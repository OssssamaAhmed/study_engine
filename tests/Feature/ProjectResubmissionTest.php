<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Services\FixPlanWriter;
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

    /** A milestone reply that has what the checks require: sections, specs, steps with Goal bullets + whys + Where, checks. */
    private function milestoneReply(int $n, string $marker = 'STEP_GOAL', int $sections = 1): string
    {
        $body = '';
        for ($k = 1; $k <= $sections; $k++) {
            $body .= "### F{$n}.{$k} ".($k === 1 ? 'First fix' : "Fix number {$k}")."\n\n**Spec:**\n- the first fix works\n\n"
                . "#### Step 9 — Fix it\n\n**Goal:**\n- do the {$marker} fix *→ because it was broken*\n- check it *→ so you know*\n\n**Where:** `parser.py`, inside `run`\n\n"
                . "**✅ Checks for F{$n}.{$k}:**\n- `python main.py` → `Scan finished.`\n\n";
        }

        return "## Fix Milestone {$n}: whatever the model calls it\n\n**Recommended support level:** SPEC ONLY — wrong form\n\n".$body
            . "*Commit message:* `Fix {$n}`\n\nEND OF DOCUMENT";
    }

    private function outlineReply(string $row = 'FIX_ROW_TEXT', string $extra = ''): string
    {
        return "FINDINGS\n1 | {$row} | high | Core Functionality [40%]\n2 | SECOND_ROW | low | Passing score: 70/100\n{$extra}\n"
            . "MILESTONES\n1 | Close the gaps | FULL | the first fixes touch the core path | 1\n2 | Tidy the tests | GUIDED | you know tests by now | 2\n\nEND OF DOCUMENT";
    }

    private function glossaryReply(): string
    {
        return "- **assertEqual** — fails the test when two values differ. Urdu mein: test khud check karta hai.\n\nEND OF DOCUMENT";
    }

    /** The four replies a two-milestone Learner-mode plan costs: outline, milestone 1, milestone 2, glossary. */
    private function stagedPlan(string $row = 'FIX_ROW_TEXT', string $marker = 'STEP_GOAL'): array
    {
        return [$this->outlineReply($row), $this->milestoneReply(1, $marker), $this->milestoneReply(2, $marker), $this->glossaryReply()];
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

    // ------------------------------------------------------------------ the plan for each attempt, written by the app

    public function test_a_plan_for_the_next_attempt_needs_an_evaluation_first(): void
    {
        Http::fake();

        $this->post('/phases/1/fix-plan')->assertRedirect('/phases/1')->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertNull(PhaseProgress::first()->fix_plans);
    }

    public function test_the_app_writes_the_plan_for_attempt_two_in_stages_and_stores_it(): void
    {
        $this->fakeReplies($this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), ...$this->stagedPlan());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')
            ->assertRedirect('/phases/1#attempt-2')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'attempt 2') && str_contains($s, 'attempt 1 evaluation'));

        Http::assertSentCount(5);   // grading + outline + two milestones + glossary

        $plans = PhaseProgress::first()->fix_plans;
        $this->assertSame([2], array_keys($plans));
        $this->assertSame(1, $plans[2]['from']);
        $plan = $plans[2]['md'];

        // the whole plan, assembled in the usual layout
        $this->assertStringStartsWith("# Fix Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## What we are fixing", $plan);
        $this->assertStringContainsString('| 1 | FIX_ROW_TEXT | high | "Core Functionality [40%]" | F1 |', $plan);   // the table is built by the app
        $this->assertStringContainsString('| 2 | SECOND_ROW | low | "Passing score: 70/100" | F2 |', $plan);
        $this->assertStringContainsString('Where the brief asks for it', $plan);
        $this->assertStringContainsString('## Fix Milestone 1: Close the gaps', $plan);                 // the outline's name, not the model's own heading
        $this->assertStringContainsString('## Fix Milestone 2: Tidy the tests', $plan);
        $this->assertStringContainsString('## Fix Milestone 3: Re-audit and resubmit', $plan);          // written by the app, not the model
        $this->assertStringContainsString('**Recommended support level: FULL** — the first fixes touch the core path', $plan);   // the exact form the page reads
        $this->assertStringNotContainsString('wrong form', $plan);
        $this->assertStringContainsString('## GLOSSARY', $plan);
        $this->assertStringEndsWith('END OF DOCUMENT', $plan);
        $this->assertSame(1, substr_count($plan, 'END OF DOCUMENT'));
        $this->assertSame(1, substr_count($plan, '**Learner mode: ON**'));

        // steps are numbered by the app, continuing across milestones, whatever numbers the model wrote
        preg_match_all('/^#### Step (\d+)/m', $plan, $steps);
        $this->assertSame(['1', '2', '3', '4'], $steps[1]);

        // the outline request carries everything the findings give; the milestone request carries the layout example
        $outline = $this->message(1);
        $this->assertStringContainsString('MODE: GENERATE_FIX_PLAN', $outline);
        $this->assertStringContainsString('PART: OUTLINE', $outline);
        $this->assertStringContainsString('Learner mode: ON', $outline);
        $this->assertStringContainsString('This Fix Plan is for attempt 2', $outline);
        $this->assertStringContainsString('74/100', $outline);
        $this->assertStringContainsString('FEEDBACK_ONE', $outline);               // grader feedback
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $outline);            // audit findings: defects
        $this->assertStringContainsString('PARTIAL_GATE_ROW', $outline);           // audit findings: gate table
        $this->assertStringContainsString('RUBRIC_REASON', $outline);              // audit findings: rubric
        $this->assertStringContainsString('KEEP_THESE_NAMES', $outline);           // architecture lock
        $this->assertStringContainsString('Core Functionality [40%]', $outline);   // brief
        foreach (['SNAPSHOT_TEXT', 'COMMANDS_TEXT', 'STRENGTH_TEXT', 'QUALITY_TEXT', 'STEP_ONLY_TEXT'] as $noise) {
            $this->assertStringNotContainsString($noise, $outline);
        }
        $this->assertStringNotContainsString('{{', $outline);

        // embedded text keeps its structure but never looks like one of the request's own sections
        $this->assertStringContainsString('#### 3. Milestone gates', $outline);
        $this->assertSame(8, preg_match_all('/^## /m', $outline), 'top-level: ARCHITECTURE, NAMES, COMMANDS, REFERENCE CODE, GRADER FEEDBACK, AUDIT FINDINGS, PROJECT BRIEF, YOUR TASK (the outline is plain FINDINGS/MILESTONES lines — the app writes the heading)');

        $milestone = $this->message(2);
        $this->assertStringContainsString('PART: MILESTONE 1 of 2', $milestone);
        $this->assertStringContainsString('LAYOUT EXAMPLE', $milestone);
        $this->assertStringContainsString('#### Step', $milestone);
        $this->assertStringContainsString('1 | Close the gaps | FULL', $milestone);   // the decided outline is repeated for every milestone

        $system = $this->message(1, 'system');
        $this->assertStringContainsString('MODE 4: GENERATE FIX PLAN', $system);
        $this->assertStringContainsString('LEARNER MODE (applies to MODE 3 and MODE 4)', $system);
        $this->assertStringNotContainsString('keleton', str_replace('code skeletons', '', $system));   // the skeleton mode is gone
    }

    public function test_a_finding_the_model_marks_none_is_left_out_and_listed_as_not_planned(): void
    {
        $outline = $this->outlineReply('FIX_ROW_TEXT', '3 | THIRD_ROW_NOT_IN_BRIEF | medium | NONE');
        $outline = str_replace("2 | Tidy the tests | GUIDED | you know tests by now | 2", "2 | Tidy the tests | GUIDED | you know tests by now | 2, 3", $outline);
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $outline, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringContainsString('> **Not planned**', $plan);
        $this->assertStringContainsString('> - THIRD_ROW_NOT_IN_BRIEF', $plan);
        $this->assertStringNotContainsString('| 3 | THIRD_ROW_NOT_IN_BRIEF', $plan);   // not a row of the table, so nothing is planned for it
        $this->assertStringContainsString('| 2 | SECOND_ROW', $plan);                  // the others keep sequential numbers
        $this->assertStringNotContainsString('THIRD_ROW_NOT_IN_BRIEF', $this->message(2));   // and the milestones are not told to fix it
        $this->assertCount(5, Http::recorded());                                           // grading, outline, 2 milestones, glossary: NONE needed no second try
    }

    public function test_a_quote_that_is_not_in_the_brief_is_sent_back_before_the_finding_is_dropped(): void
    {
        $both = fn (string $outline): string => str_replace('| you know tests by now | 2', '| you know tests by now | 2, 3', $outline);
        $guess = $both($this->outlineReply('FIX_ROW_TEXT', '3 | THIRD_ROW_PARAPHRASED | medium | something close to what the brief says'));
        $fixed = $both($this->outlineReply('FIX_ROW_TEXT', '3 | THIRD_ROW_PARAPHRASED | medium | Passing score: 70/100'));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $guess, $fixed, $this->milestoneReply(1), $this->milestoneReply(2, 'STEP_GOAL', 2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $this->assertStringContainsString('finding 3: «something close to what the brief says» is not in the brief', $this->message(2));   // told which one, and how to answer
        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringContainsString('| 3 | THIRD_ROW_PARAPHRASED | medium | "Passing score: 70/100" |', $plan);   // the corrected quote kept it
        $this->assertStringNotContainsString('Not planned', $plan);
    }

    public function test_a_quote_still_not_in_the_brief_on_the_last_try_is_dropped_and_the_rest_is_kept(): void
    {
        $guess = $this->outlineReply('FIX_ROW_TEXT', '3 | THIRD_ROW_PARAPHRASED | medium | something close to what the brief says');
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $guess, $guess, $guess, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringContainsString('> - THIRD_ROW_PARAPHRASED', $plan);
        $this->assertStringContainsString('| 1 | FIX_ROW_TEXT', $plan);
    }

    public function test_a_finding_that_only_says_where_and_not_what_is_sent_back(): void
    {
        $bare = str_replace('1 | FIX_ROW_TEXT |', '1 | `detector.py:15-19` |', $this->outlineReply());
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $bare, $this->outlineReply(), $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $this->assertStringContainsString('says only where, not what is wrong', $this->message(2));
        $this->assertStringContainsString('| 1 | FIX_ROW_TEXT |', PhaseProgress::first()->fix_plans[2]['md']);
    }

    public function test_an_outline_where_no_finding_has_a_real_quote_is_rejected(): void
    {
        $invented = "FINDINGS\n1 | FIX_ROW_TEXT | high | words that are nowhere in the brief at all\n\nMILESTONES\n1 | Close the gaps | FULL | why | 1\n\nEND OF DOCUMENT";
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $invented, $invented, $invented);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('error', fn ($e) => str_contains($e, 'Nothing was saved'));

        $this->assertNull(PhaseProgress::first()->fix_plans[2] ?? null);   // nothing is saved when no finding can be tied to the brief
    }

    public function test_a_milestone_must_have_one_section_per_finding_and_nothing_more(): void
    {
        $twoSections = $this->milestoneReply(1)."\n\n".str_replace('F1.1', 'F1.2', $this->milestoneReply(1));
        $this->assertSame([], FixPlanWriter::milestoneProblems($this->milestoneReply(1), 1, 1));
        $this->assertNotSame([], FixPlanWriter::milestoneProblems($this->milestoneReply(1), 1, 2));   // fewer than the findings it fixes
        $problems = FixPlanWriter::milestoneProblems(FixPlanWriter::normalizeMilestone($twoSections, 1, ['name' => 'x', 'level' => 'FULL', 'why' => 'y']), 1, 1);
        $this->assertStringContainsString('fixes exactly 1 finding(s)', implode(' ', $problems));          // extra sections = extra features
    }

    public function test_a_goal_bullet_that_thinks_aloud_is_rejected(): void
    {
        $aloud = str_replace('do the STEP_GOAL fix', 'also keep the match in the key? The brief says to deduplicate', $this->milestoneReply(1));
        $this->assertStringContainsString('think aloud', implode(' ', FixPlanWriter::milestoneProblems($aloud, 1)));
        $fine = str_replace('do the STEP_GOAL fix', 'find the `if x?` marker and keep it', $this->milestoneReply(1));
        $this->assertSame([], FixPlanWriter::milestoneProblems($fine, 1));   // a question mark inside code is code, not a question
    }

    public function test_a_milestone_keeps_no_glossary_or_intro_of_its_own_and_any_commit_line_form_is_fixed(): void
    {
        $messy = str_replace("### F1.1 First fix", "AI Security Engineers need clear errors. Intro paragraph that is not part of the layout.\n\n### F1.1 First fix", $this->milestoneReply(1));
        $messy = str_replace("*Commit message:* `Fix 1`\n\nEND OF DOCUMENT", "*Commit message: Fix the first thing*\n\n## GLOSSARY\n- **x** — a stray glossary\n\nEND OF DOCUMENT", $messy);
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $messy, $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringNotContainsString('Intro paragraph', $plan);
        $this->assertStringNotContainsString('a stray glossary', $plan);
        $this->assertSame(1, substr_count($plan, '## GLOSSARY'));
        $this->assertStringContainsString('*Commit message:* `Fix the first thing`', $plan);
    }

    public function test_a_milestone_that_has_no_steps_is_rejected_and_asked_again(): void
    {
        $noSteps = "## Fix Milestone 1: Close the gaps\n\n### F1.1 First fix\n\n**Spec:**\n- the first fix works\n\n**✅ Checks for F1.1:**\n- `python main.py` → `OK`\n\nEND OF DOCUMENT";
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $noSteps, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        Http::assertSentCount(6);   // grading + outline + milestone 1 twice + milestone 2 + glossary
        $retry = $this->message(3);
        $this->assertStringContainsString('YOUR PREVIOUS REPLY WAS REJECTED', $retry);
        $this->assertStringContainsString('step blocks are missing', $retry);
        $this->assertStringContainsString('STEP_GOAL', PhaseProgress::first()->fix_plans[2]['md']);   // the good second reply was kept
    }

    public function test_a_plan_the_model_cannot_structure_is_never_saved_and_the_old_plan_stays(): void
    {
        $noSteps = "## Fix Milestone 1: Close the gaps\n\n### F1.1 First fix\n\n**Spec:**\n- works\n\n**✅ Checks for F1.1:**\n- `x` → `y`\n\nEND OF DOCUMENT";
        PhaseProgress::first()->update(['fix_plans' => [2 => ['md' => 'OLD_PLAN_TEXT', 'from' => 1, 'at' => '2026-11-08 10:00:00']]]);
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $noSteps, $noSteps, $noSteps);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')
            ->assertRedirect('/phases/1')
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'step-by-step format') && str_contains($e, 'Nothing was saved'));

        $this->assertSame('OLD_PLAN_TEXT', PhaseProgress::first()->fix_plans[2]['md']);
    }

    public function test_goal_bullets_without_their_whys_are_rejected(): void
    {
        $noWhys = str_replace([' *→ because it was broken*', ' *→ so you know*'], '', $this->milestoneReply(1));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $noWhys, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        $this->assertStringContainsString('must end with its why', $this->message(3));
    }

    public function test_a_reference_solution_the_model_adds_is_dropped(): void
    {
        $withSolution = str_replace("*Commit message:*", "#### Reference solution — Milestone 1\n\n```python\nINVENTED_CODE = 1\n```\n\n*Commit message:*", $this->milestoneReply(1));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $withSolution, $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringNotContainsString('INVENTED_CODE', $plan);
        $this->assertStringContainsString('Commit message', $plan);
    }

    public function test_a_failing_glossary_does_not_fail_the_plan(): void
    {
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $this->milestoneReply(1), $this->milestoneReply(2), 'no bullets here', 'still none', 'nothing');
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringNotContainsString('## GLOSSARY', $plan);
        $this->assertStringEndsWith('END OF DOCUMENT', $plan);
        $this->assertStringContainsString('Re-audit and resubmit', $plan);
    }

    public function test_a_milestone_reply_that_is_cut_off_is_continued_until_it_is_complete(): void
    {
        $milestone = $this->milestoneReply(1);
        $cut = strpos($milestone, 'fix *→ because');
        $this->fakeReplies(
            $this->gradingReply(74, '# Evaluation'), $this->outlineReply(),
            substr($milestone, 0, $cut + 4), substr($milestone, $cut + 4),   // milestone 1 arrives in two pieces
            $this->milestoneReply(2), $this->glossaryReply(),
        );
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        Http::assertSentCount(6);
        $this->assertStringContainsString('YOUR REPLY SO FAR', $this->message(3));
        $this->assertStringContainsString('do the STEP_GOAL fix *→ because it was broken*', PhaseProgress::first()->fix_plans[2]['md']);
    }

    private const PLAN_WITH_SOLUTIONS = "# Implementation Plan: Test Sentinel\n\n**Learner mode: ON**\n\n## Architecture Lock\nKEEP_THESE_NAMES.\n\n## Milestone 2: Parser\n\n"
        . "**✅ Checks for M2.2:**\n- `python -c \"from parser import LogParser; print(LogParser.total_loaded)\"` → `0`\n- `python -m unittest discover -s tests -t . -v` → `OK`\n\n"
        . "**FINAL ACCEPTANCE CRITERIA**\n- [ ] works\n\n#### Reference solution — Milestone 2\n\n`parser.py`:\n```python\nclass LogParser:\n    total_loaded = 0\n\n    def __init__(self, path):\n        self.path = path\n\n    def load_json(self):\n        return []\n```\n\n"
        . "`detector.py`:\n```python\nclass ThreatSentinel:\n    def analyze(self, logs):\n        return []\n```\n\n## GLOSSARY\n- term\n\nEND OF DOCUMENT";

    public function test_the_real_names_and_commands_of_the_project_are_collected_from_the_original_plan(): void
    {
        $names = PhaseService::planSignatures(self::PLAN_WITH_SOLUTIONS);

        $this->assertStringContainsString('`parser.py — class LogParser`', $names);
        $this->assertStringContainsString('`parser.py — def load_json(self)`', $names);
        $this->assertStringContainsString('`detector.py — def analyze(self, logs)`', $names);
        $this->assertStringNotContainsString('total_loaded', $names);   // a variable is not a signature
        $this->assertSame('', PhaseService::planSignatures(self::PLAN));   // a plan without reference solutions has none

        $commands = PhaseService::planCommands(self::PLAN_WITH_SOLUTIONS);
        $this->assertStringContainsString('LogParser.total_loaded', $commands);
        $this->assertStringContainsString('`python -m unittest discover -s tests -t . -v` → `OK`', $commands);
    }

    public function test_the_request_carries_the_projects_real_names_so_the_model_does_not_invent_methods(): void
    {
        PhaseProgress::first()->update(['implementation_plan_md' => self::PLAN_WITH_SOLUTIONS]);
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), ...$this->stagedPlan());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        $outline = $this->message(1);
        $this->assertStringContainsString('NAMES THAT EXIST IN THE PROJECT', $outline);
        $this->assertStringContainsString('`detector.py — def analyze(self, logs)`', $outline);
        $this->assertStringContainsString('COMMANDS THAT WORK IN THE PROJECT', $outline);
        $this->assertStringContainsString('`python -m unittest discover -s tests -t . -v` → `OK`', $outline);

        $milestone = $this->message(2);
        $this->assertStringContainsString('Use ONLY the names listed', $milestone);
        $this->assertStringContainsString('ONE way to do each thing', $milestone);
        $this->assertStringContainsString('Use no new library or tool', $milestone);
    }

    public function test_dots_as_placeholders_are_rejected_and_the_commit_line_is_repaired(): void
    {
        $dots = str_replace('`Scan finished.`', '`Ran 2 tests ... OK`', $this->milestoneReply(1));
        $badCommit = str_replace('*Commit message:* `Fix 2`', '*Commit message:* `Fix 2 and tidy*', $this->milestoneReply(2));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $dots, $this->milestoneReply(1), $badCommit, $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $this->assertStringContainsString('never use dots as a placeholder', $this->message(3));   // milestone 1 was sent back once
        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringNotContainsString('...', $plan);
        $this->assertStringContainsString('*Commit message:* `Fix 2 and tidy`', $plan);          // one balanced pair of backticks
    }

    public function test_the_reference_code_of_the_original_plan_is_collected(): void
    {
        $code = PhaseService::planReferenceCode(self::PLAN_WITH_SOLUTIONS);

        $this->assertStringContainsString('Reference code of Milestone 2:', $code);
        $this->assertStringContainsString('class LogParser:', $code);
        $this->assertStringContainsString('def analyze(self, logs):', $code);
        $this->assertStringNotContainsString('FINAL ACCEPTANCE', $code);   // only the code, not the milestone's gate list
        $this->assertSame('', PhaseService::planReferenceCode(self::PLAN));
    }

    public function test_every_part_gets_only_the_context_it_needs(): void
    {
        PhaseProgress::first()->update(['implementation_plan_md' => self::PLAN_WITH_SOLUTIONS]);
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), ...$this->stagedPlan());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        [$outline, $milestone, $glossary] = [$this->message(1), $this->message(2), $this->message(4)];

        // the outline decides what to fix: findings + brief, but no code
        $this->assertStringContainsString('Core Functionality [40%]', $outline);
        $this->assertStringNotContainsString('class LogParser:', $outline);

        // a milestone writes the steps: findings + the real code, but not the brief (a free model has a small context window)
        $this->assertStringContainsString('Reference code of Milestone 2:', $milestone);
        $this->assertStringContainsString('def analyze(self, logs):', $milestone);
        $this->assertStringNotContainsString('## 5. Evaluation Rubric & Pass Criteria', $milestone);   // the brief itself is not sent (only the quote the outline kept)
        $this->assertStringContainsString('DEFECT_ONE_TEXT', $milestone);

        // the glossary needs only the steps that come with its task
        $this->assertStringNotContainsString('DEFECT_ONE_TEXT', $glossary);
        $this->assertStringNotContainsString('class LogParser:', $glossary);
        $this->assertStringContainsString('PART: GLOSSARY', $glossary);
    }

    public function test_a_goal_bullet_with_a_whole_statement_is_rejected_but_a_condition_is_fine(): void
    {
        $condition = str_replace('do the STEP_GOAL fix *→', 'write an `if` whose condition is `not isinstance(text, str):` *→', $this->milestoneReply(1));
        $statement = str_replace('do the STEP_GOAL fix *→', 'insert `if not isinstance(text, str): return None` at the top *→', $this->milestoneReply(1));

        $this->assertSame([], FixPlanWriter::milestoneProblems($condition, 1));
        // two code spans with plain words between them must not be read as one statement
        $between = str_replace('do the STEP_GOAL fix *→', 'check `if not isinstance(data, list):` inside the `except (OSError, UnicodeDecodeError):` block *→', $this->milestoneReply(1));
        $this->assertSame([], FixPlanWriter::milestoneProblems($between, 1));
        $this->assertStringContainsString('whole code statement', implode(' ', FixPlanWriter::milestoneProblems($statement, 1)));
    }

    public function test_the_real_unittest_output_may_show_dots_but_a_placeholder_may_not(): void
    {
        $this->assertSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '`test_a (tests.test_x.T.test_a) ... ok`', $this->milestoneReply(1)), 1));
        $this->assertSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '9 lines ending `... ok`, then `Ran 13 tests`', $this->milestoneReply(1)), 1));   // the wording of the original plan
        $this->assertSame([], FixPlanWriter::milestoneProblems(str_replace('do the STEP_GOAL fix', 'find the `try...except json.JSONDecodeError` block', $this->milestoneReply(1)), 1));   // naming the construct
        $this->assertSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '`Error: Invalid json...` (a printed message that trails off)', $this->milestoneReply(1)), 1));   // dots after a word are text
        $this->assertNotSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '`Ran 2 tests ... OK`', $this->milestoneReply(1)), 1));
        $this->assertNotSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '`...`', $this->milestoneReply(1)), 1));
        $this->assertNotSame([], FixPlanWriter::milestoneProblems(str_replace('`Scan finished.`', '`parse(...)`', $this->milestoneReply(1)), 1));   // a stand-alone `...` is a placeholder
        $failLine = "\n**If a check fails:**\n- `ImportError: cannot import name ...` → check the function name in `util.py`\n";
        $this->assertSame([], FixPlanWriter::milestoneProblems(str_replace("\n**✅ Checks", $failLine."\n**✅ Checks", $this->milestoneReply(1)), 1));   // the changing part of an error message
    }

    public function test_a_new_library_is_rejected_and_the_model_is_told_why(): void
    {
        $withInstall = str_replace('check it *→ so you know*', 'run `pip install pycodestyle` *→ a style checker*', $this->milestoneReply(1));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $withInstall, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan')->assertSessionHas('status');

        $this->assertStringContainsString('new library or tool', $this->message(3));
        $this->assertStringNotContainsString('pycodestyle', PhaseProgress::first()->fix_plans[2]['md']);
    }

    public function test_alternatives_inside_a_goal_bullet_are_rejected(): void
    {
        $either = str_replace('do the STEP_GOAL fix *→', 'convert the value to text or either skip it *→', $this->milestoneReply(1));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), $this->outlineReply(), $either, $this->milestoneReply(1), $this->milestoneReply(2), $this->glossaryReply());
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        $this->assertStringContainsString('choose ONE way', $this->message(3));
    }

    public function test_a_milestone_keeps_one_commit_line_the_last_one(): void
    {
        $twoCommits = str_replace('*Commit message:* `Fix 1`', "*Commit message:* `first section`\n\n*Commit message:* `Fix 1`", $this->milestoneReply(1));
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), ...[$this->outlineReply(), $twoCommits, $this->milestoneReply(2), $this->glossaryReply()]);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');

        $plan = PhaseProgress::first()->fix_plans[2]['md'];
        $this->assertStringNotContainsString('first section', $plan);
        $this->assertSame(1, substr_count($plan, '*Commit message:* `Fix 1`'));
    }

    public function test_the_layout_example_follows_the_active_track(): void
    {
        $this->assertStringContainsString('scan_prompt', \App\Support\StudyMode::fixPlanExample('ai_security'));
        $this->assertStringContainsString('whereNumber', \App\Support\StudyMode::fixPlanExample('laravel_web'));
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

    public function test_every_attempt_keeps_its_own_plan(): void
    {
        $report = $this->report($this->auditId());
        $this->fakeReplies(...[
            $this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), ...$this->stagedPlan(),
            $this->gradingReply(81, '# Evaluation FEEDBACK_TWO'), ...$this->stagedPlan('SECOND_ROUND_ROW'),
        ]);

        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/fix-plan');
        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/fix-plan')->assertRedirect('/phases/1#attempt-3');

        $plans = PhaseProgress::first()->fix_plans;
        $this->assertSame([2, 3], array_keys($plans));                              // attempt 2's plan is still there
        $this->assertStringContainsString('FIX_ROW_TEXT', $plans[2]['md']);
        $this->assertStringContainsString('SECOND_ROUND_ROW', $plans[3]['md']);
        $this->assertSame(2, $plans[3]['from']);

        $request = $this->message(6);   // calls: 0 grading, 1-4 the plan for attempt 2, 5 grading, 6 the outline for attempt 3
        $this->assertStringContainsString('This Fix Plan is for attempt 3', $request);
        $this->assertStringContainsString('EARLIER ATTEMPTS', $request);
        $this->assertStringContainsString('FEEDBACK_ONE', $request);   // what attempt 1 found, as context
    }

    public function test_creating_the_plan_again_replaces_only_that_attempts_plan(): void
    {
        $this->fakeReplies($this->gradingReply(74, '# Evaluation'), ...$this->stagedPlan(), ...$this->stagedPlan('REDONE_ROW'));
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $this->post('/phases/1/fix-plan');
        $this->post('/phases/1/fix-plan');

        $plans = PhaseProgress::first()->fix_plans;
        $this->assertSame([2], array_keys($plans));
        $this->assertStringContainsString('REDONE_ROW', $plans[2]['md']);
    }

    // ------------------------------------------------------------------ the page

    public function test_the_page_has_a_document_dropdown_that_grows_with_every_attempt(): void
    {
        $this->get('/phases/1')->assertOk()
            ->assertSee('id="doc-select"', false)
            ->assertSee('value="brief"', false)
            ->assertSee('value="plan"', false)
            ->assertDontSee('value="attempt-2"', false)
            ->assertDontSee('data-tab=', false)                // the old tab buttons are gone
            ->assertDontSee('Resubmission:', false);

        $report = $this->report($this->auditId());
        $this->fakeReplies(...[$this->gradingReply(74, '# Evaluation FEEDBACK_ONE'), ...$this->stagedPlan(), $this->gradingReply(81, '# Evaluation FEEDBACK_TWO'), ...$this->stagedPlan()]);
        $this->post('/phases/1/submit', ['submission' => $report]);

        $this->get('/phases/1')->assertOk()
            ->assertSee('action="/phases/1/fix-plan"', false)
            ->assertSee('Create implementation plan for attempt 2')
            ->assertSee('#1 · 74/100')
            ->assertSee('Resubmission:', false)
            ->assertDontSee('FIX_ROW_TEXT')
            ->assertDontSee('value="attempt-2"', false);

        $this->post('/phases/1/fix-plan');

        $this->get('/phases/1')->assertOk()
            ->assertSee('value="attempt-2"', false)
            ->assertSee('🛠 Attempt 2')
            ->assertSee('FIX_ROW_TEXT')
            ->assertSee('Fix Milestone 1: Close the gaps')
            ->assertSee('Re-audit and resubmit')
            ->assertSee('Recreate implementation plan for attempt 2')
            ->assertSee('/phases/1/export/attempt-2/md', false);

        $this->post('/phases/1/submit', ['submission' => $report]);
        $this->post('/phases/1/fix-plan');

        $this->get('/phases/1')->assertOk()
            ->assertSee('value="attempt-2"', false)
            ->assertSee('value="attempt-3"', false)
            ->assertSee('Recreate implementation plan for attempt 3');
    }

    public function test_the_evaluation_summary_sits_under_the_phase_notes_above_everything_else(): void
    {
        $this->fakeGradings([74, '# Evaluation FEEDBACK_ONE']);
        $this->post('/phases/1/submit', ['submission' => $this->report($this->auditId())]);

        $html = $this->get('/phases/1')->assertOk()->getContent();

        $summary = strpos($html, 'Evaluation Score: 74/100');
        $this->assertNotFalse($summary);
        $this->assertLessThan(strpos($html, '1. Concept Mastery Check'), $summary);
        $this->assertLessThan(strpos($html, 'id="doc-select"'), $summary);
        if (($notes = strpos($html, 'Phase Notes &amp; Strategy')) !== false) {
            $this->assertGreaterThan($notes, $summary);
        }
        $this->assertSame(1, substr_count($html, 'Evaluation Score:'), 'the summary appears once, not again below the documents');
    }

    public function test_the_plain_english_skeleton_is_gone(): void
    {
        $this->post('/phases/1/skeleton')->assertNotFound();

        $this->get('/phases/1')->assertOk()->assertDontSee('Skeleton')->assertDontSee('skeleton');
        $this->get('/phases/1/export/skeleton/md')->assertNotFound();
    }

    public function test_an_attempts_plan_can_be_exported_like_the_other_documents(): void
    {
        $this->get('/phases/1/export/attempt-2/md')->assertNotFound();

        PhaseProgress::first()->update(['fix_plans' => [2 => ['md' => self::FIX_PLAN, 'from' => 1, 'at' => '2026-11-08 10:00:00']]]);

        $this->get('/phases/1/export/attempt-2/md')->assertOk()->assertSee('FIX_ROW_TEXT');
        $this->get('/phases/1/export/attempt-3/md')->assertNotFound();
    }

    public function test_the_stored_single_plan_of_the_first_version_moves_into_the_per_attempt_storage(): void
    {
        $migration = require database_path('migrations/2026_11_09_000001_store_fix_plans_per_attempt.php');

        // go back to the single-plan columns, put a plan there the old way, then run the migration forward again
        $migration->down();
        \DB::table('phase_progress')->update(['fix_plan_md' => 'OLD_SINGLE_PLAN', 'fix_plan_attempt' => 1]);
        $migration->up();

        $plans = PhaseProgress::first()->fix_plans;
        $this->assertSame([2], array_keys($plans));
        $this->assertSame('OLD_SINGLE_PLAN', $plans[2]['md']);
        $this->assertSame(1, $plans[2]['from']);
        $this->assertFalse(\Schema::hasColumn('phase_progress', 'fix_plan_md'));
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
        $this->assertStringContainsString('MODE 4: GENERATE FIX PLAN', $system);
        $this->assertStringContainsString('Skip everything marked "Beyond the plan"', $system);   // only graded findings get a fix
        $this->assertStringContainsString('No reference solutions and no invented code', $system);   // the app cannot see the learner's code
        $this->assertStringContainsString('Fix Milestone N: Re-audit and resubmit', $system);
    }
}
