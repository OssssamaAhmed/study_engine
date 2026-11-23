<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\PhaseProgress;
use App\Models\Topic;
use App\Support\StudyMode;
use Illuminate\Support\Facades\File;

class PhaseService
{
    public function __construct(
        protected RoadmapService $roadmap,
        protected LlmService $llm
    ) {}

    /**
     * Check if a phase can be marked as completed.
     * Evaluates concept coverage against roadmap topics AND checks if the Mega Portfolio Project was passed.
     */
    public function checkPhaseCompletable(int $phaseNumber): array
    {
        $requiredTopics = $this->roadmap->getRequiredTopicsForPhase($phaseNumber);
        $topic = Topic::where('phase_number', $phaseNumber)->first();

        // Gather all concepts learned by the user for this phase's topic or related concept names
        $userConcepts = Concept::where(function ($query) use ($topic) {
            if ($topic) {
                $query->where('topic_id', $topic->id);
            }
        })->get();

        $userConceptNames = $userConcepts->pluck('name')->map(fn ($n) => strtolower($n))->all();

        $missingTopics = [];
        foreach ($requiredTopics as $req) {
            $reqLower = strtolower($req);
            $found = false;

            // Check direct match or substring match in user concept names or summaries
            foreach ($userConcepts as $c) {
                if (str_contains(strtolower($c->name), $reqLower) || str_contains(strtolower($c->summary), $reqLower)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missingTopics[] = $req;
            }
        }

        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        $projectPassed = $progress && $progress->project_score >= 70;
        $projectScore = $progress?->project_score;

        // Missing topics are advisory only — they do NOT block phase completion.
        $isCompletable = $projectPassed;

        $reasons = [];
        if (! empty($missingTopics)) {
            $reasons[] = '(Advisory) You still have gaps in: ' . implode(', ', $missingTopics) . '. Consider studying them, but you may proceed.';
        }
        if (! $projectPassed) {
            $reasons[] = 'Complete and pass (score ≥ 70) the ' . StudyMode::label() . ' Mega Portfolio Project for Phase ' . $phaseNumber . '.';
        }

        return [
            'is_completable' => $isCompletable,
            'missing_topics' => $missingTopics,
            'project_passed' => $projectPassed,
            'project_score' => $projectScore,
            'progress' => $progress,
            'reason' => implode(' ', $reasons),
        ];
    }

    /**
     * Generate the Claude Pro research prompt for Stage 1 of the Mega Portfolio flow.
     * This prompt is shown to the user to copy into Claude Pro for real 2026 trend research.
     * Includes ALL past phases and their concepts for richer, context-aware research.
     */
    public function generateResearchPrompt(int $phaseNumber): string
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        // Current phase concepts
        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptList = $concepts->pluck('name')->implode(', ')
            ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber));

        $role = StudyMode::label();

        // Build a full learning history of ALL past phases (1 through phaseNumber-1)
        $priorPhaseSummary = '';
        if ($phaseNumber > 1) {
            $lines = [];
            for ($p = 1; $p < $phaseNumber; $p++) {
                $pastPhase = $this->roadmap->getPhase($p);
                if (! $pastPhase) {
                    continue;
                }
                $pastTopic = Topic::where('phase_number', $p)->first();
                $pastConcepts = Concept::where('topic_id', $pastTopic?->id)->pluck('name');
                $pastConceptStr = $pastConcepts->isNotEmpty()
                    ? $pastConcepts->implode(', ')
                    : implode(', ', $this->roadmap->getRequiredTopicsForPhase($p));
                $lines[] = "- **Phase {$p} — {$pastPhase['title']}:** {$pastConceptStr}";
            }
            $priorPhaseSummary = implode("\n", $lines);
        }

        $priorPhasesSection = $phaseNumber > 1
            ? "- **Prior phases completed (full skill stack):**\n{$priorPhaseSummary}\n"
            : '';

        return <<<PROMPT
You are a 2026 AI Security Engineering talent analyst and curriculum researcher. I need you to do thorough, source-backed research to help me design a killer portfolio project for my skill level.

## MY FULL LEARNING CONTEXT
- **Role I'm targeting:** {$role}
- **Phase I just completed:** Phase {$phaseNumber} — {$phase['title']}
- **Concepts learned in THIS phase:** {$conceptList}
{$priorPhasesSection}
> Use my COMPLETE skill stack above when recommending a project. The best project should integrate concepts from multiple phases where it makes sense — not just the latest one in isolation.

## YOUR RESEARCH MISSION
Find the MOST in-demand, genuinely valuable portfolio project topics for an {$role} in 2026 that someone with my exact skill background could build. This research will directly feed into a portfolio project generator — be specific, not generic.

## SOURCES TO CHECK (check all of them)
1. **Reddit** — r/netsec, r/AISecOps, r/MachineLearning, r/LLMSecurity — what are practitioners actually worried about? Top posts from 2025-2026.
2. **GitHub Trending** — AI + Security repos with the most stars/forks in the last 12 months. What tools are being built?
3. **LinkedIn Job Postings** — Search "{$role}", "MLSecOps", "LLM Security", "AI Red Team" — what skills appear in EVERY posting?
4. **Hacker News** — Threads about AI model vulnerabilities, prompt injection, LLM supply chain attacks. What keeps people up at night?
5. **CVE Database / NVD** — What new attack vectors targeting AI/ML systems appeared in 2025-2026? Any patterns?
6. **Conference talks** — DEF CON AI Village 2025, NeurIPS Security workshops, Black Hat AI talks — what was the hottest topic?
7. **OWASP LLM Top 10 (2025 update)** — Which vulnerabilities are most critical and underrepresented in portfolios?
8. **X/Twitter** — @AISecDevs, #AISecOps, #MLSec — what are practitioners posting about?
9. **Company engineering blogs** — Anthropic, OpenAI, Google DeepMind security teams — what problems are they publishing about?

## OUTPUT FORMAT (be very specific)

### 1. Top 5 Hottest Project Areas for 2026
For each: name it, explain WHY it's hot right now (cite real evidence — company names, CVE numbers, GitHub repos, post titles), rate the employer-appeal, and note which of my skills from the phases above it would leverage.

### 2. Key Technologies & Frameworks Companies Are Hiring For RIGHT NOW
List the actual tool names, libraries, frameworks. Not generic — specific. Flag which ones align with skills I've already learned.

### 3. Specific Attack Vectors or Security Problems That Are Trending or Unsolved
Real problems. Real CVE references where possible. What's the gap in the field?

### 4. What Makes an AI Security Portfolio Project Stand Out on GitHub in 2026?
What separates a 5-star project from a generic one? Specific signals hiring managers look for.

### 5. Emerging Standards, Certifications, or Compliance Frameworks to Demonstrate
Any new ISO, NIST AI RMF, EU AI Act, or similar frameworks that projects should align with?

### 6. Your Recommendation
Given my COMPLETE background (Phase {$phaseNumber} just completed, full stack above), what ONE project direction would give me maximum employer signal in 2026? Be opinionated. Explain how it pulls from multiple phases of my learning.

Be specific. Use real names. This research will feed directly into a portfolio project generator, so the more concrete the better.
PROMPT;
    }

    /**
     * Generate a Mega Portfolio Project brief grounded in real research from Claude Pro.
     * Stage 2 of the research ingestion flow.
     */
    public function generateMegaProjectFromResearch(int $phaseNumber, string $researchContext): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptList = $concepts->pluck('name')->implode(', ')
            ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber));

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = "MODE: GENERATE_MEGA_PROJECT\n\n"
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Phase Notes: " . ($phase['notes'] ?? 'N/A') . "\n"
            . "Target Role: " . StudyMode::label() . "\n"
            . "Learned Concepts: {$conceptList}\n\n"
            . "## REAL-WORLD RESEARCH (from external 2026 market analysis)\n"
            . "The following is fresh research on what the " . StudyMode::label() . " job market actually needs in 2026. "
            . "Use this research to ground the project in REAL current demand — not generic topics.\n\n"
            . $researchContext . "\n\n"
            . "## YOUR TASK\n"
            . "Generate a real-world, high-impact " . StudyMode::label() . " Mega Portfolio Project brief that:\n"
            . "1. Is DIRECTLY informed by the 2026 market research above\n"
            . "2. Integrates the concepts listed (they are what the learner has actually studied)\n"
            . "3. Addresses problems that real employers care about RIGHT NOW based on the research\n"
            . "4. Would genuinely stand out on GitHub to an AI Security hiring manager in 2026";

        $briefMd = $this->llm->chat($systemPrompt, $userPrompt);

        $progress = PhaseProgress::firstOrCreate(['phase_number' => $phaseNumber]);
        $progress->update([
            'mega_project_md' => $briefMd,
            'research_context' => $researchContext,
            'implementation_plan_md' => null,
            'status' => $progress->status === 'completed' ? 'completed' : 'pending_project',
        ]);

        return $progress;
    }

    /** Generate or regenerate the Mega Portfolio Project for a phase (legacy — no research context). */
    public function generateMegaProject(int $phaseNumber): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptList = $concepts->pluck('name')->implode(', ');

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = "MODE: GENERATE_MEGA_PROJECT\n\n"
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Phase Notes: " . ($phase['notes'] ?? 'N/A') . "\n"
            . "Target Role: " . StudyMode::label() . "\n"
            . "Learned Concepts: " . ($conceptList ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber))) . "\n\n"
            . "Please generate a real-world, high-impact " . StudyMode::label() . " Mega Portfolio Project brief combining these concepts.";

        $briefMd = $this->llm->chat($systemPrompt, $userPrompt);

        $progress = PhaseProgress::firstOrCreate(['phase_number' => $phaseNumber]);
        $progress->update([
            'mega_project_md' => $briefMd,
            'implementation_plan_md' => null,
            'status' => $progress->status === 'completed' ? 'completed' : 'pending_project',
        ]);

        return $progress;
    }

    /** Stage 3: Generate a Milestone-Based WBS implementation plan from the finalized brief. */
    public function generateImplementationPlan(int $phaseNumber, bool $learnerMode = false): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->mega_project_md) {
            throw new \RuntimeException('Generate the Mega Project brief before generating an implementation plan.');
        }

        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptList = $concepts->pluck('name')->implode(', ')
            ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber));

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = "MODE: GENERATE_IMPLEMENTATION_PLAN\n\n"
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Target Role: " . StudyMode::label() . "\n"
            . ($learnerMode ? "Learner mode: ON\n" : '')
            . "Learned Concepts: {$conceptList}\n\n"
            . "## MEGA PROJECT BRIEF\n{$progress->mega_project_md}\n\n"
            . "## YOUR TASK\n" . ($learnerMode
                ? "Generate the FULL Learner-mode implementation plan for the brief above — every milestone, start to finish — following MODE 3 and LEARNER MODE rules exactly, then the GLOSSARY, then END OF DOCUMENT."
                : "Generate the full Milestone-Based WBS implementation plan for the brief above, following MODE 3 rules exactly.");

        $planMd = $learnerMode
            ? $this->chatUntilComplete($systemPrompt, $userPrompt)
            : $this->llm->chat($systemPrompt, $userPrompt);

        $progress->update(['implementation_plan_md' => $planMd]);

        return $progress;
    }

    /** Learner-mode plans (and fix plans) end with this line; a document without it was cut off. */
    public const END_MARKER = 'END OF DOCUMENT';

    public static function isLearnerDoc(?string $md): bool
    {
        return str_contains((string) $md, '**Learner mode: ON**');
    }

    /** A Learner-mode document that does not end with END OF DOCUMENT was cut off. */
    public static function looksCutOff(?string $md): bool
    {
        return self::isLearnerDoc($md) && ! self::hasEndMarker((string) $md);
    }

    private static function hasEndMarker(string $md): bool
    {
        return str_ends_with(rtrim($md), self::END_MARKER);
    }

    /** One chat call, plus up to 5 "continue where you stopped" calls while the reply lacks END OF DOCUMENT. */
    private function chatUntilComplete(string $systemPrompt, string $userPrompt): string
    {
        $text = rtrim($this->llm->chat($systemPrompt, $userPrompt));

        for ($round = 0; $round < 5 && ! self::hasEndMarker($text); $round++) {
            $more = $this->llm->chat($systemPrompt, $userPrompt
                . "\n\n## YOUR REPLY SO FAR (it was cut off — this is its end)\n" . mb_substr($text, -3000)
                . "\n\n## CONTINUE\nContinue EXACTLY where the reply above stops, mid-sentence if needed. Do not repeat anything, no preamble. Finish with END OF DOCUMENT.");
            $text .= rtrim($more);
        }

        return $text;
    }

    /** A report that fails these is never graded; the learner has to re-run the audit. */
    private const AUDIT_REPORT_HEADING = '/^\s*#\s*PROJECT AUDIT REPORT\b/mi';

    /** An audit report that lists failed gates or constraints cannot pass, whatever the model scores it. */
    private const AUDIT_FAIL_CAP = 69;

    public static function isAuditReport(string $submission): bool
    {
        return (bool) preg_match(self::AUDIT_REPORT_HEADING, $submission);
    }

    /**
     * The milestone gates and checks lifted out of an implementation plan — the standards a finished project is
     * judged against. Step lists, whys and reference solutions are left out (they are build help, not standards).
     */
    public static function gatesFromPlan(?string $plan): string
    {
        $out = [];
        $header = null;
        $capturing = false;

        // A plan's "Brief Coverage" table says which brief requirement each part of the plan builds — part of the standards.
        $coverage = preg_match('/^## Brief Coverage\s*\n(.*?)(?=^## |\z)/ms', (string) $plan, $c)
            ? trim(preg_replace('/\n-{3,}\s*$/', '', trim($c[1])))
            : '';

        // Not "\R": without /u it also matches the byte 0x85, which ends the ✅ character and would cut it in half.
        foreach (preg_split('/\r\n|\r|\n/', (string) $plan) as $line) {
            if (preg_match('/^## (Milestone \d+.*)$/', $line, $m)) {
                $header = '#### '.trim($m[1]);
                $capturing = false;

                continue;
            }
            if (str_starts_with($line, '## ')) {
                $header = null;
                $capturing = false;

                continue;
            }
            if ($header === null) {
                continue;
            }

            $isGate = preg_match('/^\*\*(✅ Checks for|FINAL ACCEPTANCE CRITERIA|COMPLETE ONLY WHEN)/u', $line)
                || preg_match('/^- \[ \] Check it works:/', $line);

            if (! $isGate && $capturing) {
                if (trim($line) === '' || preg_match('/^(\s|- |\d+\. )/', $line)) {
                    $out[] = $line;

                    continue;
                }
                $capturing = false;
            }

            if ($isGate) {
                if ($header !== '') {
                    $out[] = '';
                    $out[] = $header;
                    $header = '';
                }
                $capturing = str_starts_with($line, '**');
                if ($capturing && end($out) !== '') {
                    $out[] = '';
                }
                $out[] = $line;
            }
        }

        $gates = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));

        return $coverage !== '' ? "#### Brief coverage (which part of the plan builds each brief requirement)\n\n{$coverage}\n\n{$gates}" : $gates;
    }

    /** @return array{0: string, 1: string, 2: string} brief, gates, audit id — the standards one audit is tied to */
    private function auditStandards(int $phaseNumber, PhaseProgress $progress): array
    {
        $brief = (string) $progress->mega_project_md;
        $gates = self::gatesFromPlan($progress->implementation_plan_md)
            ?: '_No implementation plan yet — judge the project against the brief alone._';
        $auditId = 'AUDIT-P'.$phaseNumber.'-'.strtoupper(substr(md5($brief."\n".$gates), 0, 8));

        return [$brief, $gates, $auditId];
    }

    /**
     * The prompt the learner hands to Claude Code to audit their whole repository. It carries the brief, the
     * milestone gates and a fixed report format; the Audit ID ties the report to these exact standards.
     *
     * @return array{prompt: string, audit_id: string}
     */
    public function generateAuditPrompt(int $phaseNumber): array
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->mega_project_md) {
            throw new \RuntimeException('Generate the Mega Project brief first — the audit prompt is built from it.');
        }

        [$brief, $gates, $auditId] = $this->auditStandards($phaseNumber, $progress);
        preg_match('/^#\s*Mega Portfolio Project:\s*(.+)$/mi', $brief, $m);

        $prompt = strtr(StudyMode::renderPrompt(File::get(resource_path('prompts/project-audit.md'))), [
            '{{AUDIT_ID}}' => $auditId,
            '{{PROJECT_TITLE}}' => trim($m[1] ?? "Phase {$phaseNumber} project"),
            '{{PHASE_LABEL}}' => "Phase {$phaseNumber} — {$phase['title']}",
            '{{BRIEF}}' => $brief,
            '{{GATES}}' => $gates,
            '{{RESUBMISSION}}' => $this->resubmissionForAudit($progress),
        ]);

        return ['prompt' => $prompt, 'audit_id' => $auditId];
    }

    /** For a re-audit: the problems found last time, so the new report must check each one. Empty on a first audit. */
    private function resubmissionForAudit(PhaseProgress $progress): string
    {
        $history = $this->attemptHistory($progress);
        if ($history === []) {
            return '';
        }

        $last = end($history);
        $number = $last['n'] + 1;

        return "## RESUBMISSION CHECK — this is attempt {$number}\n\n"
            ."I already submitted this project (attempt {$last['n']}: {$last['score']}/100). The problems below were found. In section 9 of your report, FIRST list every one of them with the verdict FIXED, STILL OPEN or REGRESSED and the evidence (a command and its real output, or `path:line`), THEN list any new defects. Do not take my word that something is fixed — check it. If a problem below is something neither the brief nor the gates ask for (rule 8), mark it OUT OF SCOPE instead — it is not graded.\n\n"
            .self::formatAttempts([$last], 1);
    }

    // ---------------------------------------------------------------- attempts (resubmissions)

    private const ATTEMPT_FEEDBACK_CHARS = 2500;

    private const ATTEMPT_DEFECT_CHARS = 3000;

    private const ATTEMPTS_KEPT = 10;

    /**
     * Every graded submission of this project, oldest first. A project graded before attempts were recorded
     * gets its single attempt rebuilt from the stored submission, feedback and score.
     *
     * @return list<array<string, mixed>>
     */
    public function attemptHistory(?PhaseProgress $progress): array
    {
        if (! $progress) {
            return [];
        }

        $attempts = $progress->project_attempts;
        if (is_array($attempts) && $attempts !== []) {
            return array_values($attempts);
        }

        if ($progress->project_eval_md === null || $progress->project_score === null) {
            return [];
        }

        return [self::attemptEntry(
            1,
            (int) $progress->project_score,
            (int) $progress->project_score >= 70,
            (string) $progress->project_submission,
            (string) $progress->project_eval_md,
            $progress->updated_at?->toDateTimeString(),
        )];
    }

    /** @return array<string, mixed> one graded submission, trimmed to what a later grading or audit needs */
    private static function attemptEntry(int $number, int $score, bool $passed, string $submission, string $feedback, ?string $at): array
    {
        $isAudit = self::isAuditReport($submission);

        return [
            'n' => $number,
            'at' => $at ?? now()->toDateTimeString(),
            'score' => $score,
            'passed' => $passed,
            'kind' => $isAudit ? 'audit' : 'code',
            'failed_rows' => $isAudit ? self::auditFailedRows($submission) : 0,
            'feedback_md' => mb_substr($feedback, 0, self::ATTEMPT_FEEDBACK_CHARS),
            'defects' => $isAudit ? mb_substr(self::reportSections($submission, [9]), 0, self::ATTEMPT_DEFECT_CHARS) : '',
        ];
    }

    /** @param list<array<string, mixed>> $attempts */
    private static function formatAttempts(array $attempts, int $last): string
    {
        $blocks = [];
        foreach (array_slice($attempts, -$last) as $a) {
            $lines = ["### Attempt {$a['n']} — {$a['at']} — {$a['score']}/100 (".($a['passed'] ? 'PASSED' : 'NOT PASSED').')'];
            if (! empty($a['failed_rows'])) {
                $lines[] = "The audit report listed {$a['failed_rows']} gate or constraint row(s) as FAIL or NOT RUN.";
            }
            $lines[] = "Grader feedback then:\n".trim((string) ($a['feedback_md'] ?? ''));
            if (! empty($a['defects'])) {
                $lines[] = "Defects the audit report listed then:\n".trim((string) $a['defects']);
            }
            $blocks[] = implode("\n\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /** The numbered `## N.` sections of an audit report, in the order given (code fences around the report are ignored). */
    public static function reportSections(string $report, array $numbers): string
    {
        $plain = preg_replace('/^```[a-z]*\s*$/mi', '', $report);
        $found = [];

        foreach (preg_split('/^(?=## \d+\.)/m', (string) $plain) as $part) {
            if (preg_match('/^## (\d+)\./', $part, $m) && in_array((int) $m[1], $numbers, true)) {
                $found[(int) $m[1]] = trim(preg_replace('/\n*END OF REPORT\s*$/i', '', trim($part)));
            }
        }

        $ordered = [];
        foreach ($numbers as $number) {
            if (isset($found[$number])) {
                $ordered[] = $found[$number];
            }
        }

        return implode("\n\n", $ordered);
    }

    /**
     * The real names of the project: every class and function signature in the reference solutions of the original
     * plan, labelled with its file. The learner's code follows those solutions, so a fix plan built on these names
     * does not invent methods the project does not have.
     */
    public static function planSignatures(?string $plan): string
    {
        $found = [];
        $file = null;
        $inCode = false;
        $inSolution = false;

        foreach (preg_split('/\r\n|\r|\n/', (string) $plan) as $line) {
            if (str_starts_with($line, '#')) {
                $inSolution = str_starts_with($line, '#### Reference solution');
                $inCode = false;

                continue;
            }
            if (! $inSolution) {
                continue;
            }
            if (str_starts_with($line, '```')) {
                $inCode = ! $inCode;

                continue;
            }
            if (! $inCode) {
                if (preg_match('/^`([\w\/.\-]+\.\w+)`/', $line, $f)) {
                    $file = $f[1];
                }

                continue;
            }
            if (preg_match('/^\s*(?:class |def |function |public function |protected function |private function |public static function )[^\n]*/', $line)) {
                $found[($file ? $file.' — ' : '').rtrim(trim($line), ':{ ')] = true;
            }
        }

        return implode("\n", array_map(fn ($line) => "- `{$line}`", array_slice(array_keys($found), 0, 150)));
    }

    /**
     * The code of the original plan's reference solutions, milestone by milestone. The learner built the project by
     * following them, so their code is close to it; the app cannot read the learner's repository, and a model that
     * cannot see any code writes steps that do not fit (wrong method, wrong place).
     */
    public static function planReferenceCode(?string $plan, int $maxChars = 30000): string
    {
        preg_match_all('/^#### Reference solution — (Milestone \d+)[^\n]*\n(.*?)(?=^#{2,4} |^\*\*FINAL ACCEPTANCE|\z)/ms', (string) $plan, $blocks, PREG_SET_ORDER);

        $text = implode("\n\n", array_map(fn ($b) => "Reference code of {$b[1]}:\n\n".trim($b[2]), $blocks));

        return mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars)."\n[cut here]" : $text;
    }

    /** The "command → expected output" lines of the original plan's checks: commands known to work in this project. */
    public static function planCommands(?string $plan): string
    {
        preg_match_all('/^- `[^`\n]+` →[^\n]*$/m', self::gatesFromPlan($plan), $lines);

        return implode("\n", array_slice(array_unique($lines[0]), 0, 40));
    }

    /** Pushes the headings of embedded text down (outside code fences) so they never look like the request's own sections. */
    private static function demoteHeadings(string $md, int $by = 2): string
    {
        $inCode = false;
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $md) as $line) {
            if (str_starts_with($line, '```')) {
                $inCode = ! $inCode;
            }
            if (! $inCode && preg_match('/^#{1,4} /', $line)) {
                $line = str_repeat('#', $by).$line;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** The "Architecture Lock" section of the plan: the names the fixes must keep. */
    private static function architectureLock(?string $plan): string
    {
        if (! preg_match('/^## Architecture Lock\s*\n(.*?)(?=^## |\z)/ms', (string) $plan, $m)) {
            return '_The plan has no architecture lock — keep every existing file, class and function name._';
        }

        return trim(preg_replace('/\n-{3,}\s*$/', '', trim($m[1])));
    }

    /**
     * The implementation plan for the learner's NEXT attempt, written by the app's own model in the same format as
     * their original plan (Learner mode or not), covering exactly what the last evaluation found weak. Every attempt
     * keeps its own plan in fix_plans, keyed by the attempt it is FOR (attempt 2's plan is built from attempt 1's
     * evaluation); creating it again for the same attempt replaces it.
     *
     * @return int the attempt number the plan is for
     */
    public function generateFixPlan(int $phaseNumber): int
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->mega_project_md) {
            throw new \RuntimeException('Generate the Mega Project brief first.');
        }

        $history = $this->attemptHistory($progress);
        if ($history === [] || ! $progress->project_eval_md) {
            throw new \RuntimeException('Submit the project for evaluation first — the fix plan is built from what the evaluation found.');
        }

        $last = end($history);
        $report = (string) $progress->project_submission;
        $findings = self::isAuditReport($report)
            ? self::reportSections($report, [3, 4, 5, 6, 9, 10])
            : '_This attempt was graded from pasted code, not from an audit report — the grader feedback above is the list of findings._';
        $earlier = count($history) > 1
            ? "## EARLIER ATTEMPTS (context only — do not plan again for problems that were already fixed)\n\n".self::formatAttempts(array_slice($history, 0, -1), 2)."\n\n"
            : '';
        preg_match('/^#\s*Mega Portfolio Project:\s*(.+)$/mi', (string) $progress->mega_project_md, $m);

        $forAttempt = (int) $last['n'] + 1;
        $title = trim($m[1] ?? "Phase {$phaseNumber} project");
        $learnerMode = self::isLearnerDoc($progress->implementation_plan_md);
        $knownNames = self::planSignatures($progress->implementation_plan_md)
            ?: '_The plan has no reference solutions — use only names the findings mention, and create any other name in a step that says so._';
        $knownCommands = self::planCommands($progress->implementation_plan_md)
            ?: '_No commands are known — describe what the learner should see instead of inventing a command._';
        $referenceCode = self::planReferenceCode($progress->implementation_plan_md)
            ?: '_The plan has no reference code — use only the names and line numbers in the findings._';
        $notNeeded = '_(not needed for this part)_';

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));
        $template = StudyMode::renderPrompt(File::get(resource_path('prompts/project-fix-plan.md')));
        // A free model has a small context window: every part gets only what it needs.
        //   OUTLINE / WHOLE PLAN: findings + brief; MILESTONE: findings + the code; GLOSSARY: nothing (the steps come in the task).
        $request = fn (string $part, string $task): string => strtr($template, [
            '{{PHASE_LABEL}}' => "Phase {$phaseNumber} — {$phase['title']}",
            '{{LEARNER_LINE}}' => $learnerMode ? "Learner mode: ON\n" : '',
            '{{PROJECT_TITLE}}' => $title,
            '{{ATTEMPT_NO}}' => (string) $last['n'],
            '{{NEXT_ATTEMPT}}' => (string) $forAttempt,
            '{{PART}}' => $part,
            '{{SCORE}}' => (string) $last['score'],
            '{{STATUS}}' => $last['passed'] ? 'PASSED' : 'NOT PASSED YET',
            '{{ARCHITECTURE}}' => $part === 'GLOSSARY' ? $notNeeded : self::demoteHeadings(self::architectureLock($progress->implementation_plan_md)),
            '{{KNOWN_NAMES}}' => $part === 'GLOSSARY' ? $notNeeded : $knownNames,
            '{{KNOWN_COMMANDS}}' => $part === 'GLOSSARY' ? $notNeeded : $knownCommands,
            '{{REFERENCE_CODE}}' => str_starts_with($part, 'MILESTONE') || $part === 'WHOLE PLAN' ? self::demoteHeadings($referenceCode, 3) : $notNeeded,
            '{{EVALUATION}}' => $part === 'GLOSSARY' ? $notNeeded : self::demoteHeadings(trim((string) $progress->project_eval_md)),
            '{{FINDINGS}}' => $part === 'GLOSSARY' ? $notNeeded : self::demoteHeadings($findings !== '' ? $findings : '_The report had no findings sections — use the grader feedback above._'),
            '{{EARLIER}}' => $part === 'GLOSSARY' || str_starts_with($part, 'MILESTONE') ? '' : self::demoteHeadings($earlier),
            '{{BRIEF}}' => $part === 'OUTLINE' || $part === 'WHOLE PLAN' ? self::demoteHeadings((string) $progress->mega_project_md) : $notNeeded,
            '{{TASK}}' => $task,
        ]);

        set_time_limit(900);   // a staged plan is several model calls

        if ($learnerMode) {
            // Staged and checked in code: a free model asked for the whole plan in one reply skipped the step blocks.
            $planMd = (new FixPlanWriter(
                $request,
                fn (string $user): string => $this->chatUntilComplete($systemPrompt, $user),
                StudyMode::fixPlanExample(),
                $title,
                $forAttempt,
                (string) $progress->mega_project_md."\n".$knownCommands,   // what a finding's quote is checked against
            ))->write();
        } else {
            $planMd = $this->chatUntilComplete($systemPrompt, $request('WHOLE PLAN', "Write the whole Fix Plan for attempt {$forAttempt} in one reply, ending with the Re-audit and resubmit milestone, the GLOSSARY and the line END OF DOCUMENT."));
        }

        $plans = $progress->fix_plans ?? [];
        $plans[$forAttempt] = ['md' => $planMd, 'from' => (int) $last['n'], 'at' => now()->toDateTimeString()];
        ksort($plans);
        $progress->update(['fix_plans' => $plans]);

        return $forAttempt;
    }

    /** @return array<int, array{md: string, from: int, at: string}> the project's fix plans, oldest attempt first */
    public function fixPlans(?PhaseProgress $progress): array
    {
        $plans = is_array($progress?->fix_plans) ? $progress->fix_plans : [];
        ksort($plans);

        return $plans;
    }

    /** Refuses a pasted report that belongs to other standards or that was cut off while copying. */
    private function assertAuditReportGradable(string $auditId, string $report): void
    {
        $plain = preg_replace('/^```[a-z]*\s*$/mi', '', $report);

        if (! preg_match('/^Audit ID:\s*(AUDIT-P\d+-[0-9A-F]{8})/mi', $plain, $m)) {
            throw new AuditReportRejected('This report has no "Audit ID:" line. Click Generate Audit Prompt, give that prompt to Claude Code, and paste its whole report.');
        }
        if (strtoupper($m[1]) !== $auditId) {
            throw new AuditReportRejected('This report was made from an older prompt — the brief or the plan changed since. Click Generate Audit Prompt again and re-run the audit.');
        }
        if (! preg_match('/END OF REPORT\s*$/i', rtrim($plain))
            || ! preg_match('/^## 3\./m', $plain) || ! preg_match('/^## 5\./m', $plain) || ! preg_match('/^## 10\./m', $plain)) {
            throw new AuditReportRejected('This report looks cut off — it must contain sections 3, 5 and 10 and end with END OF REPORT. Ask Claude Code to print the whole report again and paste all of it.');
        }
    }

    /** Rows whose verdict cell says FAIL or NOT RUN in the report's gate (3) and hard-constraint (4) tables. */
    public static function auditFailedRows(string $report): int
    {
        $failed = 0;

        foreach ([3, 4] as $section) {
            if (! preg_match('/^## '.$section.'\.[^\n]*\n(.*?)(?=^## \d+\.|\z)/ms', $report, $m)) {
                continue;
            }

            $column = null;
            foreach (preg_split('/\r\n|\r|\n/', $m[1]) as $line) {
                if (! str_starts_with(trim($line), '|')) {
                    continue;
                }
                $cells = array_map('trim', explode('|', trim(trim($line), '|')));

                if ($column === null) {
                    foreach ($cells as $i => $cell) {
                        if (stripos($cell, 'verdict') !== false) {
                            $column = $i;
                        }
                    }

                    continue;
                }
                $verdict = strtoupper(trim((string) preg_replace('/[^A-Za-z ]+/u', '', $cells[$column] ?? '')));
                if (preg_match('/^(FAIL|FAILED|NOT RUN)\b/', $verdict)) {
                    $failed++;
                }
            }
        }

        return $failed;
    }

    /** Submit and evaluate the learner's Mega Portfolio Project (code and docs, or a Claude Code audit report). */
    public function evaluateMegaProject(int $phaseNumber, string $submission): array
    {
        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->mega_project_md) {
            $progress = $this->generateMegaProject($phaseNumber);
        }

        $isAudit = self::isAuditReport($submission);
        [, $gates, $auditId] = $this->auditStandards($phaseNumber, $progress);
        if ($isAudit) {
            $this->assertAuditReportGradable($auditId, $submission);
        }

        $phase = $this->roadmap->getPhase($phaseNumber);
        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptNames = $concepts->pluck('name')->implode(', ') ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber));

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        // Taken before the stored submission/feedback are overwritten below.
        $history = $this->attemptHistory($progress);
        $attemptNo = $history === [] ? 1 : end($history)['n'] + 1;

        $userPrompt = "MODE: EVALUATE_MEGA_PROJECT\n\n";

        if ($history !== []) {
            $userPrompt .= "RESUBMISSION — this is attempt {$attemptNo}. The learner was graded on this project before. Follow the RESUBMISSIONS rules in your instructions. Earlier attempts, oldest first:\n\n"
                .self::formatAttempts($history, 2)."\n\n";
        }

        if ($isAudit) {
            // The model cannot read a whole repository, so it gets the standards in short form plus the report.
            $userPrompt .= "Submission type: AUDIT REPORT — written by an AI coding agent from the learner's whole repository. The app already checked that its Audit ID matches this brief and that it is complete.\n\n"
                . "Project Brief:\n{$progress->mega_project_md}\n\n"
                . "Milestone gates and checks from the Implementation Plan:\n{$gates}\n\n"
                . "Learned Concepts: {$conceptNames}\n\n"
                . "Audit report:\n{$submission}";
        } else {
            $userPrompt .= "Project Brief:\n{$progress->mega_project_md}\n\n";

            if ($progress->implementation_plan_md) {
                $userPrompt .= "Implementation Plan (check the submission against its milestone gates):\n{$progress->implementation_plan_md}\n\n";
            }

            $userPrompt .= "Learned Concepts: {$conceptNames}\n\n"
                . "Learner Submitted Code & Documentation:\n{$submission}";
        }

        $evalJson = $this->llm->chatJson($systemPrompt, $userPrompt);

        $score = (int) ($evalJson['overall_score'] ?? 0);
        $passed = (bool) ($evalJson['passed'] ?? ($score >= 70));
        $feedbackMd = $evalJson['feedback_md'] ?? 'No feedback provided.';

        // Enforced here, not in the prompt: free models skip prose rules.
        $failedRows = $isAudit ? self::auditFailedRows($submission) : 0;
        if ($failedRows > 0 && $score > self::AUDIT_FAIL_CAP) {
            $score = self::AUDIT_FAIL_CAP;
            $passed = false;
            $feedbackMd .= "\n\n> **Score capped at ".self::AUDIT_FAIL_CAP.":** the audit report itself lists {$failedRows} gate or constraint row(s) as FAIL or NOT RUN. Fix them, run the audit again and submit the new report.";
        }

        $history[] = self::attemptEntry($attemptNo, $score, $passed, $submission, $feedbackMd, now()->toDateTimeString());

        // Auto-complete the phase when the project passes — missing topics are advisory only.
        $progress->update([
            'project_submission' => $submission,
            'project_eval_md' => $feedbackMd,
            'project_score' => $score,
            'project_attempts' => array_slice($history, -self::ATTEMPTS_KEPT),
            'status' => $passed ? 'completed' : $progress->status,
            'completed_at' => $passed ? now() : $progress->completed_at,
        ]);

        return [
            'attempt' => $attemptNo,
            'score' => $score,
            'passed' => $passed,
            'feedback_md' => $feedbackMd,
            'concept_scores' => $evalJson['concept_scores'] ?? [],
            'security_insights' => $evalJson['security_insights'] ?? '',
        ];
    }

    /** Mark phase complete after passing validation. */
    public function markPhaseCompleted(int $phaseNumber): array
    {
        $check = $this->checkPhaseCompletable($phaseNumber);

        if (! $check['is_completable']) {
            return [
                'success' => false,
                'message' => $check['reason'],
            ];
        }

        $progress = PhaseProgress::firstOrCreate(['phase_number' => $phaseNumber]);
        $progress->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Congratulations! Phase {$phaseNumber} has been officially marked as completed.",
        ];
    }
}
