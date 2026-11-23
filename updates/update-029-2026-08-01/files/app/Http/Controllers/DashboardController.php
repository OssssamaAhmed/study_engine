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

        // Progress bar: count only weeks belonging to phases that are explicitly
        // marked 'completed' — never advance past the active phase by calendar.
        $completedPhaseNumbers = \App\Models\PhaseProgress::where('status', 'completed')
            ->pluck('phase_number')
            ->all();
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

        try {
            $exercises->generate($data['difficulty'] ?? 'Easy', (int) ($data['drills'] ?? 5), (int) ($data['bosses'] ?? 1));
        } catch (\RuntimeException $e) {
            return redirect('/')->with('status', $e->getMessage());
        }

        return redirect('/');
    }
}
