<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ExerciseService;
use Illuminate\Http\Request;

class SideQuestController extends Controller
{
    public function index()
    {
        return view('side_quest.index', [
            'sideQuests' => Exercise::sideQuests()
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function store(Request $request, ExerciseService $exercises)
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'min:5', 'max:500'],
            'concept_id' => ['nullable', 'integer', 'exists:concepts,id'],
            'difficulty' => ['nullable', 'in:Easy,Medium,Hard'],
            'drills' => ['nullable', 'integer', 'min:3', 'max:10'],
            'boss_fight' => ['nullable'],
        ]);

        try {
            $exercise = $exercises->generateSideQuest(
                topicPrompt: $data['topic'],
                conceptId: $data['concept_id'] ?? null,
                difficulty: $data['difficulty'] ?? 'Easy',
                drills: (int) ($data['drills'] ?? 5),
                bossFight: isset($data['boss_fight']),
            );
        } catch (\RuntimeException $e) {
            return redirect('/side-quest')->with('error', $e->getMessage());
        }

        return redirect("/exercise/{$exercise->id}");
    }
}
