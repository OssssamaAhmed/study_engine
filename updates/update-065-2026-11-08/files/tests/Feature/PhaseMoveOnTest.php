<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Services\RoadmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * User requirement: ONE graded attempt of a phase's Mega Project (any score) unlocks the next phase, so the learner's
 * studies never wait for a perfect project. The old project stays open: more attempts are still graded, and passing one
 * turns the phase from "moved on" into "completed".
 */
class PhaseMoveOnTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        PhaseProgress::create([
            'phase_number' => 1,
            'status' => 'pending_project',
            'mega_project_md' => "# Mega Portfolio Project: Test Sentinel\n\nCore Functionality [40%].",
            'implementation_plan_md' => "# Implementation Plan: Test Sentinel\n\n## Milestone 1: Setup\n\n**COMPLETE ONLY WHEN:**\n- [ ] commit visible\n\nEND OF DOCUMENT",
        ]);
    }

    /** Each model call returns the next reply: an int is a grading with that score, a string is the raw reply text. */
    private function gradings(int|string ...$replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $content = is_string($reply) ? $reply : json_encode([
                'feedback_md' => "# Evaluation {$reply}", 'overall_score' => $reply, 'passed' => $reply >= 70, 'concept_scores' => [], 'security_insights' => '',
            ]);
            $sequence->push(['choices' => [['message' => ['content' => $content]]]]);
        }
        Http::fake(['openrouter.ai/*' => $sequence]);
    }

    private function submit(): void
    {
        $this->post('/phases/1/submit', ['submission' => 'def run(): return 1  # a plain code submission'])->assertRedirect('/phases/1');
    }

    private function active(): int
    {
        return app(RoadmapService::class)->currentPhaseTopic()->phase_number;
    }

    public function test_the_next_phase_stays_locked_until_the_project_is_graded_once(): void
    {
        $this->post('/phases/1/complete')
            ->assertRedirect('/phases/1')
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Submit your'));

        $this->assertSame('pending_project', PhaseProgress::first()->status);
        $this->assertSame(1, $this->active());
        $this->get('/phases/1')->assertSee('Next phase locked');
    }

    public function test_one_failing_attempt_is_enough_to_move_on_and_the_project_stays_open(): void
    {
        $this->gradings(55);
        $this->submit();
        $this->assertSame('pending_project', PhaseProgress::first()->status);   // grading alone does not move the learner

        $this->get('/phases/1')->assertSee('Move on to Phase 2');

        $this->post('/phases/1/complete')
            ->assertRedirect('/phases/1')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Moved on') && str_contains($s, '55/100'));

        $progress = PhaseProgress::first();
        $this->assertSame('moved_on', $progress->status);
        $this->assertNull($progress->completed_at);   // not finished: the project has not passed
        $this->assertSame(2, $this->active());        // missions, chat and the dashboard now work on Phase 2

        $this->get('/')->assertOk()->assertSee('Projects still open')->assertSee('55/100')->assertSee('Active Phase 2');
        $this->get('/phases')->assertOk()->assertSee('Moved on · project open');
        $this->get('/phases/1')->assertSee('Moved on · project still open');
    }

    public function test_attempts_after_moving_on_are_still_graded_and_a_pass_completes_the_phase(): void
    {
        $this->gradings(55, 62, 81);
        $this->submit();
        $this->post('/phases/1/complete');

        $this->submit();   // attempt 2, still failing
        $this->assertSame('moved_on', PhaseProgress::first()->status);
        $this->assertSame(62, PhaseProgress::first()->project_score);

        $this->submit();   // attempt 3, passes
        $progress = PhaseProgress::first();
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertCount(3, $progress->project_attempts);
        $this->assertSame(2, $this->active());   // the learner is not pulled back to Phase 1
        $this->get('/')->assertDontSee('Projects still open');
    }

    public function test_a_passing_first_attempt_completes_the_phase_as_before(): void
    {
        $this->gradings(85);
        $this->submit();
        $this->post('/phases/1/complete')->assertSessionHas('status', fn ($s) => str_contains($s, 'officially marked as completed'));

        $this->assertSame('completed', PhaseProgress::first()->status);
    }

    public function test_a_completed_phase_is_never_downgraded_by_a_later_weaker_attempt(): void
    {
        $this->gradings(85, 60);
        $this->submit();
        $this->submit();   // a weaker re-audit: the score drops, the phase stays finished
        $this->assertSame('completed', PhaseProgress::first()->status);

        $this->post('/phases/1/complete');

        $this->assertSame('completed', PhaseProgress::first()->status);
    }

    public function test_a_new_brief_does_not_take_a_moved_on_phase_back(): void
    {
        $this->gradings(55, "# Mega Portfolio Project: New Brief\n\nBody.");
        $this->submit();
        $this->post('/phases/1/complete');

        $this->post('/phases/1/project')->assertRedirect('/phases/1');

        $this->assertSame('moved_on', PhaseProgress::first()->status);
        $this->assertSame(2, $this->active());
    }
}
