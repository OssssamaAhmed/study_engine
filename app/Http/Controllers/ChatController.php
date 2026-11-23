<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StruggleNote;
use App\Services\ExerciseService;
use App\Services\LlmService;
use App\Services\MissionReference;
use App\Services\RoadmapService;
use App\Support\LearnerInstructions;
use App\Support\StudyMode;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function show()
    {
        return view('chat', ['messages' => session($this->chatKey(), [])]);
    }

    public function send(Request $request, LlmService $llm, RoadmapService $roadmap, MissionReference $refs)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:20000']]);

        $messages = session($this->chatKey(), []);
        $messages[] = ['role' => 'learner', 'content' => $data['message']];

        // Drivers take a single user string, so the running conversation is
        // folded into the request as a labelled transcript.
        $transcript = collect($messages)
            ->map(fn ($m) => strtoupper($m['role']).":\n{$m['content']}")
            ->implode("\n\n");

        // M3 / M3 D2 / B1 style references: fetch the actual mission text so
        // the learner never pastes it by hand.
        if ($referenced = $refs->resolve($data['message'])) {
            $transcript .= "\n\nREFERENCED MATERIAL (fetched by the app from the mission archive):\n---\n{$referenced}\n---";
        }

        try {
            $reply = $llm->chat(
                StudyMode::renderPrompt(file_get_contents(resource_path('prompts/tutor-chat.md')))."\n\n".$this->learnerState($roadmap),
                $transcript,
            );
        } catch (\Throwable $e) {
            session([$this->chatKey() => $messages]);

            return redirect('/chat')->with('status', "The model call failed — try again. ({$e->getMessage()})");
        }

        $messages[] = ['role' => 'tutor', 'content' => $reply];

        // ponytail: keep the last 30 entries so the prompt never grows unbounded
        session([$this->chatKey() => array_slice($messages, -30)]);

        return redirect('/chat');
    }

    /** Turn a tutor practice reply into a real mission — submit/evaluate like any other. */
    public function saveMission(Request $request, ExerciseService $service)
    {
        $data = $request->validate(['index' => ['required', 'integer', 'min:0']]);

        $message = session($this->chatKey(), [])[$data['index']] ?? null;

        if (! $message || $message['role'] !== 'tutor') {
            return redirect('/chat')->with('status', 'That message is no longer in the conversation.');
        }

        $exercise = $service->fromChat($message['content']);

        return redirect("/exercise/{$exercise->id}")
            ->with('status', "Saved as mission M{$exercise->id} — submit your work here and it updates your review schedule.");
    }

    public function clear()
    {
        session()->forget($this->chatKey());

        return redirect('/chat');
    }

    /** Session key is namespaced per track so switching tracks never continues the other track's conversation. */
    private function chatKey(): string
    {
        return 'chat_'.StudyMode::current();
    }

    /** Live learner context appended to the system prompt, bounded to 10 items each. */
    private function learnerState(RoadmapService $roadmap): string
    {
        $phase = $roadmap->currentPhaseTopic();

        $due = Concept::where('retired', false)
            ->whereDate('next_review_at', '<=', today())
            ->orderBy('next_review_at')
            ->take(10)
            ->pluck('name')
            ->implode(', ') ?: '(none)';

        // ->whereHas('concept') is load-bearing: struggle_notes has no mode
        // column of its own (it scopes transitively through concept_id), so
        // without this the top-10-by-weight notes would leak across tracks.
        $struggles = StruggleNote::whereHas('concept')
            ->with('concept')
            ->where('resolved', false)
            ->orderByDesc('weight')
            ->take(10)
            ->get()
            ->map(fn ($n) => "- {$n->concept->name}: {$n->note}")
            ->implode("\n") ?: '(none)';

        $instructions = LearnerInstructions::get() ?: '(none)';

        return <<<STATE
            Learner's live state:
            - Current roadmap phase: Phase {$phase->phase_number} — {$phase->name}
            - Concepts due for review: {$due}
            - Personal instructions (follow them):
            {$instructions}
            - Active struggle notes:
            {$struggles}
            STATE;
    }
}
