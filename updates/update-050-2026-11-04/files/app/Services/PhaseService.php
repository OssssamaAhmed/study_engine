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
            'skeleton_md' => null,
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
            'skeleton_md' => null,
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
                ? "Start the Learner-mode implementation plan for the brief above, following MODE 3 and LEARNER MODE rules exactly: the title, Before We Start, Architecture Lock and Milestone Map for the WHOLE project, then Write only: Milestone 1, then the GLOSSARY, then END OF DOCUMENT."
                : "Generate the full Milestone-Based WBS implementation plan for the brief above, following MODE 3 rules exactly.");

        $planMd = $learnerMode
            ? $this->chatUntilComplete($systemPrompt, $userPrompt)
            : $this->llm->chat($systemPrompt, $userPrompt);

        $progress->update(['implementation_plan_md' => $planMd]);

        return $progress;
    }

    /** Stage 4: Generate a plain-English pseudocode skeleton grounded in the implementation plan. */
    public function generateSkeleton(int $phaseNumber, bool $learnerMode = false): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->implementation_plan_md) {
            throw new \RuntimeException('Generate the implementation plan before generating a skeleton.');
        }

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = "MODE: GENERATE_PLAIN_ENGLISH_SKELETON\n\n"
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Target Role: " . StudyMode::label() . "\n"
            . ($learnerMode ? "Learner mode: ON\n" : '')
            . "\n"
            . "## MEGA PROJECT BRIEF\n{$progress->mega_project_md}\n\n"
            . "## IMPLEMENTATION PLAN\n{$progress->implementation_plan_md}\n\n"
            . "## YOUR TASK\n" . ($learnerMode
                ? "Start the Learner-mode skeleton for the plan above, following MODE 4 and LEARNER MODE rules exactly: the title, How This Fits Together, then Write only: Milestone 1, then Data & Control Flow Across Milestones and Suggested File/Module Map for the whole project, then END OF DOCUMENT."
                : "Generate the plain-English pseudocode skeleton grounded in the implementation plan above, following MODE 4 rules exactly.");

        $skeletonMd = $learnerMode
            ? $this->chatUntilComplete($systemPrompt, $userPrompt)
            : $this->llm->chat($systemPrompt, $userPrompt);

        $progress->update(['skeleton_md' => $skeletonMd]);

        return $progress;
    }

    /**
     * Learner mode writes plan/skeleton one milestone per click (free models cut long replies off).
     * The next milestone number still to write for 'plan' or 'skeleton', or null when none is due.
     */
    public function nextMilestone(?PhaseProgress $progress, string $document): ?int
    {
        $plan = (string) $progress?->implementation_plan_md;
        if (! self::isLearnerDoc($plan)) {
            return null;
        }

        $planWritten = self::writtenMilestones($plan, 'plan');
        if ($document === 'plan') {
            preg_match_all('/^- Milestone (\d+):/m', $plan, $map);
            $total = $map[1] ? max(array_map('intval', $map[1])) : 0;

            return $planWritten < $total ? $planWritten + 1 : null;
        }

        $skeleton = (string) $progress?->skeleton_md;
        if (! self::isLearnerDoc($skeleton)) {
            return null;
        }
        $written = self::writtenMilestones($skeleton, 'skeleton');

        return $written < $planWritten ? $written + 1 : null;
    }

    /** Writes the next milestone of a Learner-mode plan or skeleton and merges it into the stored document. */
    public function generateNextMilestone(int $phaseNumber, string $document): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        $next = $this->nextMilestone($progress, $document);
        if (! $phase || $next === null) {
            throw new \RuntimeException('There is no milestone left to write — every milestone in the plan is done.');
        }

        $column = $document === 'plan' ? 'implementation_plan_md' : 'skeleton_md';
        $current = self::stripEndMarker($progress->{$column});
        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = ($document === 'plan' ? "MODE: GENERATE_IMPLEMENTATION_PLAN\n\n" : "MODE: GENERATE_PLAIN_ENGLISH_SKELETON\n\n")
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Target Role: " . StudyMode::label() . "\n"
            . "Learner mode: ON\n\n"
            . "## MEGA PROJECT BRIEF\n{$progress->mega_project_md}\n\n"
            . ($document === 'plan'
                ? "## PLAN SO FAR\n{$current}\n\n## YOUR TASK\nWrite only: Milestone {$next} — continue the plan above with the same names and the support level its Milestone Map gives Milestone {$next}. Start with `## Milestone {$next}:`, then `## GLOSSARY` with ONLY the terms that are new in this milestone, then END OF DOCUMENT."
                : "## IMPLEMENTATION PLAN\n" . self::stripEndMarker($progress->implementation_plan_md) . "\n\n## SKELETON SO FAR\n{$current}\n\n## YOUR TASK\nWrite only: Milestone {$next} — the `## Skeleton for Milestone {$next}:` section for the plan's Milestone {$next}, then END OF DOCUMENT. Do not repeat the other sections.");

        $piece = $this->chatUntilComplete($systemPrompt, $userPrompt);
        $progress->update([$column => self::mergeMilestone((string) $progress->{$column}, $piece, $document)]);

        return $progress;
    }

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

    private static function writtenMilestones(string $md, string $document): int
    {
        $pattern = $document === 'plan' ? '/^## Milestone (\d+)\b/m' : '/^## Skeleton for Milestone (\d+)\b/m';
        preg_match_all($pattern, $md, $m);

        return $m[1] ? max(array_map('intval', $m[1])) : 0;
    }

    private static function hasEndMarker(string $md): bool
    {
        return str_ends_with(rtrim($md), self::END_MARKER);
    }

    private static function stripEndMarker(?string $md): string
    {
        return rtrim(preg_replace('/\n*' . self::END_MARKER . '\s*$/', '', rtrim((string) $md)));
    }

    /** One chat call, plus up to 3 "continue where you stopped" calls while the reply lacks END OF DOCUMENT. */
    private function chatUntilComplete(string $systemPrompt, string $userPrompt): string
    {
        $text = rtrim($this->llm->chat($systemPrompt, $userPrompt));

        for ($round = 0; $round < 3 && ! self::hasEndMarker($text); $round++) {
            $more = $this->llm->chat($systemPrompt, $userPrompt
                . "\n\n## YOUR REPLY SO FAR (it was cut off — this is its end)\n" . mb_substr($text, -3000)
                . "\n\n## CONTINUE\nContinue EXACTLY where the reply above stops, mid-sentence if needed. Do not repeat anything, no preamble. Finish with END OF DOCUMENT.");
            $text .= rtrim($more);
        }

        return $text;
    }

    /**
     * Puts a new milestone piece into the stored document: before the plan's GLOSSARY (the piece's new
     * glossary lines are appended to it) or before the skeleton's closing plan-wide sections.
     * END OF DOCUMENT is re-added only when the piece itself was complete, so a cut-off piece stays visible.
     */
    private static function mergeMilestone(string $doc, string $piece, string $document): string
    {
        $complete = self::hasEndMarker($piece);
        $doc = self::stripEndMarker($doc);
        $piece = self::stripEndMarker($piece);

        $newGlossary = '';
        if ($document === 'plan' && preg_match('/^## GLOSSARY[^\n]*\n?/m', $piece, $g, PREG_OFFSET_CAPTURE)) {
            $newGlossary = trim(substr($piece, $g[0][1] + strlen($g[0][0])));
            $piece = substr($piece, 0, $g[0][1]);
        }

        $tailHeading = $document === 'plan' ? '/^## GLOSSARY/m' : '/^## Data & Control Flow/m';
        if (preg_match($tailHeading, $doc, $t, PREG_OFFSET_CAPTURE)) {
            $merged = rtrim(substr($doc, 0, $t[0][1])) . "\n\n" . trim($piece) . "\n\n" . rtrim(substr($doc, $t[0][1]))
                . ($newGlossary !== '' ? "\n" . $newGlossary : '');
        } else {
            $merged = $doc . "\n\n" . trim($piece) . ($newGlossary !== '' ? "\n\n## GLOSSARY\n" . $newGlossary : '');
        }

        return $merged . ($complete ? "\n\n" . self::END_MARKER : '');
    }

    /** Submit and evaluate the learner's Mega Portfolio Project. */
    public function evaluateMegaProject(int $phaseNumber, string $submission): array
    {
        $progress = PhaseProgress::where('phase_number', $phaseNumber)->first();
        if (! $progress || ! $progress->mega_project_md) {
            $progress = $this->generateMegaProject($phaseNumber);
        }

        $phase = $this->roadmap->getPhase($phaseNumber);
        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptNames = $concepts->pluck('name')->implode(', ') ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber));

        $systemPrompt = StudyMode::renderPrompt(File::get(resource_path('prompts/mega-project.md')));

        $userPrompt = "MODE: EVALUATE_MEGA_PROJECT\n\n"
            . "Project Brief:\n{$progress->mega_project_md}\n\n";

        if ($progress->implementation_plan_md) {
            $userPrompt .= "Implementation Plan (check the submission against its milestone gates):\n{$progress->implementation_plan_md}\n\n";
        }

        if ($progress->skeleton_md) {
            $userPrompt .= "Plain-English Skeleton (reference structure):\n{$progress->skeleton_md}\n\n";
        }

        $userPrompt .= "Learned Concepts: {$conceptNames}\n\n"
            . "Learner Submitted Code & Documentation:\n{$submission}";

        $evalJson = $this->llm->chatJson($systemPrompt, $userPrompt);

        $score = (int) ($evalJson['overall_score'] ?? 0);
        $passed = (bool) ($evalJson['passed'] ?? ($score >= 70));
        $feedbackMd = $evalJson['feedback_md'] ?? 'No feedback provided.';

        // Auto-complete the phase when the project passes — missing topics are advisory only.
        $progress->update([
            'project_submission' => $submission,
            'project_eval_md' => $feedbackMd,
            'project_score' => $score,
            'status' => $passed ? 'completed' : $progress->status,
            'completed_at' => $passed ? now() : $progress->completed_at,
        ]);

        return [
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
