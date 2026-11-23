<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ExerciseService;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function show(Exercise $exercise)
    {
        return view('exercise.show', [
            'exercise' => $exercise,
            'concepts' => $exercise->concepts()->get(),
            'attempt' => $exercise->attempts()->latest('id')->first(),
        ]);
    }

    public function submit(Request $request, Exercise $exercise, ExerciseService $service)
    {
        set_time_limit(0);

        $data = $request->validate(['pasted_result' => ['required', 'string']]);

        $service->evaluate($exercise, $data['pasted_result']);

        return redirect("/exercise/{$exercise->id}")->with('status', 'Evaluation complete — ladder updated.');
    }

    public function reset(Exercise $exercise)
    {
        $exercise->attempts()->delete();
        $exercise->update(['status' => 'pending']);

        return redirect("/exercise/{$exercise->id}")->with('status', 'Evaluation cleared — submit again.');
    }

    public function note(Request $request, Exercise $exercise, ExerciseService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $count = $service->addOwnNote($exercise, $data['note']);

        return redirect("/exercise/{$exercise->id}")->with('status', "Struggle note attached to {$count} concepts.");
    }
}
