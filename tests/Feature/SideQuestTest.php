<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Topic;
use App\Services\ExerciseService;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SideQuestTest extends TestCase
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

    public function test_side_quest_page_renders(): void
    {
        $this->get('/side-quest')->assertOk()->assertSee('Side Quest');
    }

    public function test_side_quest_page_shows_past_quests(): void
    {
        $sq = Exercise::create([
            'date' => today()->toDateString(),
            'type' => 'side_quest',
            'mission_md' => "# Side Quest — 2026-07-23 — Nested Unpacking\nbody",
            'status' => 'pending',
        ]);

        $this->get('/side-quest')
            ->assertOk()
            ->assertSee('SQ'.$sq->id)
            ->assertSee('Nested Unpacking');
    }

    public function test_side_quest_creation_calls_llm_and_redirects(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturn("# Side Quest — 2026-07-23 — Test Quest\n## Diagnosis\nTest diagnosis.\n## Drill 1\nbody");
            $mock->shouldReceive('embedMany')->andReturn([array_fill(0, 1536, 0.0)]);
        });

        $response = $this->post('/side-quest', [
            'topic' => 'nested dictionary tuple list set unpacking confuses me',
            'difficulty' => 'Easy',
            'drills' => 5,
            'boss_fight' => '1',
        ]);

        $exercise = Exercise::where('type', 'side_quest')->latest('id')->first();
        $this->assertNotNull($exercise);
        $this->assertSame('side_quest', $exercise->type);
        $this->assertSame('pending', $exercise->status);
        $response->assertRedirect("/exercise/{$exercise->id}");
    }

    public function test_side_quest_creation_with_existing_concept(): void
    {
        $concept = $this->makeConcept(['name' => 'dictionary unpacking', 'lapses' => 3]);

        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturn("# Side Quest — 2026-07-23 — Dict Deep Dive\nbody");
            $mock->shouldReceive('embedMany')->andReturn([array_fill(0, 1536, 0.0)]);
        });

        $this->post('/side-quest', [
            'topic' => 'dictionary unpacking in nested structures',
            'concept_id' => $concept->id,
            'difficulty' => 'Medium',
            'drills' => 4,
        ]);

        $exercise = Exercise::where('type', 'side_quest')->latest('id')->first();
        $this->assertNotNull($exercise);

        // The concept should be attached to this side quest.
        $this->assertTrue($exercise->concepts->contains($concept));
        $this->assertSame('side_quest', $exercise->concepts->first()->pivot->role);
    }

    public function test_side_quest_creates_concept_when_none_match(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturn("# Side Quest — 2026-07-23 — Totally New\nbody");
            $mock->shouldReceive('embedMany')->andReturn([array_fill(0, 1536, 0.0)]);
        });

        $before = Concept::count();

        $this->post('/side-quest', [
            'topic' => 'something completely new that has no matching concept',
            'difficulty' => 'Easy',
            'drills' => 3,
        ]);

        // A new concept should have been created.
        $this->assertSame($before + 1, Concept::count());
        $newConcept = Concept::latest('id')->first();
        $this->assertStringContainsString('something completely new', $newConcept->name);
    }

    public function test_side_quest_validates_topic_required(): void
    {
        $this->post('/side-quest', ['topic' => ''])
            ->assertSessionHasErrors('topic');
    }

    public function test_exercise_model_helpers(): void
    {
        $mission = Exercise::create(['date' => today()->toDateString(), 'type' => 'mission', 'mission_md' => 'x', 'status' => 'pending']);
        $sideQuest = Exercise::create(['date' => today()->toDateString(), 'type' => 'side_quest', 'mission_md' => 'y', 'status' => 'pending']);

        $this->assertFalse($mission->isSideQuest());
        $this->assertTrue($sideQuest->isSideQuest());

        $this->assertSame(1, Exercise::sideQuests()->count());
    }
}
