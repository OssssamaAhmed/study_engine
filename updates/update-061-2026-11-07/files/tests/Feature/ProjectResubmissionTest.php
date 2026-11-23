<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Services\PhaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** After a graded submission: a fix-plan prompt for what was weak, and resubmissions that know the earlier attempts. */
class ProjectResubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const BRIEF = "# Mega Portfolio Project: Test Sentinel\n\n## 5. Evaluation Rubric & Pass Criteria\nCore Functionality [40%]. Passing score: 70/100.";

    private const PLAN = "# Implementation Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## Architecture Lock\nKEEP_THESE_NAMES: LogParser, ThreatSentinel.\n\n"
        . "## Milestone 1: Setup\n\n#### Step 1 — Do a thing\n\n**Goal:**\n- do STEP_ONLY_TEXT\n\n"
        . "**FINAL ACCEPTANCE CRITERIA**\n- [ ] repo exists\n\n**COMPLETE ONLY WHEN:**\n- [ ] commit visible\n\n---\n\n## GLOSSARY\n- term\n\nEND OF DOCUMENT";

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

    /** The n-th model call returns the n-th (score, feedback) pair. */
    private function fakeGradings(array ...$gradings): void
    {
        $sequence = Http::sequence();
        foreach ($gradings as [$score, $feedback]) {
            $json = json_encode(['feedback_md' => $feedback, 'overall_score' => $score, 'passed' => $score >= 70, 'concept_scores' => [], 'security_insights' => '']);
            $sequence->push(['choices' => [['message' => ['content' => $json]]]]);
        }
        Http::fake(['openrouter.ai/*' => $sequence]);
    }

    private function userMessage(int $call): string
    {
        $messages = Http::recorded()[$call][0]->data()['messages'];

        return collect($messages)->firstWhere('role', 'user')['content'];
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
        $this->assertStringNotContainsString('RESUBMISSION', $this->userMessage(0));
    }

    public function test_a_resubmission_tells_the_grader_what_was_wrong_before(): void
    {
        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE'], [81, '# Evaluation FEEDBACK_TWO']);
        $report = $this->report($this->auditId());

        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/submit', ['submission' => $report])->assertSessionHas('status', fn ($s) => str_contains($s, 'Attempt 2'));

        $second = $this->userMessage(1);
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

        $this->assertStringContainsString('RESUBMISSION — this is attempt 2', $this->userMessage(0));
        $this->assertStringContainsString('FEEDBACK_OLD', $this->userMessage(0));
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

    public function test_the_fix_plan_prompt_needs_an_evaluation_first(): void
    {
        $this->getJson('/phases/1/fix-plan-prompt')->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_the_fix_plan_prompt_carries_the_findings_the_format_and_the_names_to_keep(): void
    {
        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE']);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $res = $this->getJson('/phases/1/fix-plan-prompt')->assertOk();
        $prompt = $res->json('prompt');

        $this->assertSame(1, $res->json('attempt'));
        $this->assertStringContainsString('74/100', $prompt);
        $this->assertStringContainsString('FEEDBACK_ONE', $prompt);                // grader feedback
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $prompt);             // audit findings: defects
        $this->assertStringContainsString('PARTIAL_GATE_ROW', $prompt);            // audit findings: gate table
        $this->assertStringContainsString('RUBRIC_REASON', $prompt);               // audit findings: rubric
        $this->assertStringContainsString('KEEP_THESE_NAMES', $prompt);            // architecture lock
        $this->assertStringContainsString('Core Functionality [40%]', $prompt);    // brief
        $this->assertStringContainsString('ONE bullet per line of code', $prompt); // the Learner plan format, single-sourced
        $this->assertStringContainsString('Fix Milestone', $prompt);
        $this->assertStringContainsString('END OF DOCUMENT', $prompt);
        $this->assertStringContainsString('Re-audit and resubmit', $prompt);
        $this->assertStringContainsString('skip anything marked "Beyond the plan"', $prompt);   // only graded findings get a fix
        foreach (['SNAPSHOT_TEXT', 'COMMANDS_TEXT', 'STRENGTH_TEXT', 'QUALITY_TEXT', 'STEP_ONLY_TEXT'] as $noise) {
            $this->assertStringNotContainsString($noise, $prompt);
        }
        $this->assertStringNotContainsString('{{', $prompt);
    }

    public function test_the_fix_plan_prompt_for_a_code_submission_uses_the_grader_feedback(): void
    {
        $this->fakeGradings([55, '# Evaluation FEEDBACK_CODE']);
        $this->post('/phases/1/submit', ['submission' => 'def run(): return 1  # plain code submission']);

        $prompt = $this->getJson('/phases/1/fix-plan-prompt')->json('prompt');

        $this->assertStringContainsString('FEEDBACK_CODE', $prompt);
        $this->assertStringContainsString('graded from pasted code', $prompt);
        $this->assertStringContainsString('NOT PASSED YET', $prompt);
    }

    public function test_report_sections_come_out_in_the_order_asked_without_the_end_marker(): void
    {
        $sections = PhaseService::reportSections($this->report('AUDIT-P1-00000000'), [10, 9]);

        $this->assertStringStartsWith('## 10.', $sections);
        $this->assertStringContainsString('## 9. Defects', $sections);
        $this->assertStringNotContainsString('END OF REPORT', $sections);
        $this->assertStringNotContainsString('STRENGTH_TEXT', $sections);
    }

    public function test_the_page_shows_the_fix_plan_box_and_the_attempts_only_after_a_grading(): void
    {
        $this->get('/phases/1')->assertOk()->assertDontSee('id="fix-btn"', false)->assertDontSee('Resubmission:', false);

        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE']);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->get('/phases/1')->assertOk()
            ->assertSee('Generate Fix Plan Prompt')
            ->assertSee('id="fix-btn"', false)
            ->assertSee('#1 · 74/100')
            ->assertSee('Resubmission:', false);
    }

    public function test_the_grader_prompt_has_the_resubmission_rules(): void
    {
        $system = File::get(resource_path('prompts/mega-project.md'));

        $this->assertStringContainsString('RESUBMISSIONS:', $system);
        $this->assertStringContainsString('Resubmission check', $system);
    }
}
