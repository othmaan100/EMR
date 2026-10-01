<?php

namespace App\Http\Controllers;

use App\Models\NursingNote;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NursingNoteController extends Controller
{
    /**
     * Notes are append-only: a correction is written as a new note.
     */
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validateWithBag('note', [
            'type' => ['required', Rule::in(array_keys(NursingNote::TYPES))],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $note = new NursingNote($data);
        $note->patient_id = $patient->id;
        $note->visit_id = $patient->visits()->open()->value('id');
        $note->user_id = $request->user()->id;
        $note->save();

        return back()->with('success', 'Nursing note added.');
    }
}
