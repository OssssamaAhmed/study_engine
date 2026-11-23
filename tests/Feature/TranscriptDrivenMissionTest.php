<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Topic;
use App\Services\ExerciseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscriptDrivenMissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** One fake for the whole flow: embeddings, MODE 1 missions, MODE 2 evaluations. */
    private function fakeLlm(string $missionContent, string $evalContent = '{}'): void
    {
        Http::fake(function ($request) use ($missionContent, $evalContent) {
            if (str_contains($request->url(), '/embeddings')) {
                $count = is_array($request['input']) ? count($request['input']) : 1;

                return Http::response([
                    'data' => collect(range(0, $count - 1))
                        ->map(fn ($i) => ['index' => $i, 'embedding' => [0.1, 0.2, 0.3]])
                        ->all(),
                ]);
            }

            $content = str_contains($request->body(), 'MODE 2') ? $evalContent : $missionContent;

            return Http::response(['choices' => [['message' => ['content' => $content]]]]);
        });
    }

    private function makeConcept(array $attrs = []): Concept
    {
        return Concept::create($attrs + [
            'topic_id' => Topic::first()->id,
            'name' => 'concept-'.uniqid(),
            'summary' => 'a summary',
            'next_review_at' => today()->addDay()->toDateString(),
        ]);
    }

    public function test_generate_from_concepts_creates_mission_even_when_one_exists_today(): void
    {
        $this->fakeLlm("# Mission — 2026-07-09 — Second Wind\n## Drill 1 — A [NEW]\nbody");

        Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# earlier', 'status' => 'pending']);

        $fresh = collect([$this->makeConcept(), $this->makeConcept(), $this->makeConcept()]);
        $due = $this->makeConcept(['interval_days' => 3, 'next_review_at' => today()->subDay()->toDateString(), 'lapses' => 2]);
        $notDue = $this->makeConcept(['interval_days' => 7, 'next_review_at' => today()->addDays(3)->toDateString()]);

        $exercise = app(ExerciseService::class)->generateFromConcepts($fresh->pluck('id')->all());

        $this->assertSame(2, Exercise::count());
        $roles = $exercise->concepts()->get()->mapWithKeys(fn ($c) => [$c->id => $c->pivot->role]);
        foreach ($fresh as $c) {
            $this->assertSame('new', $roles[$c->id]);
        }
        $this->assertSame('review', $roles[$due->id]);
        // Interleaving guarantee: past concepts backfill review slots even before they are due.
        $this->assertSame('review', $roles[$notDue->id]);
    }

    public function test_ingest_confirm_with_save_generate_lands_on_new_exercise(): void
    {
        $this->fakeLlm("# Mission — 2026-07-09 — First Contact\n## Drill 1 — Loops [NEW]\nbody");

        $response = $this->post('/ingest/confirm', [
            'raw_text' => 'while loops repeat until a condition is false. break exits early.',
            'source_note' => 'test',
            'topic_id' => Topic::first()->id,
            'action' => 'save_generate',
            'concepts' => [
                ['name' => 'while loop', 'summary' => 'repeats until condition false', 'keep' => '1'],
                ['name' => 'break statement', 'summary' => 'exits a loop early', 'keep' => '1'],
            ],
        ]);

        $exercise = Exercise::first();
        $this->assertNotNull($exercise);
        $response->assertRedirect("/exercise/{$exercise->id}");
        $this->assertSame(2, $exercise->concepts()->wherePivot('role', 'new')->count());
        $this->assertSame(2, Concept::count());
    }

    public function test_transcript_driven_mission_updates_progress_on_evaluation(): void
    {
        $concept = $this->makeConcept(['interval_days' => 0]);

        $this->fakeLlm(
            "# Mission — 2026-07-09 — X\n## Drill 1 — A [NEW]\nbody",
            json_encode([
                'feedback_md' => 'solid',
                'concept_scores' => [['concept_id' => $concept->id, 'score' => 95, 'evidence' => 'good']],
                'suggested_struggle_notes' => [],
            ]),
        );

        $exercise = app(ExerciseService::class)->generateFromConcepts([$concept->id]);
        app(ExerciseService::class)->evaluate($exercise, 'my answer');

        $this->assertSame(1, $concept->fresh()->interval_days); // promoted onto the ladder
        $this->assertSame('evaluated', $exercise->fresh()->status);
    }
}
