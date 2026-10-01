<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AncVisit;
use App\Models\Baby;
use App\Models\Delivery;
use App\Models\PartographEntry;
use App\Models\Patient;
use App\Models\PostnatalVisit;
use App\Models\Pregnancy;
use App\Models\Service;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaternityService
{
    public function __construct(protected BillingService $billing) {}

    public function book(Patient $patient, array $data, User $by): Pregnancy
    {
        if ($patient->gender !== 'female') {
            throw ValidationException::withMessages(['patient' => 'Antenatal booking is only for female patients.']);
        }
        if ($patient->pregnancies()->active()->exists()) {
            throw ValidationException::withMessages(['patient' => "{$patient->full_name} already has an active pregnancy record."]);
        }

        $data['edd'] = ! empty($data['edd_scan'])
            ? Carbon::parse($data['edd_scan'])
            : Pregnancy::eddFromLmp(Carbon::parse($data['lmp']));
        $data['edd_by_scan'] = ! empty($data['edd_scan']);

        return DB::transaction(function () use ($patient, $data, $by) {
            $pregnancy = new Pregnancy($data);
            $pregnancy->patient_id = $patient->id;
            $pregnancy->booked_by = $by->id;
            $pregnancy->save();

            $this->chargeService($patient, Service::ANC_BOOKING, $pregnancy, $by);

            return $pregnancy;
        });
    }

    public function recordAnc(Pregnancy $pregnancy, array $data, User $by): AncVisit
    {
        $this->assertActive($pregnancy);

        $visit = new AncVisit($data);
        $visit->recorded_by = $by->id;
        $pregnancy->ancVisits()->save($visit);

        return $visit;
    }

    public function startLabour(Pregnancy $pregnancy): void
    {
        $this->assertActive($pregnancy);
        $pregnancy->forceFill(['labour_started_at' => $pregnancy->labour_started_at ?? now()])->save();
    }

    public function addPartograph(Pregnancy $pregnancy, array $data, User $by): PartographEntry
    {
        $this->assertActive($pregnancy);
        if (! $pregnancy->labour_started_at) {
            $this->startLabour($pregnancy);
        }

        $entry = new PartographEntry($data + ['recorded_at' => now()]);
        $entry->recorded_by = $by->id;
        $pregnancy->partograph()->save($entry);

        return $entry;
    }

    /**
     * Record the birth. Live-born babies are registered as patients linked
     * to the mother, inheriting her address, next of kin and payer.
     *
     * @param  list<array{sex: string, outcome: string, birth_weight_g?: ?int, apgar_1?: ?int, apgar_5?: ?int, resuscitated?: bool, name?: ?string, notes?: ?string}>  $babies
     */
    public function recordDelivery(Pregnancy $pregnancy, array $data, array $babies, User $by): Delivery
    {
        $this->assertActive($pregnancy);
        $mother = $pregnancy->patient;

        return DB::transaction(function () use ($pregnancy, $data, $babies, $by, $mother) {
            $admission = Admission::current()->where('patient_id', $mother->id)->first();

            $delivery = new Delivery($data + ['admission_id' => $admission?->id]);
            $delivery->attended_by = $by->id;
            $pregnancy->delivery()->save($delivery);

            $liveBorn = 0;
            foreach ($babies as $input) {
                $baby = new Baby($input);
                if ($baby->isLiveBirth() || $baby->outcome === 'neonatal_death') {
                    $baby->patient_id = $this->registerBaby($mother, $delivery, $input, $by)->id;
                    $liveBorn++;
                }
                $delivery->babies()->save($baby);
            }

            $pregnancy->forceFill([
                'status' => 'delivered',
                'parity' => $pregnancy->parity + 1,
                'living_children' => $pregnancy->living_children + collect($babies)->where('outcome', 'live_birth')->count(),
            ])->save();

            if ($data['maternal_outcome'] === 'died') {
                $mother->forceFill(['is_deceased' => true, 'date_of_death' => $delivery->delivered_at->toDateString()])->save();
            }

            $code = $delivery->isCaesarean() ? Service::DELIVERY_CAESAREAN : Service::DELIVERY_VAGINAL;
            $this->chargeService($mother, $code, $delivery, $by, $admission);

            Audit::log('delivery_recorded', "Delivery recorded for {$mother->hospital_number}: {$delivery->modeLabel()}, {$liveBorn} live-born", $delivery);

            return $delivery;
        });
    }

    public function recordPostnatal(Pregnancy $pregnancy, array $data, User $by): PostnatalVisit
    {
        if ($pregnancy->status !== 'delivered') {
            throw ValidationException::withMessages(['status' => 'Postnatal visits are recorded after delivery.']);
        }

        $visit = new PostnatalVisit($data);
        $visit->recorded_by = $by->id;
        $pregnancy->postnatalVisits()->save($visit);

        return $visit;
    }

    public function end(Pregnancy $pregnancy, string $reason): void
    {
        $this->assertActive($pregnancy);
        $pregnancy->forceFill(['status' => 'ended', 'end_reason' => $reason])->save();
        Audit::log('pregnancy_ended', "Pregnancy record closed: {$reason}", $pregnancy);
    }

    protected function registerBaby(Patient $mother, Delivery $delivery, array $input, User $by): Patient
    {
        $baby = new Patient([
            'title' => 'Baby',
            'first_name' => filled($input['name'] ?? null) ? $input['name'] : 'Baby',
            'last_name' => $mother->last_name,
            'gender' => $input['sex'],
            'date_of_birth' => $delivery->delivered_at->toDateString(),
            'phone' => $mother->phone,
            'address' => $mother->address,
            'city' => $mother->city,
            'state' => $mother->state,
            'country' => $mother->country,
            'nationality' => $mother->nationality,
            'nok_name' => $mother->full_name,
            'nok_relationship' => 'Parent',
            'nok_phone' => $mother->phone,
            'payment_type' => $mother->payment_type,
            'insurance_provider_id' => $mother->insurance_provider_id,
            'insurance_number' => $mother->insurance_number,
            'is_deceased' => ($input['outcome'] ?? null) === 'neonatal_death',
            'date_of_death' => ($input['outcome'] ?? null) === 'neonatal_death' ? $delivery->delivered_at->toDateString() : null,
        ]);
        $baby->registered_by = $by->id;
        $baby->mother_id = $mother->id;
        $baby->save();

        return $baby;
    }

    protected function chargeService(Patient $patient, string $code, $source, User $by, ?Admission $admission = null): void
    {
        $service = Service::active()->where('code', $code)->first();
        if ($service) {
            $context = $admission ?? $patient->visits()->open()->first();
            $this->billing->charge($patient, $context, $service, $source, 1, $by);
        }
    }

    protected function assertActive(Pregnancy $pregnancy): void
    {
        if ($pregnancy->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'This pregnancy record is closed.']);
        }
    }
}
