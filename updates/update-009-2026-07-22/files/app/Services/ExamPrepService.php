<?php

namespace App\Services;

use App\Models\ExamPrepSession;
use Illuminate\Support\Facades\File;

class ExamPrepService
{
    public function __construct(
        protected RoadmapService $roadmap,
        protected LlmService $llm
    ) {}

    /** Ingest external research report / study guide. */
    public function ingestStudyGuide(string $target, string $reportText): ExamPrepSession
    {
        return ExamPrepSession::create([
            'exam_target' => $target,
            'method' => 'ingested_guide',
            'study_guide_text' => $reportText,
            'session_data' => [
                'type' => 'study_guide',
                'length' => strlen($reportText),
                'ingested_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** Generate a new preparation session for one of the 4 methods. */
    public function generateSession(string $target, string $method, ?string $studyGuideText = null): ExamPrepSession
    {
        $systemPrompt = File::get(resource_path('prompts/exam-prep.md'));
        $checkpointData = null;

        // Find matching checkpoint if target matches Checkpoint 1..4
        foreach ($this->roadmap->getCheckpoints() as $index => $cp) {
            $name = $cp['title'] ?? $cp['name'] ?? ('Checkpoint ' . ($index + 1));
            if (str_contains(strtolower($target), strtolower($name)) || str_contains(strtolower($target), 'checkpoint ' . ($index + 1))) {
                $checkpointData = $cp;
                break;
            }
        }

        $promptContext = "MODE: GENERATE_EXAM\n"
            . "Target: {$target}\n"
            . "Method: {$method}\n";

        if ($studyGuideText) {
            $promptContext .= "\nIngested Study Guide / Research Report:\n" . substr($studyGuideText, 0, 4000) . "\n";
        }

        if ($checkpointData) {
            $promptContext .= "\nRoadmap Checkpoint MCQs & Questions:\n" . json_encode($checkpointData['mcq_exam'] ?? $checkpointData['short_answer_questions'] ?? []);
        }

        $sessionData = $this->llm->chatJson($systemPrompt, $promptContext);

        return ExamPrepSession::create([
            'exam_target' => $target,
            'method' => $method,
            'study_guide_text' => $studyGuideText,
            'session_data' => $sessionData,
        ]);
    }

    /** Submit and evaluate an exam prep session. */
    public function evaluateSession(int $sessionId, string $userSubmission): array
    {
        $session = ExamPrepSession::findOrFail($sessionId);

        $systemPrompt = File::get(resource_path('prompts/exam-prep.md'));

        $promptContext = "MODE: EVALUATE_EXAM\n"
            . "Exam Target: {$session->exam_target}\n"
            . "Method: {$session->method}\n\n"
            . "Questions & Distractor Analysis:\n" . json_encode($session->session_data) . "\n\n"
            . "Learner Answers & Metacognitive Logic Journal Submission:\n{$userSubmission}";

        $evalResult = $this->llm->chatJson($systemPrompt, $promptContext);

        $score = (int) ($evalResult['overall_score'] ?? 0);
        $feedbackMd = $evalResult['feedback_md'] ?? 'Evaluation complete.';

        $session->update([
            'user_submission' => $userSubmission,
            'evaluation_md' => $feedbackMd,
            'score' => $score,
        ]);

        return [
            'score' => $score,
            'feedback_md' => $feedbackMd,
            'metacognitive_evaluation' => $evalResult['metacognitive_evaluation'] ?? '',
            'distractor_mastery_score' => $evalResult['distractor_mastery_score'] ?? 0,
            'concept_breakdown' => $evalResult['concept_breakdown'] ?? [],
        ];
    }
}
