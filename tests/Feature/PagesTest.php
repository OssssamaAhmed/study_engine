<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
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

    public function test_concepts_filters(): void
    {
        $due = $this->makeConcept(['name' => 'due-one', 'next_review_at' => today()->subDay()->toDateString()]);
        $future = $this->makeConcept(['name' => 'future-one']);
        $struggling = $this->makeConcept(['name' => 'struggling-one']);
        StruggleNote::create(['concept_id' => $struggling->id, 'note' => 'hard', 'weight' => 2]);

        $this->get('/concepts')->assertOk()->assertSee('due-one')->assertSee('future-one')->assertSee('struggling-one');
        $this->get('/concepts?filter=due')->assertOk()->assertSee('due-one')->assertDontSee('future-one');
        $this->get('/concepts?filter=struggling')->assertOk()->assertSee('struggling-one')->assertDontSee('due-one');
    }

    public function test_retire_toggle_and_note_resolution(): void
    {
        $concept = $this->makeConcept();
        $note = StruggleNote::create(['concept_id' => $concept->id, 'note' => 'hard', 'weight' => 1]);

        $this->post("/concepts/{$concept->id}/retire");
        $this->assertTrue($concept->fresh()->retired);
        $this->post("/concepts/{$concept->id}/retire");
        $this->assertFalse($concept->fresh()->retired);

        $this->post("/notes/{$note->id}/resolve");
        $this->assertTrue($note->fresh()->resolved);
    }

    public function test_history_lists_exercises_with_latest_score(): void
    {
        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => "# Mission — 2026-07-08 — Test Title\nbody", 'status' => 'evaluated']);
        $exercise->attempts()->create(['pasted_result' => 'x', 'evaluation_md' => 'y', 'score' => 82]);

        $this->get('/history')->assertOk()->assertSee('Test Title')->assertSee('82');
    }

    public function test_settings_shows_provider_and_resync_works(): void
    {
        $this->get('/settings')->assertOk()->assertSee(config('ai.provider'))->assertSee('Resync roadmap');

        $this->post('/settings/resync')->assertRedirect('/settings');
        $this->assertSame(21, Topic::count());
    }
}
