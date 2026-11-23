<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ExerciseService;
use App\Services\MissionReference;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExerciseController extends Controller
{
    public function show(Exercise $exercise, MissionReference $missions)
    {
        $sections = $missions->sections($exercise->mission_md);
        $drillKeys = collect($sections)->pluck('key')->filter(fn ($k) => $k && $k[0] === 'D')->values();

        return view('exercise.show', [
            'exercise' => $exercise,
            'concepts' => $exercise->concepts()->get(),
            'attempt' => $exercise->attempts()->latest('id')->first(),
            'sections' => $sections,
            'drillKeys' => $drillKeys,
            'drillAttempts' => $exercise->drillAttempts()->get()->keyBy('drill_key'),
        ]);
    }

    public function submit(Request $request, Exercise $exercise, ExerciseService $service)
    {
        set_time_limit(0);

        $data = $request->validate(['pasted_result' => ['nullable', 'string']]);
        $pasted = (string) ($data['pasted_result'] ?? '');

        // Inline-checked drills count as a submission, so pasting is optional then.
        if (trim($pasted) === '' && ! $exercise->drillAttempts()->exists()) {
            return back()->withErrors(['pasted_result' => 'Paste your result, or check at least one drill inline first.']);
        }

        $service->evaluate($exercise, $pasted);

        return redirect("/exercise/{$exercise->id}")->with('status', 'Evaluation complete — ladder updated.');
    }

    /** Inline quick-check of one drill — JSON for the page script, redirect without JS. */
    public function checkDrill(Request $request, Exercise $exercise, string $key, ExerciseService $service)
    {
        set_time_limit(0);

        abort_unless(preg_match('/^D\d{1,2}$/', $key), 404);
        $data = $request->validate(['answer' => ['required', 'string', 'max:20000']]);

        try {
            $drill = $service->checkDrill($exercise, $key, $data['answer']);
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Could not check this drill: '.$e->getMessage()], 422)
                : redirect("/exercise/{$exercise->id}#drill-{$key}")->with('error', 'Could not check this drill: '.$e->getMessage());
        }

        if (! $request->expectsJson()) {
            return redirect("/exercise/{$exercise->id}#drill-{$key}");
        }

        return response()->json([
            'key' => $key,
            'score' => $drill->score,
            'feedback_html' => (string) Str::markdown($drill->feedback_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }

    public function reset(Exercise $exercise)
    {
        $exercise->attempts()->delete();
        $exercise->drillAttempts()->delete();
        $exercise->update(['status' => 'pending']);

        return redirect("/exercise/{$exercise->id}")->with('status', 'Evaluation cleared — submit again.');
    }

    public function note(Request $request, Exercise $exercise, ExerciseService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $count = $service->addOwnNote($exercise, $data['note']);

        return redirect("/exercise/{$exercise->id}")->with('status', "Struggle note attached to {$count} concepts.");
    }

    public function addMore(Request $request, Exercise $exercise, ExerciseService $service)
    {
        set_time_limit(0);

        $data = $request->validate([
            'difficulty' => ['nullable', 'in:Easy,Medium,Hard'],
            'drills'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'bosses'     => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);

        $conceptIds = $exercise->concepts()->pluck('concepts.id')->all();

        try {
            $new = $service->generateFromConcepts(
                $conceptIds,
                $data['difficulty'] ?? 'Easy',
                (int) ($data['drills'] ?? 5),
                (int) ($data['bosses'] ?? 1),
            );
        } catch (\Throwable $e) {
            return redirect("/exercise/{$exercise->id}")
                ->with('status', 'Could not generate follow-up: '.$e->getMessage());
        }

        return redirect("/exercise/{$new->id}")
            ->with('status', 'Follow-up mission generated — go get it!');
    }
}
