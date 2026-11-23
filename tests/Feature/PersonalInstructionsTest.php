<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonalInstructionsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fakeLlm(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/embeddings')) {
                $count = is_array($request['input']) ? count($request['input']) : 1;

                return Http::response([
                    'data' => collect(range(0, $count - 1))
                        ->map(fn ($i) => ['index' => $i, 'embedding' => [0.1, 0.2, 0.3]])
                        ->all(),
                ]);
            }

            return Http::response(['choices' => [['message' => ['content' => '# Mission — X']]]]);
        });
    }

    public function test_instructions_save_and_show_on_settings_page(): void
    {
        $this->post('/settings/instructions', ['instructions' => 'Keep repeating for loops in upcoming practice.'])
            ->assertRedirect('/settings');

        $this->get('/settings')->assertOk()->assertSee('Keep repeating for loops in upcoming practice.');
    }

    public function test_instructions_reach_mission_generation(): void
    {
        $this->post('/settings/instructions', ['instructions' => 'Keep weaving for loops into every mission.']);

        $this->fakeLlm();
        Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'while loops',
            'summary' => 'repeat until false',
            'next_review_at' => today()->toDateString(),
        ]);

        $this->post('/exercise/generate', ['difficulty' => 'Easy'])->assertRedirect('/');

        Http::assertSent(fn ($request) => str_contains($request->body(), "Learner's personal instructions:")
            && str_contains($request->body(), 'Keep weaving for loops into every mission.'));
    }

    public function test_instructions_reach_the_tutor(): void
    {
        $this->post('/settings/instructions', ['instructions' => 'I am 70 percent confident with for loops.']);

        $this->fakeLlm();

        $this->post('/chat', ['message' => 'What should I practice today?'])->assertRedirect('/chat');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'I am 70 percent confident with for loops.'));
    }
}
