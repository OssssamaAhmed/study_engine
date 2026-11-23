<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Learner mode reaches the portfolio-project plan and skeleton, same checkbox (and session) as drills. */
class PhaseLearnerModeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '# Generated doc']]]])]);

        PhaseProgress::create([
            'phase_number' => 1,
            'status' => 'pending_project',
            'mega_project_md' => '# Mega Portfolio Project: Test Brief',
            'implementation_plan_md' => '# Implementation Plan: Test Brief',
        ]);
    }

    /** The user message the driver sent (the system prompt mentions the flag too, so check only this). */
    private function sentUserMessage(): string
    {
        $messages = Http::recorded()->first()[0]->data()['messages'];

        return collect($messages)->firstWhere('role', 'user')['content'];
    }

    public function test_plan_with_learner_mode_sends_the_flag_and_the_prompt_section(): void
    {
        $this->post('/phases/1/implementation-plan', ['learner_mode' => '1'])
            ->assertRedirect('/phases/1')
            ->assertSessionHas('learner_mode', true);

        $this->assertStringContainsString("Learner mode: ON\n", $this->sentUserMessage());
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'LEARNER MODE (applies to MODE 3 and MODE 4 only)'));
        $this->assertSame('# Generated doc', PhaseProgress::first()->implementation_plan_md);
    }

    public function test_skeleton_with_learner_mode_sends_the_flag(): void
    {
        $this->post('/phases/1/skeleton', ['learner_mode' => '1'])->assertRedirect('/phases/1');

        $this->assertStringContainsString("Learner mode: ON\n", $this->sentUserMessage());
        $this->assertSame('# Generated doc', PhaseProgress::first()->skeleton_md);
    }

    public function test_plan_without_learner_mode_has_no_flag(): void
    {
        $this->post('/phases/1/implementation-plan')->assertRedirect('/phases/1');

        $this->assertStringNotContainsString('Learner mode: ON', $this->sentUserMessage());
    }

    public function test_phase_page_shows_the_checkbox_ticked_from_the_shared_setting(): void
    {
        $this->withSession(['learner_mode' => true])->get('/phases/1')
            ->assertOk()
            ->assertSee('name="learner_mode" value="1" checked', false);
    }
}
