<?php

namespace App\Http\Controllers;

use App\Services\PhaseService;
use App\Services\RoadmapService;
use Illuminate\Http\Request;

class PhaseController extends Controller
{
    public function __construct(
        protected PhaseService $phaseService,
        protected RoadmapService $roadmap
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
            'currentWeek' => $this->roadmap->currentWeek(),
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
        ]);
    }

    public function generateProject(int $phase)
    {
        $this->phaseService->generateMegaProject($phase);

        return redirect("/phases/{$phase}")->with('status', "AI Security Mega Portfolio Project brief generated for Phase {$phase}!");
    }

    public function submitProject(Request $request, int $phase)
    {
        $request->validate([
            'submission' => ['required', 'string', 'min:20'],
        ]);

        $res = $this->phaseService->evaluateMegaProject($phase, $request->input('submission'));

        $statusMsg = $res['passed']
            ? "Project evaluated! Score: {$res['score']}/100. Project PASSED!"
            : "Project evaluated! Score: {$res['score']}/100. Review feedback to reach 70+ for pass.";

        return redirect("/phases/{$phase}")->with('status', $statusMsg);
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
