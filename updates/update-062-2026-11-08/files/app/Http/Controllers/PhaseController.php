<?php

namespace App\Http\Controllers;

use App\Models\PhaseProgress;
use App\Services\AuditReportRejected;
use App\Services\DocumentExportService;
use App\Services\PhaseService;
use App\Services\RoadmapService;
use App\Support\StudyMode;
use Illuminate\Http\Request;

class PhaseController extends Controller
{
    /** phase-progress column + filename label for each exportable document. */
    protected const EXPORTABLE_DOCUMENTS = [
        'brief' => ['column' => 'mega_project_md', 'label' => 'Brief'],
        'plan' => ['column' => 'implementation_plan_md', 'label' => 'Implementation-Plan'],
        'skeleton' => ['column' => 'skeleton_md', 'label' => 'Skeleton'],
        'fixplan' => ['column' => 'fix_plan_md', 'label' => 'Fix-Plan'],
    ];

    protected const EXPORT_FORMATS = ['txt', 'md', 'doc', 'docx'];

    public function __construct(
        protected PhaseService $phaseService,
        protected RoadmapService $roadmap,
        protected DocumentExportService $exporter
    ) {}

    public function index()
    {
        $phases = $this->roadmap->phases();
        $phaseData = [];

        foreach ($phases as $p) {
            $num = $p['phase_number'];
            $check = $this->phaseService->checkPhaseCompletable($num);
            $phaseData[] = [
                'phase' => $p,
                'check' => $check,
            ];
        }

        return view('phases.index', [
            'phases' => $phaseData,
            'currentPhaseNumber' => $this->roadmap->currentPhaseTopic()?->phase_number,
        ]);
    }

    public function show(int $phase)
    {
        $phaseInfo = $this->roadmap->getPhase($phase);
        if (! $phaseInfo) {
            abort(404, "Phase {$phase} not found.");
        }

        $check = $this->phaseService->checkPhaseCompletable($phase);
        $progress = $check['progress'];

        return view('phases.show', [
            'phaseNumber' => $phase,
            'phaseInfo' => $phaseInfo,
            'check' => $check,
            'progress' => $progress,
            'attempts' => $this->phaseService->attemptHistory($progress),
        ]);
    }

    public function generateProject(int $phase)
    {
        $this->phaseService->generateMegaProject($phase);

        return redirect("/phases/{$phase}")->with('status', StudyMode::label()." Mega Portfolio Project brief generated for Phase {$phase}!");
    }

    /** Stage 1: Return the Claude Pro research prompt as JSON for the copy modal. */
    public function researchPrompt(int $phase): \Illuminate\Http\JsonResponse
    {
        try {
            $prompt = $this->phaseService->generateResearchPrompt($phase);
            return response()->json(['prompt' => $prompt]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Stage 2: Accept pasted research output and generate a grounded Mega Project brief. */
    public function generateFromResearch(Request $request, int $phase)
    {
        $request->validate([
            'research' => ['required', 'string', 'min:100'],
        ], [
            'research.required' => 'Please paste the research output from Claude Pro.',
            'research.min'      => 'The research content seems too short. Paste the full Claude Pro response.',
        ]);

        $this->phaseService->generateMegaProjectFromResearch($phase, $request->input('research'));

        return redirect("/phases/{$phase}")->with('status', StudyMode::label()." Mega Portfolio Project brief generated from your 2026 research for Phase {$phase}!");
    }

    public function generateImplementationPlan(Request $request, int $phase)
    {
        session(['learner_mode' => $request->boolean('learner_mode')]);

        try {
            $this->phaseService->generateImplementationPlan($phase, $request->boolean('learner_mode'));
        } catch (\Throwable $e) {
            return redirect("/phases/{$phase}#plan")->with('error', $e->getMessage());
        }

        return redirect("/phases/{$phase}#plan")->with('status', "Implementation plan generated for Phase {$phase}!");
    }

    public function generateSkeleton(Request $request, int $phase)
    {
        session(['learner_mode' => $request->boolean('learner_mode')]);

        try {
            $this->phaseService->generateSkeleton($phase, $request->boolean('learner_mode'));
        } catch (\Throwable $e) {
            return redirect("/phases/{$phase}#skeleton")->with('error', $e->getMessage());
        }

        return redirect("/phases/{$phase}#skeleton")->with('status', "Plain-English skeleton generated for Phase {$phase}!");
    }

    /** The prompt the learner gives to Claude Code so it audits the whole project and prints a report. */
    public function auditPrompt(int $phase): \Illuminate\Http\JsonResponse
    {
        try {
            return response()->json($this->phaseService->generateAuditPrompt($phase));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** The app writes the implementation plan for the next attempt from what the last evaluation found weak. */
    public function generateFixPlan(int $phase)
    {
        try {
            $progress = $this->phaseService->generateFixPlan($phase);
        } catch (\Throwable $e) {
            return redirect("/phases/{$phase}#fixplan")->with('error', $e->getMessage());
        }

        $next = $progress->fix_plan_attempt + 1;

        return redirect("/phases/{$phase}#fixplan")->with('status', "Implementation plan for attempt {$next} created from your attempt {$progress->fix_plan_attempt} evaluation!");
    }

    public function submitProject(Request $request, int $phase)
    {
        $request->validate([
            'submission' => ['required', 'string', 'min:20'],
        ]);

        try {
            $res = $this->phaseService->evaluateMegaProject($phase, $request->input('submission'));
        } catch (AuditReportRejected $e) {
            return redirect("/phases/{$phase}")->withInput()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            // e.g. the free model's reply was not readable JSON — keep the learner's text and let them retry
            report($e);

            return redirect("/phases/{$phase}")->withInput()
                ->with('error', 'The AI could not grade this submission just now (its reply was unreadable — free models do that). Your text is still in the box: click Submit again.');
        }

        $attempt = $res['attempt'] > 1 ? "Attempt {$res['attempt']} evaluated" : 'Project evaluated';
        $statusMsg = $res['passed']
            ? "{$attempt}! Score: {$res['score']}/100. Project PASSED!"
            : "{$attempt}! Score: {$res['score']}/100. Review feedback to reach 70+ for pass.";

        return redirect("/phases/{$phase}")->with('status', $statusMsg);
    }

    /** Download the brief, implementation plan, or skeleton as a .txt/.md/.doc/.docx file. */
    public function export(int $phase, string $document, string $format)
    {
        if (! isset(self::EXPORTABLE_DOCUMENTS[$document]) || ! in_array($format, self::EXPORT_FORMATS, true)) {
            abort(404);
        }

        ['column' => $column, 'label' => $label] = self::EXPORTABLE_DOCUMENTS[$document];

        $content = PhaseProgress::where('phase_number', $phase)->first()?->{$column};
        if (! $content) {
            abort(404, 'This document has not been generated yet.');
        }

        [$body, $mime] = $this->exporter->export($content, "Phase {$phase} — {$label}", $format);

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"Phase-{$phase}-{$label}.{$format}\"",
        ]);
    }

    public function attemptComplete(int $phase)
    {
        $res = $this->phaseService->markPhaseCompleted($phase);

        if (! $res['success']) {
            return redirect("/phases/{$phase}")->with('error', $res['message']);
        }

        return redirect("/phases/{$phase}")->with('status', $res['message']);
    }
}
