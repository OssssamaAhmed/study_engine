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
        $weeksDone = max(0, min($week - 1, $totalWeeks));
        $currentPhaseTopic = $roadmap->currentPhaseTopic();

        $unreadNotifications = $notificationService->getUnread();
        $phaseCheck = $currentPhaseTopic ? $phaseService->checkPhaseCompletable($currentPhaseTopic->phase_number) : null;

        return view('dashboard', [
            'exercise' => $exercises->todays() ?? $exercises->latestPending(),
            'missions' => Exercise::orderByDesc('date')->orderByDesc('id')->get(),
            'dueCount' => Concept::where('retired', false)
                ->whereDate('next_review_at', '<=', today())
                ->count(),
            'phase' => $currentPhaseTopic,
            'phaseCheck' => $phaseCheck,
            'notifications' => $unreadNotifications,
            'week' => $week,
            'streak' => ActivityLog::streak(),
            'name' => LearnerName::get(),
            'topics' => $topics,
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
            'drills' => ['nullable', 'integer', 'min:5', 'max:10'],
            'bosses' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        try {
            $exercises->generate($data['difficulty'] ?? 'Easy', (int) ($data['drills'] ?? 5), (int) ($data['bosses'] ?? 1));
        } catch (\RuntimeException $e) {
            return redirect('/')->with('status', $e->getMessage());
        }

        return redirect('/');
    }
}
