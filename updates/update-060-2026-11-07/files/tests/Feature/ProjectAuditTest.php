<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Services\PhaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A finished project is audited by Claude Code; the app builds its prompt and grades the pasted report. */
class ProjectAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const BRIEF = "# Mega Portfolio Project: Test Sentinel\n\n## 5. Evaluation Rubric & Pass Criteria\nCore Functionality [40%]. Passing score: 70/100.";

    private const PLAN = "# Implementation Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## Before We Start\nIGNORE_ME_INTRO\n\n"
        . "## Milestone 1: Setup\n\n#### Step 1 — Do a thing\n\n**Goal:**\n- do STEP_ONLY_TEXT\n\n"
        . "**✅ Checks for M1:**\n- `git log --oneline` → 1 commit\n\n**If a check fails:**\n- NOPE_HINT\n\n"
        . "#### Reference solution — Milestone 1\n\n```python\nSECRET_REFERENCE_CODE = 1\n```\n\n"
        . "**FINAL ACCEPTANCE CRITERIA**\n- [ ] repo exists\n\n**COMPLETE ONLY WHEN:**\n- [ ] commit visible\n\n---\n\n"
        . "## GLOSSARY\n- GLOSSARY_TERM\n\nEND OF DOCUMENT";

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

    private function fakeEvaluation(int $score = 85, bool $passed = true): void
    {
        $json = json_encode([
            'feedback_md' => '# Project Evaluation Summary\nSolid work.',
            'overall_score' => $score,
            'passed' => $passed,
            'concept_scores' => [],
            'security_insights' => '',
        ]);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => $json]]]])]);
    }

    private function sentUserMessage(): string
    {
        $messages = Http::recorded()->first()[0]->data()['messages'];

        return collect($messages)->firstWhere('role', 'user')['content'];
    }

    private function auditId(): string
    {
        return $this->getJson('/phases/1/audit-prompt')->json('audit_id');
    }

    private function report(string $auditId, string $gateVerdict = 'PASS', bool $complete = true): string
    {
        return "# PROJECT AUDIT REPORT\nAudit ID: {$auditId}\nProject: Test Sentinel\nPhase: Phase 1\n\n"
            . "## 1. Snapshot\n3 commits\n\n## 2. Commands run\n| # | Command | Result |\n|---|---|---|\n| 1 | `python -m unittest` | OK |\n\n"
            . "## 3. Milestone gates\n| Milestone | Gate | Verdict (PASS / PARTIAL / FAIL / NOT RUN) | Evidence |\n|---|---|---|---|\n"
            . "| 1 | repo exists | {$gateVerdict} | words like FAIL in the evidence column are not verdicts |\n\n"
            . "## 4. Hard constraints\n| Constraint | Check | Verdict |\n|---|---|---|\n| no eval | grep: empty | PASS |\n\n"
            . "## 5. Rubric\n| Category | Weight | Points | Reason |\n|---|---|---|---|\n| Core | 40 | 36 | works |\nTotal: 85/100\n\n"
            . "## 6. Security and robustness findings\nNone found\n\n## 10. Verdict\nSelf-assessed score: 85/100 — PASS\n"
            . ($complete ? "\nEND OF REPORT" : '');
    }

    public function test_audit_prompt_carries_the_brief_the_gates_and_the_report_format(): void
    {
        $res = $this->getJson('/phases/1/audit-prompt')->assertOk();
        $prompt = $res->json('prompt');

        $this->assertMatchesRegularExpression('/^AUDIT-P1-[0-9A-F]{8}$/', $res->json('audit_id'));
        $this->assertStringContainsString('Audit ID: '.$res->json('audit_id'), $prompt);
        $this->assertStringContainsString('Project: Test Sentinel', $prompt);
        $this->assertStringContainsString('READ-ONLY', $prompt);
        $this->assertStringContainsString('END OF REPORT', $prompt);
        $this->assertStringContainsString('at the moment THAT milestone was finished', $prompt);   // point-in-time gates
        $this->assertStringContainsString('Core Functionality [40%]', $prompt);           // the brief, verbatim
        $this->assertStringContainsString('`git log --oneline` → 1 commit', $prompt);     // checks
        $this->assertStringContainsString('repo exists', $prompt);                        // acceptance criteria
        $this->assertStringContainsString('commit visible', $prompt);                     // COMPLETE ONLY WHEN
        $this->assertStringNotContainsString('{{', $prompt);
    }

    public function test_audit_prompt_leaves_out_build_help_from_the_plan(): void
    {
        $prompt = $this->getJson('/phases/1/audit-prompt')->json('prompt');

        foreach (['STEP_ONLY_TEXT', 'SECRET_REFERENCE_CODE', 'NOPE_HINT', 'IGNORE_ME_INTRO', 'GLOSSARY_TERM'] as $helpOnly) {
            $this->assertStringNotContainsString($helpOnly, $prompt);
        }
    }

    public function test_audit_prompt_needs_a_brief(): void
    {
        PhaseProgress::first()->update(['mega_project_md' => null]);

        $this->getJson('/phases/1/audit-prompt')->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_audit_id_changes_when_the_plan_changes(): void
    {
        $before = $this->auditId();
        PhaseProgress::first()->update(['implementation_plan_md' => str_replace('commit visible', 'tag visible', self::PLAN)]);

        $this->assertNotSame($before, $this->auditId());
    }

    public function test_gates_come_out_of_a_legacy_plan_too(): void
    {
        $gates = PhaseService::gatesFromPlan("## Milestone 2: Build\n\n### M2.1 Work\n- [ ] write code\n- [ ] Check it works: run `pytest` — all pass.\n\n**FINAL ACCEPTANCE CRITERIA**\n- [ ] it works\n\n**COMPLETE ONLY WHEN:**\n- [ ] tests pass\n");

        $this->assertStringContainsString('#### Milestone 2: Build', $gates);
        $this->assertStringContainsString('Check it works: run `pytest`', $gates);
        $this->assertStringContainsString('tests pass', $gates);
        $this->assertStringNotContainsString('write code', $gates);
    }

    public function test_only_fail_and_not_run_verdicts_in_the_gate_tables_are_counted(): void
    {
        $report = "## 3. Milestone gates\n| M | Gate | Verdict | Evidence |\n|---|---|---|---|\n"
            . "| 1 | a | **FAIL** | x |\n| 1 | b | ❌ FAIL — tests error | x |\n| 1 | c | NOT RUN | x |\n| 1 | d | PARTIAL | FAIL in evidence |\n| 1 | e | PASS | x |\n\n"
            . "## 4. Hard constraints\n| Constraint | Check | Verdict |\n|---|---|---|\n| no eval | grep | FAIL |\n\n## 5. Rubric\n| Category | Verdict |\n|---|---|\n| Core | FAIL |\n";

        $this->assertSame(4, PhaseService::auditFailedRows($report));
    }

    public function test_a_valid_report_is_graded_against_the_brief_and_the_gates_only(): void
    {
        $this->fakeEvaluation(85, true);

        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())])->assertRedirect('/phases/1');

        $message = $this->sentUserMessage();
        $this->assertStringContainsString('Submission type: AUDIT REPORT', $message);
        $this->assertStringContainsString('commit visible', $message);
        $this->assertStringNotContainsString('SECRET_REFERENCE_CODE', $message);
        $this->assertStringNotContainsString('STEP_ONLY_TEXT', $message);

        $progress = PhaseProgress::first();
        $this->assertSame(85, $progress->project_score);
        $this->assertSame('completed', $progress->status);
    }

    public function test_a_report_with_failed_gates_cannot_pass(): void
    {
        $this->fakeEvaluation(90, true);

        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId(), 'FAIL')]);

        $progress = PhaseProgress::first();
        $this->assertSame(69, $progress->project_score);
        $this->assertSame('pending_project', $progress->status);
        $this->assertStringContainsString('Score capped at 69', $progress->project_eval_md);
    }

    public function test_a_report_from_older_standards_is_rejected_without_calling_the_model(): void
    {
        Http::fake();

        $this->post('/phases/1/submit', ['submission' => $this->report('AUDIT-P1-DEADBEEF')])
            ->assertRedirect('/phases/1')
            ->assertSessionHas('error')
            ->assertSessionHasInput('submission');

        Http::assertNothingSent();
        $this->assertNull(PhaseProgress::first()->project_score);
    }

    public function test_an_unreadable_model_reply_shows_a_message_instead_of_crashing(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{ this is not json at all']]]])]);

        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())])
            ->assertRedirect('/phases/1')
            ->assertSessionHas('error')
            ->assertSessionHasInput('submission');

        $this->assertNull(PhaseProgress::first()->project_score);
    }

    public function test_a_report_without_an_audit_id_is_rejected(): void
    {
        Http::fake();
        $noId = preg_replace('/^Audit ID:.*\n/m', '',$this->report($this->auditId()));

        $this->post('/phases/1/submit', ['submission' => $noId])->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_a_cut_off_report_is_rejected(): void
    {
        Http::fake();

        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId(), 'PASS', false)])
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_a_report_pasted_inside_a_code_fence_is_still_accepted(): void
    {
        $this->fakeEvaluation(80, true);

        $this->post('/phases/1/submit', ['submission' => "```markdown\n".$this->report($this->auditId())."\n```"])
            ->assertRedirect('/phases/1')
            ->assertSessionMissing('error');

        $this->assertSame(80, PhaseProgress::first()->project_score);
    }

    public function test_plain_code_submissions_are_graded_as_before(): void
    {
        $this->fakeEvaluation(75, true);

        $this->post('/phases/1/submit', ['submission' => 'def run(): return 1  # my whole project in one file'])
            ->assertRedirect('/phases/1');

        $message = $this->sentUserMessage();
        $this->assertStringNotContainsString('AUDIT REPORT', $message);
        $this->assertStringContainsString('Implementation Plan (check the submission', $message);
        $this->assertSame(75, PhaseProgress::first()->project_score);
    }

    public function test_phase_page_offers_the_audit_prompt_button(): void
    {
        $this->get('/phases/1')->assertOk()
            ->assertSee('Generate Audit Prompt')
            ->assertSee('/audit-prompt', false);
    }
}
