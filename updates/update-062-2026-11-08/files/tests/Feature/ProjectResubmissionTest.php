<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Services\PhaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** After a graded submission: the app writes the plan for the next attempt, and resubmissions know the earlier attempts. */
class ProjectResubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const BRIEF = "# Mega Portfolio Project: Test Sentinel\n\n## 5. Evaluation Rubric & Pass Criteria\nCore Functionality [40%]. Passing score: 70/100.";

    private const PLAN = "# Implementation Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## Architecture Lock\nKEEP_THESE_NAMES: LogParser, ThreatSentinel.\n\n"
        . "## Milestone 1: Setup\n\n#### Step 1 — Do a thing\n\n**Goal:**\n- do STEP_ONLY_TEXT\n\n"
        . "**FINAL ACCEPTANCE CRITERIA**\n- [ ] repo exists\n\n**COMPLETE ONLY WHEN:**\n- [ ] commit visible\n\n---\n\n## GLOSSARY\n- term\n\nEND OF DOCUMENT";

    private const FIX_PLAN = "# Fix Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## What we are fixing\n| # | Problem | How bad | Fixed in |\n|---|---|---|---|\n| 1 | FIX_ROW_TEXT | high | F1 |\n\n"
        . "## Fix Milestone 1: Close the gaps\n\n**Recommended support level: FULL** — start here.\n\n### F1.1 First fix\n\n**Spec:**\n- the first fix works\n\n#### Step 1 — Fix it\n\n**Goal:**\n- do the fix\n\n**Where:** parser.py\n\n"
        . "## GLOSSARY\n- term\n\nEND OF DOCUMENT";

    protected function setUp(): void
    {
        parent::setUp();

        PhaseProgress::create([
            'phase_number' => 1,
            'status' => 'pending_project',
            'mega_project_md' => self::BRIEF,
            'implementation_plan_md' => self::PLAN,
        ]);
    }

    private function gradingReply(int $score, string $feedback): string
    {
        return json_encode(['feedback_md' => $feedback, 'overall_score' => $score, 'passed' => $score >= 70, 'concept_scores' => [], 'security_insights' => '']);
    }

    /** The n-th model call returns the n-th reply (raw message content). */
    private function fakeReplies(string ...$replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['choices' => [['message' => ['content' => $reply]]]]);
        }
        Http::fake(['openrouter.ai/*' => $sequence]);
    }

    private function fakeGradings(array ...$gradings): void
    {
        $this->fakeReplies(...array_map(fn ($g) => $this->gradingReply($g[0], $g[1]), $gradings));
    }

    private function message(int $call, string $role = 'user'): string
    {
        $messages = Http::recorded()[$call][0]->data()['messages'];

        return collect($messages)->firstWhere('role', $role)['content'];
    }

    private function auditId(): string
    {
        return $this->getJson('/phases/1/audit-prompt')->json('audit_id');
    }

    private function report(string $auditId): string
    {
        return "# PROJECT AUDIT REPORT\nAudit ID: {$auditId}\nProject: Test Sentinel\n\n"
            . "## 1. Snapshot\nSNAPSHOT_TEXT\n\n## 2. Commands run\nCOMMANDS_TEXT\n\n"
            . "## 3. Milestone gates\n| Milestone | Gate | Verdict | Evidence |\n|---|---|---|---|\n| 1 | PARTIAL_GATE_ROW | PARTIAL | missing a test |\n\n"
            . "## 4. Hard constraints\n| Constraint | Check | Verdict |\n|---|---|---|\n| no eval | grep: empty | PASS |\n\n"
            . "## 5. Rubric\n| Category | Weight | Points | Reason |\n|---|---|---|---|\n| Core | 40 | 30 | RUBRIC_REASON |\nTotal: 74/100\n\n"
            . "## 6. Security and robustness findings\nSECURITY_TEXT\n\n## 7. Code quality and README\nQUALITY_TEXT\n\n"
            . "## 8. Strengths\nSTRENGTH_TEXT\n\n## 9. Defects and missing requirements\n1. DEFECT_ONE_TEXT at parser.py:23\n\n"
            . "## 10. Verdict\nSelf-assessed score: 74/100 — PASS\n\nEND OF REPORT";
    }

    // ------------------------------------------------------------------ attempts

    public function test_the_first_grading_is_recorded_as_attempt_one(): void
    {
        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE']);

        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())])->assertRedirect('/phases/1');

        $attempts = PhaseProgress::first()->project_attempts;
        $this->assertCount(1, $attempts);
        $this->assertSame(1, $attempts[0]['n']);
        $this->assertSame(74, $attempts[0]['score']);
        $this->assertSame('audit', $attempts[0]['kind']);
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $attempts[0]['defects']);
        $this->assertStringNotContainsString('STRENGTH_TEXT', $attempts[0]['defects']);
        $this->assertStringNotContainsString('RESUBMISSION', $this->message(0));
    }

    public function test_a_resubmission_tells_the_grader_what_was_wrong_before(): void
    {
        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE'], [81, '# Evaluation FEEDBACK_TWO']);
        $report = $this->report($this->auditId());

        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/submit', ['submission' => $report])->assertSessionHas('status', fn ($s) => str_contains($s, 'Attempt 2'));

        $second = $this->message(1);
        $this->assertStringContainsString('RESUBMISSION — this is attempt 2', $second);
        $this->assertStringContainsString('74/100', $second);
        $this->assertStringContainsString('FEEDBACK_ONE', $second);
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $second);

        $attempts = PhaseProgress::first()->project_attempts;
        $this->assertSame([1, 2], array_column($attempts, 'n'));
        $this->assertSame(81, PhaseProgress::first()->project_score);
    }

    public function test_a_project_graded_before_attempts_were_recorded_counts_as_attempt_one(): void
    {
        $report = $this->report($this->auditId());
        PhaseProgress::first()->update(['project_submission' => $report, 'project_eval_md' => '# Old FEEDBACK_OLD', 'project_score' => 74]);
        $this->assertNull(PhaseProgress::first()->project_attempts);

        $this->get('/phases/1')->assertOk()->assertSee('#1 · 74/100')->assertSee('Resubmission:', false)->assertSee('attempt 2');

        $this->fakeGradings([80, '# Evaluation FEEDBACK_NEW']);
        $this->post('/phases/1/submit', ['submission' => $report]);

        $this->assertStringContainsString('RESUBMISSION — this is attempt 2', $this->message(0));
        $this->assertStringContainsString('FEEDBACK_OLD', $this->message(0));
        $this->assertCount(2, PhaseProgress::first()->project_attempts);
    }

    public function test_only_the_last_ten_attempts_are_kept(): void
    {
        $old = array_map(fn ($n) => ['n' => $n, 'at' => '2026-11-01 10:00:00', 'score' => 50, 'passed' => false, 'kind' => 'code', 'failed_rows' => 0, 'feedback_md' => 'x', 'defects' => ''], range(1, 10));
        PhaseProgress::first()->update(['project_attempts' => $old]);
        $this->fakeGradings([60, '# Evaluation']);

        $this->post('/phases/1/submit', ['submission' => 'def run(): return 1  # plain code submission']);

        $attempts = PhaseProgress::first()->project_attempts;
        $this->assertCount(10, $attempts);
        $this->assertSame(2, $attempts[0]['n']);
        $this->assertSame(11, $attempts[9]['n']);
    }

    public function test_the_audit_prompt_lists_earlier_problems_only_on_a_resubmission(): void
    {
        $first = $this->getJson('/phases/1/audit-prompt')->json('prompt');
        $this->assertStringNotContainsString('RESUBMISSION CHECK', $first);
        $this->assertStringNotContainsString('{{', $first);

        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE']);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $again = $this->getJson('/phases/1/audit-prompt')->json('prompt');
        $this->assertStringContainsString('RESUBMISSION CHECK — this is attempt 2', $again);
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $again);
        $this->assertStringContainsString('FIXED, STILL OPEN or REGRESSED', $again);
        $this->assertStringContainsString('mark it OUT OF SCOPE', $again);   // earlier problems outside the brief are not graded
    }

    // ------------------------------------------------------------------ the fix plan, written by the app

    public function test_the_fix_plan_needs_an_evaluation_first(): void
    {
        Http::fake();

        $this->post('/phases/1/fix-plan')->assertRedirect('/phases/1#fixplan')->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertNull(PhaseProgress::first()->fix_plan_md);
    }

    public function test_the_app_writes_the_fix_plan_from_the_findings_and_stores_it(): void
    {
        $this->fakeReplies($this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), self::FIX_PLAN);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')
            ->assertRedirect('/phases/1#fixplan')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'attempt 2') && str_contains($s, 'attempt 1 evaluation'));

        $progress = PhaseProgress::first();
        $this->assertSame(self::FIX_PLAN, $progress->fix_plan_md);
        $this->assertSame(1, $progress->fix_plan_attempt);

        $request = $this->message(1);
        $this->assertStringContainsString('MODE: GENERATE_FIX_PLAN', $request);
        $this->assertStringContainsString('Learner mode: ON', $request);          // the original plan is in Learner mode, so is this one
        $this->assertStringContainsString('This Fix Plan is for attempt 2', $request);
        $this->assertStringContainsString('74/100', $request);
        $this->assertStringContainsString('FEEDBACK_ONE', $request);               // grader feedback
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $request);            // audit findings: defects
        $this->assertStringContainsString('PARTIAL_GATE_ROW', $request);           // audit findings: gate table
        $this->assertStringContainsString('RUBRIC_REASON', $request);              // audit findings: rubric
        $this->assertStringContainsString('KEEP_THESE_NAMES', $request);           // architecture lock
        $this->assertStringContainsString('Core Functionality [40%]', $request);   // brief
        foreach (['SNAPSHOT_TEXT', 'COMMANDS_TEXT', 'STRENGTH_TEXT', 'QUALITY_TEXT', 'STEP_ONLY_TEXT'] as $noise) {
            $this->assertStringNotContainsString($noise, $request);
        }
        $this->assertStringNotContainsString('{{', $request);

        // embedded text keeps its structure but never looks like one of the request's own sections
        $this->assertStringContainsString('#### 3. Milestone gates', $request);
        $this->assertSame(5, preg_match_all('/^## /m', $request), 'only ARCHITECTURE, GRADER FEEDBACK, AUDIT FINDINGS, PROJECT BRIEF and YOUR TASK are top-level');

        $system = $this->message(1, 'system');
        $this->assertStringContainsString('MODE 5: GENERATE FIX PLAN', $system);
        $this->assertStringContainsString('LEARNER MODE (applies to MODE 3, MODE 4 and MODE 5)', $system);
    }

    public function test_a_cut_off_fix_plan_is_continued_until_it_is_complete(): void
    {
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), "# Fix Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## Fix Milestone 1: Close the g", "aps\n\nEND OF DOCUMENT");
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        Http::assertSentCount(3);
        $this->assertStringContainsString('YOUR REPLY SO FAR', $this->message(2));
        $this->assertStringContainsString('Close the gaps', PhaseProgress::first()->fix_plan_md);
        $this->assertStringEndsWith('END OF DOCUMENT', PhaseProgress::first()->fix_plan_md);
    }

    public function test_a_plan_that_was_not_in_learner_mode_gets_a_standard_request(): void
    {
        PhaseProgress::first()->update(['implementation_plan_md' => "# Implementation Plan: Test Sentinel\n\n## Milestone 1: Setup\n- [ ] x"]);
        $this->fakeReplies($this->gradingReply(55, '# Evaluation'), "# Fix Plan\n\nEND OF DOCUMENT");
        $this->post('/phases/1/submit', ['submission' => 'def run(): return 1  # plain code submission']);

        $this->post('/phases/1/fix-plan');

        $request = $this->message(1);
        $this->assertStringNotContainsString('Learner mode: ON', $request);
        $this->assertStringContainsString('graded from pasted code', $request);   // no audit report: the grader feedback is the list of findings
        $this->assertStringContainsString('NOT PASSED YET', $request);
    }

    public function test_every_attempt_can_get_its_own_fix_plan_and_the_old_one_goes_stale(): void
    {
        $report = $this->report($this->auditId());
        $this->fakeReplies(
            $this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), self::FIX_PLAN,
            $this->gradingReply(81, '# Evaluation FEEDBACK_TWO'), str_replace('FIX_ROW_TEXT', 'SECOND_ROUND_ROW', self::FIX_PLAN),
        );

        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/fix-plan');
        $this->post('/phases/1/submit', ['submission' => $report]);

        $this->get('/phases/1')->assertOk()
            ->assertSee('This plan was made from attempt 1')
            ->assertSee('Create implementation plan for attempt 3');

        $this->post('/phases/1/fix-plan');

        $progress = PhaseProgress::first();
        $this->assertSame(2, $progress->fix_plan_attempt);
        $this->assertStringContainsString('SECOND_ROUND_ROW', $progress->fix_plan_md);

        $request = $this->message(3);
        $this->assertStringContainsString('This Fix Plan is for attempt 3', $request);
        $this->assertStringContainsString('EARLIER ATTEMPTS', $request);
        $this->assertStringContainsString('FEEDBACK_ONE', $request);   // what attempt 1 found, as context
    }

    public function test_the_page_has_a_fix_plan_tab_only_after_a_grading_and_shows_the_plan(): void
    {
        $this->get('/phases/1')->assertOk()
            ->assertDontSee('data-tab="fixplan"', false)
            ->assertDontSee('Resubmission:', false);

        $this->fakeReplies($this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), self::FIX_PLAN);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->get('/phases/1')->assertOk()
            ->assertSee('data-tab="fixplan"', false)
            ->assertSee('action="/phases/1/fix-plan"', false)
            ->assertSee('Create implementation plan for attempt 2')
            ->assertSee('#1 · 74/100')
            ->assertSee('Resubmission:', false)
            ->assertDontSee('FIX_ROW_TEXT');

        $this->post('/phases/1/fix-plan');

        $this->get('/phases/1')->assertOk()
            ->assertSee('FIX_ROW_TEXT')
            ->assertSee('Fix Milestone 1: Close the gaps')
            ->assertSee('/phases/1/export/fixplan/md', false);
    }

    public function test_the_fix_plan_can_be_exported_like_the_other_documents(): void
    {
        $this->get('/phases/1/export/fixplan/md')->assertNotFound();

        PhaseProgress::first()->update(['fix_plan_md' => self::FIX_PLAN, 'fix_plan_attempt' => 1]);

        $this->get('/phases/1/export/fixplan/md')->assertOk()->assertSee('FIX_ROW_TEXT');
    }

    // ------------------------------------------------------------------ helpers and prompts

    public function test_report_sections_come_out_in_the_order_asked_without_the_end_marker(): void
    {
        $sections = PhaseService::reportSections($this->report('AUDIT-P1-00000000'), [10, 9]);

        $this->assertStringStartsWith('## 10.', $sections);
        $this->assertStringContainsString('## 9. Defects', $sections);
        $this->assertStringNotContainsString('END OF REPORT', $sections);
        $this->assertStringNotContainsString('STRENGTH_TEXT', $sections);
    }

    public function test_the_grader_and_fix_plan_rules_live_in_the_one_project_prompt(): void
    {
        $system = File::get(resource_path('prompts/mega-project.md'));

        $this->assertStringContainsString('RESUBMISSIONS:', $system);
        $this->assertStringContainsString('Resubmission check', $system);
        $this->assertStringContainsString('MODE 5: GENERATE FIX PLAN', $system);
        $this->assertStringContainsString('Skip everything marked "Beyond the plan"', $system);   // only graded findings get a fix
        $this->assertStringContainsString('No reference solutions and no invented code', $system);   // the app cannot see the learner's code
        $this->assertStringContainsString('Fix Milestone N: Re-audit and resubmit', $system);
    }
}
