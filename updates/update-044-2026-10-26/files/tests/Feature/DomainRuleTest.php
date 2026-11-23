<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Topic;
use App\Support\StudyMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Learner requirement: every drill and example is set in the track's real work — never generic textbook data. */
class DomainRuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_every_prompt_renders_with_no_leftover_track_tokens(): void
    {
        foreach (glob(resource_path('prompts/*.md')) as $file) {
            foreach ([StudyMode::AI_SECURITY, StudyMode::LARAVEL_WEB] as $mode) {
                $this->assertStringNotContainsString('{{TRACK_', StudyMode::renderPrompt(file_get_contents($file), $mode), basename($file));
            }
        }
    }

    public function test_side_quest_and_tutor_prompts_carry_the_track_domain_rule(): void
    {
        foreach (['side-quest-engine.md', 'tutor-chat.md'] as $prompt) {
            $raw = file_get_contents(resource_path("prompts/{$prompt}"));

            $this->assertStringContainsString('real AI Security Engineering work', StudyMode::renderPrompt($raw, StudyMode::AI_SECURITY));
            $this->assertStringContainsString('real Laravel & React web development work', StudyMode::renderPrompt($raw, StudyMode::LARAVEL_WEB));
        }
    }

    public function test_mission_request_carries_the_domain_rule(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '# Mission — X']]],
            'data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]],
        ])]);

        Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'for loops',
            'summary' => 'repeat over items',
            'next_review_at' => today()->toDateString(),
        ]);

        $this->post('/exercise/generate', ['drills' => 3])->assertRedirect('/');

        Http::assertSent(fn ($r) => str_contains($r->body(), 'HARD RULE') && str_contains($r->body(), 'DOMAIN ONLY'));
    }
}
