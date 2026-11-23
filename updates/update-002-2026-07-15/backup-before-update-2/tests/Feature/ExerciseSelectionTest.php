<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Topic;
use App\Services\ExerciseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function makeConcept(array $attrs): Concept
    {
        return Concept::create($attrs + [
            'topic_id' => Topic::first()->id,
            'name' => 'concept-'.uniqid(),
            'summary' => 'a summary',
            'next_review_at' => today()->addDay()->toDateString(),
        ]);
    }

    public function test_sixty_forty_composition_with_due_priority(): void
    {
        foreach (range(1, 6) as $i) {
            $this->makeConcept(['name' => "new-{$i}", 'interval_days' => 0]);
        }
        // 10 due concepts; lapses 0..9, most-lapsed should win the 4 slots
        foreach (range(0, 9) as $lapses) {
            $this->makeConcept([
                'name' => "due-{$lapses}",
                'interval_days' => 3,
                'lapses' => $lapses,
                'next_review_at' => today()->subDay()->toDateString(),
            ]);
        }

        [$new, $due] = app(ExerciseService::class)->selectConcepts();

        $this->assertCount(6, $new);
        $this->assertCount(4, $due); // ceil(6 * 2/3) = 4 -> 6/10 = 60% new
        $this->assertSame(['due-9', 'due-8', 'due-7', 'due-6'], $due->pluck('name')->all());
    }

    public function test_all_new_when_nothing_due(): void
    {
        $this->makeConcept(['name' => 'only-new', 'interval_days' => 0]);

        [$new, $due] = app(ExerciseService::class)->selectConcepts();

        $this->assertCount(1, $new);
        $this->assertCount(0, $due);
    }

    /** Past topics must repeat: not-yet-due reviewed concepts backfill the review slots. */
    public function test_reviews_backfill_when_nothing_is_due(): void
    {
        $this->makeConcept(['name' => 'new-1', 'interval_days' => 0]);
        $this->makeConcept(['name' => 'soon', 'interval_days' => 3, 'next_review_at' => today()->addDay()->toDateString()]);
        $this->makeConcept(['name' => 'later', 'interval_days' => 7, 'next_review_at' => today()->addDays(6)->toDateString()]);
        $this->makeConcept(['name' => 'due-lapsed', 'interval_days' => 1, 'lapses' => 2, 'next_review_at' => today()->subDay()->toDateString()]);

        [$new, $due] = app(ExerciseService::class)->selectConcepts();

        $this->assertCount(1, $new);
        // Floor of 2 review slots: the overdue lapsed concept first, then the nearest upcoming one.
        $this->assertSame(['due-lapsed', 'soon'], $due->pluck('name')->all());
    }

    public function test_dashboard_resumes_pending_mission_from_yesterday(): void
    {
        Exercise::create([
            'date' => today()->subDay()->toDateString(),
            'mission_md' => "# Mission — Old Ghosts\nbody",
            'status' => 'pending',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Old Ghosts')
            ->assertSee('Unfinished mission');
    }

    public function test_retired_and_future_concepts_are_excluded(): void
    {
        $this->makeConcept(['name' => 'retired', 'interval_days' => 0, 'retired' => true]);
        $this->makeConcept([
            'name' => 'future-review',
            'interval_days' => 7,
            'next_review_at' => today()->addDays(5)->toDateString(),
        ]);

        [$new, $due] = app(ExerciseService::class)->selectConcepts();

        $this->assertCount(0, $new);
        $this->assertCount(0, $due);
    }

    public function test_cosine_similarity(): void
    {
        $svc = app(ExerciseService::class);

        $this->assertEqualsWithDelta(1.0, $svc->cosine([1, 2, 3], [1, 2, 3]), 1e-9);
        $this->assertEqualsWithDelta(0.0, $svc->cosine([1, 0], [0, 1]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, $svc->cosine([1, 0], [-1, 0]), 1e-9);
        $this->assertSame(0.0, $svc->cosine([0, 0], [1, 1]));
    }

    public function test_dashboard_renders_generate_button_when_no_mission(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Generate today')
            ->assertSee('Welcome back');
    }
}
