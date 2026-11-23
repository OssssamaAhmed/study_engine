<?php

namespace App\Http\Controllers;

use App\Models\ExamPrepSession;
use App\Services\ExamPrepService;
use App\Services\RoadmapService;
use Illuminate\Http\Request;

class ExamPrepController extends Controller
{
    public function __construct(
        protected ExamPrepService $prepService,
        protected RoadmapService $roadmap
    ) {}

    public function index()
    {
        $currentWeek = $this->roadmap->currentWeek();
        $upcoming = $this->roadmap->getUpcomingExams($currentWeek);
        $recentSessions = ExamPrepSession::orderByDesc('created_at')->take(10)->get();

        return view('exam_prep.index', [
            'currentWeek' => $currentWeek,
            'upcomingExams' => $upcoming,
            'recentSessions' => $recentSessions,
        ]);
    }

    public function ingest(Request $request)
    {
        $request->validate([
            'target' => ['required', 'string', 'max:100'],
            'report_text' => ['required', 'string', 'min:50'],
        ]);

        $session = $this->prepService->ingestStudyGuide(
            $request->input('target'),
            $request->input('report_text')
        );

        return redirect('/exam-prep')->with('status', "Research report / study guide for '{$session->exam_target}' ingested successfully!");
    }

    public function startSession(Request $request)
    {
        $request->validate([
            'target' => ['required', 'string', 'max:100'],
            'method' => ['required', 'string', 'in:active_recall,spaced_1357,test_maker,metacognitive'],
        ]);

        $studyGuideText = $request->input('study_guide_text');

        $session = $this->prepService->generateSession(
            $request->input('target'),
            $request->input('method'),
            $studyGuideText
        );

        return redirect("/exam-prep/session/{$session->id}");
    }

    public function showSession(int $id)
    {
        $session = ExamPrepSession::findOrFail($id);

        return view('exam_prep.session', [
            'session' => $session,
        ]);
    }

    public function submitSession(Request $request, int $id)
    {
        $request->validate([
            'submission' => ['required', 'string'],
        ]);

        $eval = $this->prepService->evaluateSession($id, $request->input('submission'));

        return redirect("/exam-prep/session/{$id}")->with('status', "Exam prep session evaluated! Score: {$eval['score']}/100.");
    }
}
