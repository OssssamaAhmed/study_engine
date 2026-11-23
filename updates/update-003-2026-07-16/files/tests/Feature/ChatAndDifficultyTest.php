<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatAndDifficultyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function fakeLlm(string $chatContent): void
    {
        Http::fake(function ($request) use ($chatContent) {
            if (str_contains($request->url(), '/embeddings')) {
                $count = is_array($request['input']) ? count($request['input']) : 1;

                return Http::response([
                    'data' => collect(range(0, $count - 1))
                        ->map(fn ($i) => ['index' => $i, 'embedding' => [0.1, 0.2, 0.3]])
                        ->all(),
                ]);
            }

            return Http::response(['choices' => [['message' => ['content' => $chatContent]]]]);
        });
    }

    public function test_dashboard_generate_sends_selected_difficulty_to_the_llm(): void
    {
        $this->fakeLlm('# Mission — X');

        Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'for loops',
            'summary' => 'repeat over a sequence',
            'next_review_at' => today()->toDateString(),
        ]);

        $this->post('/exercise/generate', ['difficulty' => 'Hard'])->assertRedirect('/');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Requested difficulty: Hard'));
    }

    public function test_ingest_save_generate_sends_selected_difficulty_to_the_llm(): void
    {
        $this->fakeLlm('# Mission — X');

        $this->post('/ingest/confirm', [
            'raw_text' => 'dictionaries map keys to values.',
            'topic_id' => Topic::first()->id,
            'action' => 'save_generate',
            'difficulty' => 'Medium',
            'concepts' => [['name' => 'dictionary', 'summary' => 'maps keys to values', 'keep' => '1']],
        ]);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Requested difficulty: Medium'));
    }

    public function test_invalid_difficulty_is_rejected(): void
    {
        $this->post('/exercise/generate', ['difficulty' => 'Nightmare'])->assertSessionHasErrors('difficulty');
    }

    public function test_chosen_drill_count_reaches_the_llm_request(): void
    {
        $this->fakeLlm('# Mission — X');

        Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'while loops',
            'summary' => 'repeat until false',
            'next_review_at' => today()->toDateString(),
        ]);

        $this->post('/exercise/generate', ['difficulty' => 'Easy', 'drills' => 7])->assertRedirect('/');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Requested drills: 7'));
    }

    public function test_drill_count_below_minimum_is_rejected(): void
    {
        $this->post('/exercise/generate', ['drills' => 3])->assertSessionHasErrors('drills');
    }

    public function test_chosen_boss_fight_count_reaches_the_llm_request(): void
    {
        $this->fakeLlm('# Mission — X');

        Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'sets',
            'summary' => 'unordered unique values',
            'next_review_at' => today()->toDateString(),
        ]);

        $this->post('/exercise/generate', ['drills' => 6, 'bosses' => 2])->assertRedirect('/');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Requested Boss Fights: 2'));
    }

    public function test_boss_fight_count_out_of_range_is_rejected(): void
    {
        $this->post('/exercise/generate', ['bosses' => 0])->assertSessionHasErrors('bosses');
        $this->post('/exercise/generate', ['bosses' => 4])->assertSessionHasErrors('bosses');
    }

    public function test_tutor_receives_struggles_and_due_concepts(): void
    {
        $concept = Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'tuple unpacking',
            'summary' => 'assign multiple values at once',
            'interval_days' => 1,
            'next_review_at' => today()->subDay()->toDateString(),
        ]);
        StruggleNote::create(['concept_id' => $concept->id, 'note' => 'confuses order of values', 'weight' => 2]);

        $this->fakeLlm('Practice drill: ...');

        $this->post('/chat', ['message' => 'I struggle with some topics, drill me'])->assertRedirect('/chat');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'tuple unpacking')
            && str_contains($request->body(), 'confuses order of values'));
    }

    public function test_chat_page_renders_and_round_trip_stores_both_messages(): void
    {
        $this->get('/chat')->assertOk()->assertSee('Tutor');

        $this->fakeLlm("Use a plain `for` loop first.\n\nConcepts used: for loops");

        $this->post('/chat', ['message' => "How do I loop over a list?\nSecond line."])
            ->assertRedirect('/chat');

        $messages = session('chat');
        $this->assertCount(2, $messages);
        $this->assertSame('learner', $messages[0]['role']);
        $this->assertSame('tutor', $messages[1]['role']);

        $this->get('/chat')->assertSee('Use a plain', false);

        // The transcript (with the learner's message) reached the model.
        Http::assertSent(fn ($request) => str_contains($request->body(), 'How do I loop over a list?'));
    }

    public function test_chat_clear_empties_the_conversation(): void
    {
        session(['chat' => [['role' => 'learner', 'content' => 'hi']]]);

        $this->post('/chat/clear')->assertRedirect('/chat');

        $this->assertNull(session('chat'));
    }

    public function test_struggle_note_input_is_a_multiline_textarea(): void
    {
        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# Mission — X', 'status' => 'evaluated']);
        Attempt::create(['exercise_id' => $exercise->id, 'pasted_result' => 'code', 'evaluation_md' => 'fine', 'score' => 80]);

        $this->get("/exercise/{$exercise->id}")
            ->assertOk()
            ->assertSee('<textarea name="note"', false);
    }
}
