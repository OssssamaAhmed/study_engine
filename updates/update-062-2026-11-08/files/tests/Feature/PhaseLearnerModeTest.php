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

    private const LEARNER_PLAN = "# Implementation Plan: Test Brief\n\n**Learner mode: ON**\n\n## Milestone Map\n"
        . "- Milestone 1: Setup — support: FULL\n- Milestone 2: Parser — support: GUIDED\n\n"
        . "## Milestone 1: Setup\nstep one\n\n## GLOSSARY\n- `venv` — private Python\n\nEND OF DOCUMENT";

    protected function setUp(): void
    {
        parent::setUp();

        PhaseProgress::create([
            'phase_number' => 1,
            'status' => 'pending_project',
            'mega_project_md' => '# Mega Portfolio Project: Test Brief',
            'implementation_plan_md' => '# Implementation Plan: Test Brief',
        ]);
    }

    /** Each chat call gets the next reply, in order. */
    private function fakeReplies(string ...$replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['choices' => [['message' => ['content' => $reply]]]]);
        }
        Http::fake(['openrouter.ai/*' => $sequence]);
    }

    /** The user message of the n-th request (the system prompt mentions the flag too, so check only this). */
    private function sentUserMessage(int $n = 0): string
    {
        $messages = Http::recorded()[$n][0]->data()['messages'];

        return collect($messages)->firstWhere('role', 'user')['content'];
    }

    public function test_plan_with_learner_mode_asks_for_the_whole_plan(): void
    {
        $this->fakeReplies(self::LEARNER_PLAN);

        $this->post('/phases/1/implementation-plan', ['learner_mode' => '1'])
            ->assertRedirect('/phases/1#plan')
            ->assertSessionHas('learner_mode', true);

        Http::assertSentCount(1);
        $this->assertStringContainsString("Learner mode: ON\n", $this->sentUserMessage());
        $this->assertStringContainsString('FULL Learner-mode implementation plan', $this->sentUserMessage());
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'LEARNER MODE (applies to MODE 3, MODE 4 and MODE 5)'));
        $this->assertSame(self::LEARNER_PLAN, PhaseProgress::first()->implementation_plan_md);

        $this->get('/phases/1')->assertOk()->assertDontSee('the model was cut off');
    }

    public function test_a_cut_off_learner_reply_is_continued_until_end_of_document(): void
    {
        $this->fakeReplies("**Learner mode: ON**\n## Milestone 1: Setup\nWe open the fi", "le in VS Code.\n\nEND OF DOCUMENT");

        $this->post('/phases/1/implementation-plan', ['learner_mode' => '1']);

        Http::assertSentCount(2);
        $this->assertStringContainsString('YOUR REPLY SO FAR', $this->sentUserMessage(1));
        $this->assertStringContainsString('We open the file in VS Code.', PhaseProgress::first()->implementation_plan_md);
        $this->assertStringEndsWith('END OF DOCUMENT', PhaseProgress::first()->implementation_plan_md);
    }

    public function test_cut_off_learner_document_shows_a_warning(): void
    {
        PhaseProgress::first()->update(['implementation_plan_md' => "**Learner mode: ON**\n## Milestone 1: Setup\nWe open the fi"]);

        $this->get('/phases/1')->assertSee('the model was cut off');
    }

    public function test_skeleton_with_learner_mode_sends_the_flag(): void
    {
        $this->fakeReplies("# Skeleton\n\nEND OF DOCUMENT");

        $this->post('/phases/1/skeleton', ['learner_mode' => '1'])->assertRedirect('/phases/1#skeleton');

        $this->assertStringContainsString("Learner mode: ON\n", $this->sentUserMessage());
        $this->assertSame("# Skeleton\n\nEND OF DOCUMENT", PhaseProgress::first()->skeleton_md);
    }

    public function test_plan_without_learner_mode_is_one_unchanged_call(): void
    {
        $this->fakeReplies('# Generated doc');

        $this->post('/phases/1/implementation-plan')->assertRedirect('/phases/1#plan');

        Http::assertSentCount(1);
        $this->assertStringNotContainsString('Learner mode: ON', $this->sentUserMessage());
        $this->assertStringNotContainsString('Write only', $this->sentUserMessage());
        $this->assertSame('# Generated doc', PhaseProgress::first()->implementation_plan_md);
    }

    public function test_phase_page_ships_the_support_level_picker(): void
    {
        PhaseProgress::first()->update(['implementation_plan_md' => self::LEARNER_PLAN]);

        $this->get('/phases/1')->assertOk()
            ->assertSee("['spec', 'SPEC ONLY']", false)
            ->assertSee('Recommended support level:', false);
    }

    public function test_phase_page_shows_the_checkbox_ticked_from_the_shared_setting(): void
    {
        $this->withSession(['learner_mode' => true])->get('/phases/1')
            ->assertOk()
            ->assertSee('name="learner_mode" value="1" checked', false);
    }
}
