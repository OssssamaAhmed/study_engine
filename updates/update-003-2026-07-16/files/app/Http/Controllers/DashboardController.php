<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Exercise;
use App\Services\ExerciseService;
use App\Models\Topic;
use App\Services\RoadmapService;
use App\Support\LearnerName;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(RoadmapService $roadmap, ExerciseService $exercises)
    {
        $week = $roadmap->currentWeek();
        $topics = Topic::orderBy('phase_number')->get();
        $totalWeeks = (int) $topics->max('week_end');
        $weeksDone = max(0, min($week - 1, $totalWeeks));

        return view('dashboard', [
            // Today's mission, else the newest unsubmitted one — work in
            // progress must survive server restarts and day changes.
            'exercise' => $exercises->todays() ?? $exercises->latestPending(),
            'missions' => Exercise::orderByDesc('date')->orderByDesc('id')->get(),
            'dueCount' => Concept::where('retired', false)
                ->whereDate('next_review_at', '<=', today())
                ->count(),
            'phase' => $roadmap->currentPhaseTopic(),
            'week' => $week,
            'streak' => $this->streak(),
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

    /**
     * Consecutive days with a generated Mission, counting back from today
     * (a not-yet-generated today does not break the streak).
     */
    private function streak(): int
    {
        $days = Exercise::pluck('date')->map(fn ($d) => $d->toDateString());

        $cursor = today();
        if (! $days->contains($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;
        while ($days->contains($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }
}
