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
            'drills' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bosses' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        session(['learner_mode' => $request->boolean('learner_mode')]);

        try {
            $exercise = $exercises->generateSideQuest(
                topicPrompt: $data['topic'],
                conceptId: $data['concept_id'] ?? null,
                difficulty: $data['difficulty'] ?? 'Easy',
                drills: (int) ($data['drills'] ?? 5),
                bosses: (int) ($data['bosses'] ?? 1),
                learnerMode: $request->boolean('learner_mode'),
            );
        } catch (\RuntimeException $e) {
            return redirect('/side-quest')->with('error', $e->getMessage());
        }

        return redirect("/exercise/{$exercise->id}");
    }
}
