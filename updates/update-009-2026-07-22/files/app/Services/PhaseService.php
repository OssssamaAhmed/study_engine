<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\PhaseProgress;
use App\Models\Topic;
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

        $isCompletable = empty($missingTopics) && $projectPassed;

        $reasons = [];
        if (! empty($missingTopics)) {
            $reasons[] = 'First learn and practice these topics: ' . implode(', ', $missingTopics);
        }
        if (! $projectPassed) {
            $reasons[] = 'Complete and pass (score ≥ 70) the AI Security Mega Portfolio Project for Phase ' . $phaseNumber . '.';
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

    /** Generate or regenerate the Mega Portfolio Project for a phase. */
    public function generateMegaProject(int $phaseNumber): PhaseProgress
    {
        $phase = $this->roadmap->getPhase($phaseNumber);
        if (! $phase) {
            throw new \InvalidArgumentException("Phase {$phaseNumber} does not exist in roadmap.");
        }

        $topic = Topic::where('phase_number', $phaseNumber)->first();
        $concepts = Concept::where('topic_id', $topic?->id)->get();
        $conceptList = $concepts->pluck('name')->implode(', ');

        $systemPrompt = File::get(resource_path('prompts/mega-project.md'));

        $userPrompt = "MODE: GENERATE_MEGA_PROJECT\n\n"
            . "Phase: Phase {$phaseNumber} — {$phase['title']}\n"
            . "Phase Notes: " . ($phase['notes'] ?? 'N/A') . "\n"
            . "Target Role: AI Security Engineer\n"
            . "Learned Concepts: " . ($conceptList ?: implode(', ', $this->roadmap->getRequiredTopicsForPhase($phaseNumber))) . "\n\n"
            . "Please generate a real-world, high-impact AI Security Engineer Mega Portfolio Project brief combining these concepts.";

        $briefMd = $this->llm->chat($systemPrompt, $userPrompt);

        $progress = PhaseProgress::firstOrCreate(['phase_number' => $phaseNumber]);
        $progress->update([
            'mega_project_md' => $briefMd,
            'status' => $progress->status === 'completed' ? 'completed' : 'pending_project',
        ]);

        return $progress;
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

        $systemPrompt = File::get(resource_path('prompts/mega-project.md'));

        $userPrompt = "MODE: EVALUATE_MEGA_PROJECT\n\n"
            . "Project Brief:\n{$progress->mega_project_md}\n\n"
            . "Learned Concepts: {$conceptNames}\n\n"
            . "Learner Submitted Code & Documentation:\n{$submission}";

        $evalJson = $this->llm->chatJson($systemPrompt, $userPrompt);

        $score = (int) ($evalJson['overall_score'] ?? 0);
        $passed = (bool) ($evalJson['passed'] ?? ($score >= 70));
        $feedbackMd = $evalJson['feedback_md'] ?? 'No feedback provided.';

        $progress->update([
            'project_submission' => $submission,
            'project_eval_md' => $feedbackMd,
            'project_score' => $score,
            'status' => ($passed && empty($this->checkPhaseCompletable($phaseNumber)['missing_topics'])) ? 'completed' : $progress->status,
            'completed_at' => ($passed && empty($this->checkPhaseCompletable($phaseNumber)['missing_topics'])) ? now() : $progress->completed_at,
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
