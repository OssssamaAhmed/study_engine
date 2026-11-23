<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Topic;
use App\Services\ExerciseService;
use App\Support\LearnerInstructions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatMissionPaginationFeedbackTest extends TestCase
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

    // US-001 — chat message limit raised past 4000 chars

    public function test_chat_accepts_messages_longer_than_4000_chars(): void
    {
        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'ok']]]]));

        $this->post('/chat', ['message' => str_repeat('x', 12000)])
            ->assertRedirect('/chat')
            ->assertSessionHasNoErrors();
    }

    public function test_chat_still_rejects_messages_over_the_new_limit(): void
    {
        $this->post('/chat', ['message' => str_repeat('x', 20001)])
            ->assertSessionHasErrors('message');
    }

    // US-002 — concepts + history paginate at 20 per page

    public function test_concepts_page_shows_20_per_page_and_keeps_the_filter(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeConcept([
                'name' => sprintf('pageconcept-%02d', $i),
                'interval_days' => 1,
                'next_review_at' => today()->subDays(30 - $i)->toDateString(),
            ]);
        }

        $this->get('/concepts?filter=due')
            ->assertOk()
            ->assertSee('pageconcept-01')
            ->assertSee('pageconcept-20')
            ->assertDontSee('pageconcept-21')
            ->assertSee('filter=due&amp;page=2', false); // page links carry the filter

        $this->get('/concepts?filter=due&page=2')
            ->assertOk()
            ->assertSee('pageconcept-21')
            ->assertDontSee('pageconcept-01');
    }

    public function test_history_page_shows_20_per_page(): void
    {
        foreach (range(1, 25) as $i) {
            Exercise::create(['date' => today()->toDateString(), 'mission_md' => "# Mission — E{$i}", 'status' => 'pending']);
        }

        // Newest first: M25..M6 on page 1, M5..M1 on page 2.
        $this->get('/history')->assertOk()->assertSee('M25')->assertDontSee('M5 ·');
        $this->get('/history?page=2')->assertOk()->assertSee('M5 ·')->assertDontSee('M25');
    }

    // US-003 — tutor practice becomes a submittable mission

    public function test_saving_a_tutor_drill_creates_a_mission_linked_to_named_concepts(): void
    {
        $concept = $this->makeConcept(['name' => 'for loops', 'interval_days' => 3]);
        $this->makeConcept(['name' => 'unrelated thing', 'interval_days' => 3]);

        $drill = "## Drill 1\n**Setup:** a list of numbers\n**Task:** sum them with for loops\n**Output:** 15\n**Concepts:** for loops";

        $response = $this->withSession(['chat' => [
            ['role' => 'learner', 'content' => 'drill me on loops'],
            ['role' => 'tutor', 'content' => $drill],
        ]])->post('/chat/mission', ['index' => 1]);

        $exercise = Exercise::first();
        $response->assertRedirect("/exercise/{$exercise->id}");
        $this->assertSame('pending', $exercise->status);
        $this->assertSame($drill, $exercise->mission_md);
        $this->assertSame(['for loops'], $exercise->concepts()->pluck('name')->all());
        $this->assertSame('review', $exercise->concepts()->first()->pivot->role);
        $this->assertTrue($concept->is($exercise->concepts()->first()));
    }

    public function test_saving_a_learner_message_or_bad_index_is_refused(): void
    {
        $this->withSession(['chat' => [['role' => 'learner', 'content' => 'hi']]])
            ->post('/chat/mission', ['index' => 0])
            ->assertRedirect('/chat');

        $this->withSession(['chat' => []])
            ->post('/chat/mission', ['index' => 5])
            ->assertRedirect('/chat');

        $this->assertSame(0, Exercise::count());
    }

    public function test_tutor_drill_replies_show_a_save_as_mission_button(): void
    {
        $response = $this->withSession(['chat' => [
            ['role' => 'tutor', 'content' => "**Task:** do a thing"],
            ['role' => 'tutor', 'content' => 'plain explanation, no drill'],
        ]])->get('/chat')->assertOk();

        // Only the drill reply gets the button — not the plain explanation.
        $this->assertSame(1, substr_count($response->getContent(), 'Save as mission'));
    }

    // US-004 — personal-instructions feedback pulls named concepts into missions

    public function test_concepts_named_in_instructions_jump_the_review_queue(): void
    {
        LearnerInstructions::set('list and dict methods are slipping from my grip');

        $this->makeConcept(['name' => 'new-1', 'interval_days' => 0]);
        $this->makeConcept(['name' => 'sets basics', 'interval_days' => 3, 'next_review_at' => today()->addDay()->toDateString()]);
        $this->makeConcept(['name' => 'dict methods', 'interval_days' => 7, 'next_review_at' => today()->addDays(6)->toDateString()]);

        [$new, $due] = app(ExerciseService::class)->selectConcepts();

        // Without feedback the nearer 'sets basics' would come first; the
        // named concept jumps ahead even though its review is a week away.
        $this->assertSame(['dict methods', 'sets basics'], $due->pluck('name')->all());
    }

    public function test_empty_instructions_change_nothing(): void
    {
        $this->makeConcept(['name' => 'new-1', 'interval_days' => 0]);
        $this->makeConcept(['name' => 'sets basics', 'interval_days' => 3, 'next_review_at' => today()->addDay()->toDateString()]);
        $this->makeConcept(['name' => 'dict methods', 'interval_days' => 7, 'next_review_at' => today()->addDays(6)->toDateString()]);

        [, $due] = app(ExerciseService::class)->selectConcepts();

        $this->assertSame(['sets basics', 'dict methods'], $due->pluck('name')->all());
    }
}
