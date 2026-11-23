<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Topic;
use App\Services\ExerciseService;
use App\Services\NotificationService;
use App\Services\PhaseService;
use App\Services\RoadmapService;
use App\Support\ActivityLog;
use App\Support\LearnerName;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        RoadmapService $roadmap,
        ExerciseService $exercises,
        NotificationService $notificationService,
        PhaseService $phaseService
    ) {
        $week = $roadmap->currentWeek();
        $topics = Topic::orderBy('phase_number')->get();
        $totalWeeks = (int) $topics->max('week_end');
        $currentPhaseTopic = $roadmap->currentPhaseTopic();

        $unreadNotifications = $notificationService->getUnread();
        $phaseCheck = $currentPhaseTopic ? $phaseService->checkPhaseCompletable($currentPhaseTopic->phase_number) : null;

        // Progress bar: count only weeks belonging to phases the learner has explicitly moved past — completed, or moved
        // on after one graded attempt — never advance past the active phase by calendar.
        $completedPhaseNumbers = \App\Models\PhaseProgress::pastNumbers();
        $revisingPhases = \App\Models\PhaseProgress::where('status', \App\Models\PhaseProgress::MOVED_ON)
            ->orderBy('phase_number')
            ->get(['phase_number', 'project_score', 'project_attempts']);
        $completedWeeks = $topics
            ->whereIn('phase_number', $completedPhaseNumbers)
            ->sum(fn ($t) => $t->week_end - $t->week_start + 1);
        $weeksDone = $completedWeeks;

        return view('dashboard', [
            'exercise' => $exercises->todays() ?? $exercises->latestPending(),
            'dueCount' => Concept::where('retired', false)
                ->whereDate('next_review_at', '<=', today())
                ->count(),
            'totalConcepts' => Concept::where('retired', false)->count(),
            'missionsCompleted' => Exercise::where('status', 'evaluated')
                ->where('type', '!=', 'side_quest')
                ->count(),
            'sideQuestsCompleted' => Exercise::sideQuests()
                ->where('status', 'evaluated')
                ->count(),
            'phase' => $currentPhaseTopic,
            'phaseCheck' => $phaseCheck,
            'notifications' => $unreadNotifications,
            'week' => $week,
            'streak' => ActivityLog::streak(),
            'name' => LearnerName::get(),
            'topics' => $topics,
            'completedPhaseNumbers' => $completedPhaseNumbers,
            'revisingPhases' => $revisingPhases,
            'progress' => [
                'total' => $totalWeeks,
                'pct' => $totalWeeks ? intdiv($weeksDone * 100, $totalWeeks) : 0,
                'left' => $totalWeeks - $weeksDone,
            ],
        ]);
    }

    public function generate(Request $request, ExerciseService $exercises)
    {
        $data = $request->validate([
            'difficulty' => ['nullable', 'in:Easy,Medium,Hard'],
            'drills' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bosses' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        session(['learner_mode' => $request->boolean('learner_mode')]);
        session(['focused_drills' => $request->boolean('focused_drills')]);

        try {
            $exercises->generate($data['difficulty'] ?? 'Easy', (int) ($data['drills'] ?? 5), (int) ($data['bosses'] ?? 1), $request->boolean('learner_mode'), $request->boolean('focused_drills'));
        } catch (\RuntimeException $e) {
            return redirect('/')->with('status', $e->getMessage());
        }

        return redirect('/');
    }
}
