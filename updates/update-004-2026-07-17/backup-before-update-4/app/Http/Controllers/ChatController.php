<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StruggleNote;
use App\Services\LlmService;
use App\Services\RoadmapService;
use App\Support\LearnerInstructions;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function show()
    {
        return view('chat', ['messages' => session('chat', [])]);
    }

    public function send(Request $request, LlmService $llm, RoadmapService $roadmap)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);

        $messages = session('chat', []);
        $messages[] = ['role' => 'learner', 'content' => $data['message']];

        // Drivers take a single user string, so the running conversation is
        // folded into the request as a labelled transcript.
        $transcript = collect($messages)
            ->map(fn ($m) => strtoupper($m['role']).":\n{$m['content']}")
            ->implode("\n\n");

        try {
            $reply = $llm->chat(
                file_get_contents(resource_path('prompts/tutor-chat.md'))."\n\n".$this->learnerState($roadmap),
                $transcript,
            );
        } catch (\Throwable $e) {
            session(['chat' => $messages]);

            return redirect('/chat')->with('status', "The model call failed — try again. ({$e->getMessage()})");
        }

        $messages[] = ['role' => 'tutor', 'content' => $reply];

        // ponytail: keep the last 30 entries so the prompt never grows unbounded
        session(['chat' => array_slice($messages, -30)]);

        return redirect('/chat');
    }

    public function clear()
    {
        session()->forget('chat');

        return redirect('/chat');
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

        $struggles = StruggleNote::with('concept')
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
