<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Models\Topic;
use App\Services\ExerciseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function makeConcept(array $attrs = []): Concept
    {
        return Concept::create($attrs + [
            'topic_id' => Topic::first()->id,
            'name' => 'concept-'.uniqid(),
            'summary' => 'a summary',
            'next_review_at' => today()->addDay()->toDateString(),
        ]);
    }

    public function test_ladder_promotes_through_every_rung(): void
    {
        $svc = app(ExerciseService::class);
        $concept = $this->makeConcept(['interval_days' => 0]);

        foreach ([1, 3, 7, 14, 30, 30] as $expected) {
            $svc->applyLadder($concept, 85);
            $this->assertSame($expected, $concept->interval_days);
            $this->assertSame(today()->addDays($expected)->toDateString(), $concept->next_review_at->toDateString());
        }

        $this->assertSame(0, $concept->refresh()->lapses);
    }

    public function test_lapse_halves_interval_and_creates_note(): void
    {
        $svc = app(ExerciseService::class);
        $concept = $this->makeConcept(['interval_days' => 14]);

        $svc->applyLadder($concept, 40);

        $this->assertSame(7, $concept->interval_days);
        $this->assertSame(1, $concept->lapses);
        $this->assertSame(1, StruggleNote::where('concept_id', $concept->id)->count());
    }

    public function test_lapse_boosts_existing_note_and_floors_interval_at_one(): void
    {
        $svc = app(ExerciseService::class);
        $concept = $this->makeConcept(['interval_days' => 1]);
        $note = StruggleNote::create(['concept_id' => $concept->id, 'note' => 'weak spot', 'weight' => 1]);

        $svc->applyLadder($concept, 10);

        $this->assertSame(1, $concept->interval_days); // floor(1/2) = 0 -> min 1
        $this->assertSame(2, $note->fresh()->weight);
        $this->assertSame(1, StruggleNote::count()); // boosted, not duplicated
    }

    public function test_evaluate_round_trip_with_faked_llm(): void
    {
        $pass = $this->makeConcept(['interval_days' => 3]);
        $fail = $this->makeConcept(['interval_days' => 14]);

        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# Mission', 'status' => 'pending']);
        $exercise->concepts()->attach($pass->id, ['role' => 'review']);
        $exercise->concepts()->attach($fail->id, ['role' => 'review']);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'feedback_md' => 'Good work on X, weak on Y.',
                    'concept_scores' => [
                        ['concept_id' => $pass->id, 'score' => 90, 'evidence' => 'correct'],
                        ['concept_id' => $fail->id, 'score' => 30, 'evidence' => 'missed'],
                        ['concept_id' => 99999, 'score' => 100, 'evidence' => 'hallucinated id — must be ignored'],
                    ],
                    'suggested_struggle_notes' => [
                        ['concept_id' => $fail->id, 'note' => 'Confuses A with B', 'weight' => 2],
                    ],
                ])]]],
            ]),
        ]);

        $attempt = app(ExerciseService::class)->evaluate($exercise, 'my answer');

        $this->assertSame(60, $attempt->score); // avg(90, 30)
        $this->assertSame('Good work on X, weak on Y.', $attempt->evaluation_md);
        $this->assertSame('evaluated', $exercise->fresh()->status);

        $this->assertSame(7, $pass->fresh()->interval_days);   // 3 -> next rung 7
        $this->assertSame(7, $fail->fresh()->interval_days);   // 14 halved
        $this->assertSame(1, $fail->fresh()->lapses);

        $notes = StruggleNote::where('concept_id', $fail->id)->get();
        $this->assertCount(1, $notes); // suggested note stored, no duplicate generic note
        $this->assertSame('Confuses A with B', $notes[0]->note);
        $this->assertSame(2, $notes[0]->weight);
    }

    /** The stateless evaluator learns the Boss Fight phase from prior feedback. */
    public function test_previous_evaluations_reach_the_mode2_request(): void
    {
        $concept = $this->makeConcept(['interval_days' => 3]);
        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# Mission', 'status' => 'pending']);
        $exercise->concepts()->attach($concept->id, ['role' => 'review']);

        Attempt::create([
            'exercise_id' => $exercise->id,
            'pasted_result' => 'my skeleton',
            'evaluation_md' => 'Skeleton approved — build the outer loop first.',
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{"feedback_md":"ok","concept_scores":[],"suggested_struggle_notes":[]}']]],
            ]),
        ]);

        app(ExerciseService::class)->evaluate($exercise, 'outer loop code');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Previous evaluations of this mission')
            && str_contains($request->body(), 'Skeleton approved'));
    }

    public function test_exercise_page_renders_and_own_note_attaches_to_all_concepts(): void
    {
        $a = $this->makeConcept();
        $b = $this->makeConcept();
        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# Mission body', 'status' => 'pending']);
        $exercise->concepts()->attach([$a->id => ['role' => 'new'], $b->id => ['role' => 'new']]);

        $this->get("/exercise/{$exercise->id}")->assertOk()->assertSee('Mission body')->assertSee('Submit for evaluation');

        $this->post("/exercise/{$exercise->id}/note", ['note' => 'this was hard for me'])
            ->assertRedirect("/exercise/{$exercise->id}");

        $this->assertSame(2, StruggleNote::where('note', 'this was hard for me')->where('weight', 2)->count());
    }
}
