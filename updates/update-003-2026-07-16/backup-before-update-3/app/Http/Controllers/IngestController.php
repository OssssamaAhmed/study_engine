<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\ExerciseService;
use App\Services\IngestService;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    public function show()
    {
        return view('ingest.form', ['topics' => Topic::orderBy('phase_number')->get()]);
    }

    public function preview(Request $request, IngestService $ingest)
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string'],
            'source_note' => ['nullable', 'string', 'max:255'],
            'topic_id' => ['nullable', 'exists:topics,id'],
        ]);

        $topic = $data['topic_id'] ? Topic::find($data['topic_id']) : null;
        $extracted = $ingest->extractConcepts($data['raw_text'], $topic);

        $topic ??= Topic::where('phase_number', $extracted['topic_phase_number'])->first()
            ?? Topic::orderBy('phase_number')->first();

        return view('ingest.confirm', [
            'topic' => $topic,
            'topics' => Topic::orderBy('phase_number')->get(),
            'concepts' => $extracted['concepts'],
            'raw_text' => $data['raw_text'],
            'source_note' => $data['source_note'] ?? null,
        ]);
    }

    public function confirm(Request $request, IngestService $ingest, ExerciseService $exercises)
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string'],
            'source_note' => ['nullable', 'string', 'max:255'],
            'topic_id' => ['required', 'exists:topics,id'],
            'action' => ['nullable', 'in:save,save_generate'],
            'difficulty' => ['nullable', 'in:Easy,Medium,Hard'],
            'drills' => ['nullable', 'integer', 'min:5', 'max:10'],
            'concepts' => ['array'],
            'concepts.*.name' => ['required', 'string', 'max:255'],
            'concepts.*.summary' => ['required', 'string', 'max:1000'],
            'concepts.*.keep' => ['nullable'],
        ]);

        $kept = array_values(array_filter($data['concepts'] ?? [], fn ($c) => ! empty($c['keep'])));

        $result = $ingest->store(
            Topic::find($data['topic_id']),
            $data['raw_text'],
            $data['source_note'] ?? null,
            $kept,
        );

        $saved = "Saved: {$result['chunks']} chunks embedded, {$result['new']} new concepts (review tomorrow), {$result['linked']} already known.";

        if (($data['action'] ?? 'save') === 'save_generate' && $result['concept_ids'] !== []) {
            try {
                $exercise = $exercises->generateFromConcepts(
                    $result['concept_ids'],
                    $data['difficulty'] ?? 'Easy',
                    (int) ($data['drills'] ?? 5),
                );

                return redirect("/exercise/{$exercise->id}")->with('status', "{$saved} Drills generated below.");
            } catch (\Throwable $e) {
                return redirect('/')->with('status', "{$saved} But drill generation failed — try again from the dashboard. ({$e->getMessage()})");
            }
        }

        return redirect('/ingest')->with('status', $saved);
    }
}
