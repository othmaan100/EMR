<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SmsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(SmsMessage::STATUSES))],
            'type' => ['nullable', Rule::in(array_keys(SmsMessage::TYPES))],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $messages = SmsMessage::with('patient')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('phone', 'like', '%'.preg_replace('/\D+/', '', $term).'%')
                ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->latest('id')->paginate(30)->withQueryString();

        $today = SmsMessage::whereDate('created_at', today())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.sms.index', [
            'messages' => $messages,
            'filters' => $filters,
            'today' => $today,
            'provider' => SmsService::PROVIDERS[setting('sms_provider', 'none')] ?? 'Log only',
        ]);
    }

    /**
     * Sends immediately (not queued) so the result shows at once.
     */
    public function test(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $body = $sms->render('Test message from {hospital}. SMS is working.', null);
        $message = $sms->queue(null, $data['phone'], $body, 'test', null, $request->user()->id);
        if (! $message) {
            return back()->withErrors(['phone' => 'That does not look like a valid phone number.'])->withInput();
        }

        $message = $sms->deliver($message);

        return match ($message->status) {
            'sent' => back()->with('success', "Test SMS sent to {$message->phone}."),
            'logged' => back()->with('success', 'Test message logged. Choose an SMS provider in Hospital Settings → SMS messaging to actually send.'),
            default => back()->withErrors(['phone' => 'Sending failed: '.$message->error]),
        };
    }

    public function retry(SmsMessage $message): RedirectResponse
    {
        abort_unless($message->status === 'failed', 404);

        $message->forceFill(['status' => 'queued', 'attempts' => 0, 'error' => null])->save();

        return back()->with('success', 'Message queued to send again.');
    }
}
