<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Chunk;
use App\Models\Concept;
use App\Models\DrillAttempt;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Support\LearnerInstructions;
use App\Support\StudyMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExerciseService
{
    /** Spec §5 review ladder, in days. */
    private const LADDER = [1, 3, 7, 14, 30];

    public function __construct(private LlmService $llm, private RoadmapService $roadmap)
    {
    }

    public function todays(): ?Exercise
    {
        return Exercise::whereDate('date', today())->latest('id')->first();
    }

    /** Dashboard button: one Mission per day — returns the existing one instead of regenerating. */
    public function generate(string $difficulty = 'Easy', int $drills = 0, int $bosses = 1, bool $learnerMode = false): Exercise
    {
        if ($existing = $this->todays()) {
            return $existing;
        }

        [$new, $due] = $this->selectConcepts();

        if ($new->isEmpty() && $due->isEmpty()) {
            throw new \RuntimeException('Nothing to practice yet — ingest a transcript first.');
        }

        return $this->storeMission($new, $due, $difficulty, $drills, $bosses, learnerMode: $learnerMode);
    }

    /** Latest not-yet-evaluated mission — what the dashboard resumes when today has none. */
    public function latestPending(): ?Exercise
    {
        return Exercise::where('status', 'pending')->orderByDesc('date')->orderByDesc('id')->first();
    }

    /**
     * Transcript-driven drills (the primary workflow): the just-ingested
     * concepts are the [NEW] set, interconnected with due reviews from earlier
     * transcripts as [REVIEW]. Always creates a fresh mission — several per
     * day is fine for this path.
     */
    public function generateFromConcepts(array $conceptIds, string $difficulty = 'Easy', int $drills = 0, int $bosses = 1, bool $learnerMode = false): Exercise
    {
        $new = Concept::whereIn('id', $conceptIds)->where('retired', false)->get();

        if ($new->isEmpty()) {
            throw new \RuntimeException('No concepts to build drills from.');
        }

        $due = $this->reviewSelection(max(2, (int) ceil($new->count() * 2 / 3)), $conceptIds);

        return $this->storeMission($new, $due, $difficulty, $drills, $bosses, learnerMode: $learnerMode);
    }

    /**
     * Tutor-chat practice saved as a real mission: the tutor's reply becomes
     * mission_md and every existing concept named in it is attached as a
     * review, so submitting the work walks the ladder like any mission.
     */
    public function fromChat(string $missionMd): Exercise
    {
        $haystack = mb_strtolower($missionMd);

        $concepts = Concept::where('retired', false)->get()
            ->filter(fn ($c) => str_contains($haystack, mb_strtolower($c->name)));

        return DB::transaction(function () use ($missionMd, $concepts) {
            $exercise = Exercise::create([
                'date' => today()->toDateString(),
                'mission_md' => $missionMd,
                'status' => 'pending',
            ]);

            foreach ($concepts as $concept) {
                $exercise->concepts()->attach($concept->id, ['role' => 'review']);
            }

            return $exercise;
        });
    }

    private function storeMission(Collection $new, Collection $due, string $difficulty, int $drills, int $bosses, string $type = 'mission', bool $learnerMode = false): Exercise
    {
        if ($drills === 0 && $bosses === 0) {
            throw new \RuntimeException('Choose at least 1 drill or 1 Boss Fight — you can\'t generate a mission with both set to 0.');
        }

        $chunks = $this->retrieveChunks($new->concat($due));
        $systemPrompt = $type === 'side_quest'
            ? resource_path('prompts/side-quest-engine.md')
            : resource_path('prompts/exercise-engine.md');

        $mission = $this->llm->chat(
            StudyMode::renderPrompt(file_get_contents($systemPrompt)),
            $this->buildRequest($new, $due, $chunks, $difficulty, $drills, $bosses).self::learnerModeBlock($learnerMode),
        );

        $exercise = DB::transaction(function () use ($mission, $new, $due, $type) {
            $exercise = Exercise::create([
                'date' => today()->toDateString(),
                'type' => $type,
                'mission_md' => $mission,
                'status' => 'pending',
            ]);

            foreach ($new as $concept) {
                $exercise->concepts()->attach($concept->id, ['role' => 'new']);
            }
            foreach ($due as $concept) {
                $exercise->concepts()->attach($concept->id, ['role' => 'review']);
            }

            return $exercise;
        });

        $this->exportToObsidian($exercise);

        return $exercise;
    }

    /**
     * Side Quest: targeted micro-ladder drills for a specific weakness.
     *
     * The learner describes their confusion (e.g. "nested dict/tuple unpacking")
     * and optionally provides a concept ID. The system matches existing concepts
     * or creates a temporary one under the current phase, then generates a
     * micro-ladder drill sequence focused entirely on that weakness.
     */
    public function generateSideQuest(string $topicPrompt, ?int $conceptId = null, string $difficulty = 'Easy', int $drills = 0, int $bosses = 1, bool $learnerMode = false): Exercise
    {
        if ($drills === 0 && $bosses === 0) {
            throw new \RuntimeException('Choose at least 1 drill or 1 Boss Fight — you can\'t generate a mission with both set to 0.');
        }

        $concepts = collect();

        // If a specific concept was chosen from /concepts, start there.
        if ($conceptId) {
            $concept = Concept::where('id', $conceptId)->where('retired', false)->first();
            if ($concept) {
                $concepts->push($concept);
            }
        }

        // Also match existing concepts whose names appear in the learner's prompt.
        $promptLower = mb_strtolower($topicPrompt);
        $matched = Concept::where('retired', false)->get()
            ->filter(fn ($c) => str_contains($promptLower, mb_strtolower($c->name)))
            ->reject(fn ($c) => $concepts->contains('id', $c->id));
        $concepts = $concepts->concat($matched);

        // If no existing concepts matched, create a temporary one under the
        // current phase so the Side Quest integrates with the spacing ladder.
        if ($concepts->isEmpty()) {
            $phase = $this->roadmap->currentPhaseTopic();
            $concept = Concept::create([
                'topic_id' => $phase->id,
                'name' => str($topicPrompt)->limit(80, '')->toString(),
                'summary' => "Side Quest focus: {$topicPrompt}",
                'next_review_at' => today()->addDay()->toDateString(),
            ]);
            $concepts->push($concept);
        }

        // Build the side quest request with the learner's raw description.
        $chunks = $this->retrieveChunks($concepts);

        $systemPrompt = StudyMode::renderPrompt(file_get_contents(resource_path('prompts/side-quest-engine.md')));
        $mission = $this->llm->chat(
            $systemPrompt,
            $this->buildSideQuestRequest($topicPrompt, $concepts, $chunks, $difficulty, $drills, $bosses).self::learnerModeBlock($learnerMode),
        );

        return DB::transaction(function () use ($mission, $concepts) {
            $exercise = Exercise::create([
                'date' => today()->toDateString(),
                'type' => 'side_quest',
                'mission_md' => $mission,
                'status' => 'pending',
            ]);

            foreach ($concepts as $concept) {
                $exercise->concepts()->attach($concept->id, ['role' => 'side_quest']);
            }

            return $exercise;
        });
    }

    /**
     * Learner mode (checkbox on every generate form): full hand-holding — each
     * Task bullet names the exact line to write and says what it does. Appended
     * to the MODE 1 request so both engine prompts stay untouched.
     */
    public static function learnerModeBlock(bool $on): string
    {
        if (! $on) {
            return '';
        }

        return <<<'REQ'


            Learner mode: ON — full hand-holding. The learner is a beginner who wants to be walked through every line, and this overrides the difficulty's scaffolding level (difficulty still sets how big the problem is).
            For EVERY drill and every Boss Fight step:
            - Keep the one-line description under the heading, then **Setup:**, **Task:**, **Output:**, **Concepts:** as usual.
            - Write **Task:** as a bullet list in the exact order the code is typed, so following the bullets top to bottom builds the working program.
            - Each bullet = the exact line (or tiny block) to write, in inline code, then " — " and one plain sentence on what that line does and why. Example: "- Write `for level in levels:` — this walks through the list one item at a time and puts each item in `level`."
            - Last bullet: "- Run it — you should see:" followed by the expected output.
            - Short sentences, no jargon without a two-word explanation in brackets.
            The Solutions section stays as normal (simple solution first, explained).
            REQ;
    }

    /** Build the MODE 1 request for a Side Quest (differs from regular missions). */
    private function buildSideQuestRequest(string $topicPrompt, Collection $concepts, Collection $chunks, string $difficulty, int $drills, int $bosses): string
    {
        $phase = $this->roadmap->currentPhaseTopic();
        $week = $this->roadmap->currentWeek();

        $conceptLines = $concepts
            ->map(fn ($c) => "- (id {$c->id}, lapses={$c->lapses}) {$c->name}: {$c->summary}")
            ->implode("\n");

        $notes = StruggleNote::where('resolved', false)
            ->whereIn('concept_id', $concepts->pluck('id'))
            ->get()
            ->map(fn ($n) => "- concept id {$n->concept_id}: {$n->note} (weight {$n->weight})")
            ->implode("\n") ?: '(none)';

        $sources = $chunks->values()
            ->map(fn ($chunk, $i) => '### Chunk '.($i + 1)."\n{$chunk->text}")
            ->implode("\n\n") ?: '(none)';

        $instructions = LearnerInstructions::get() ?: '(none)';

        $date = today()->toDateString();

        return <<<REQ
            MODE 1 — GENERATE SIDE QUEST

            Today's date: {$date}
            Learner's current roadmap phase: Phase {$phase->phase_number} — {$phase->name} (study week {$week})
            Requested difficulty: {$difficulty}
            Requested drills: {$drills}
            Requested Boss Fights: {$bosses}

            The learner's specific weakness/confusion:
            ---
            {$topicPrompt}
            ---

            Matching concepts in the learner's knowledge base:
            {$conceptLines}

            Learner's personal instructions:
            {$instructions}

            Active struggle notes for these concepts:
            {$notes}

            Source chunks (what the learner actually studied):
            {$sources}
            REQ;
    }

    /**
     * Spec §5 composition rule: ~60% new / ~40% review.
     * "New" = never successfully reviewed (interval_days 0) — repeats daily until
     * evaluated, which is the point of retrieval practice. ALL new concepts are
     * included (missions scale up, never drop concepts); review count is sized to
     * ~2/3 of the new count (floor of 2) to hit the 60/40 ratio.
     * Nothing new -> up to 8 strictly-due reviews; nothing at all -> empty.
     *
     * @return array{0: Collection<int, Concept>, 1: Collection<int, Concept>}
     */
    public function selectConcepts(): array
    {
        $new = Concept::where('retired', false)
            ->where('interval_days', 0)
            ->orderByDesc('first_seen_at')
            ->get();

        return $new->isEmpty()
            ? [$new, $this->reviewSelection(8, dueOnly: true)]
            : [$new, $this->reviewSelection(max(2, (int) ceil($new->count() * 2 / 3)))];
    }

    /**
     * Review picks for a mission: overdue concepts first (most lapses, then most
     * overdue). Interleaving must hold even when nothing is technically due —
     * past topics always reappear — so the remaining slots are backfilled with
     * already-learned concepts whose next review is nearest (unless $dueOnly).
     */
    private function reviewSelection(int $cap, array $excludeIds = [], bool $dueOnly = false): Collection
    {
        $reviewed = Concept::where('retired', false)
            ->where('interval_days', '>', 0)
            ->whereNotIn('id', $excludeIds)
            ->get();

        // Concepts the learner names in their personal instructions ("list &
        // dict methods are slipping") jump the queue — feedback must actually
        // pull those concepts into missions, due or not.
        // ponytail: bare word-in-name matching; upgrade to embeddings if it misfires.
        $words = $this->feedbackWords();

        [$flagged, $rest] = $reviewed->partition(
            fn ($c) => $words->contains(fn ($w) => str_contains(mb_strtolower($c->name), $w))
        );

        [$due, $upcoming] = $rest->partition(
            fn ($c) => $c->next_review_at !== null && $c->next_review_at->lte(today())
        );

        $picked = $flagged->sortBy([['lapses', 'desc'], ['next_review_at', 'asc']])
            ->concat($due->sortBy([['lapses', 'desc'], ['next_review_at', 'asc']]));

        if (! $dueOnly) {
            $picked = $picked->concat($upcoming->sortBy('next_review_at'));
        }

        return $picked->take($cap)->values();
    }

    /** Significant words (>= 4 chars, minus filler) from the learner's personal instructions. */
    private function feedbackWords(): Collection
    {
        $filler = ['with', 'that', 'this', 'from', 'have', 'when', 'will', 'your', 'them', 'then',
            'than', 'into', 'keep', 'more', 'like', 'what', 'until', 'stop', 'make', 'want', 'need',
            'only', 'also', 'just', 'been', 'over', 'some', 'time', 'they', 'about', 'please'];

        return collect(preg_split('/[^a-z0-9_]+/', mb_strtolower(LearnerInstructions::get())))
            ->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $filler, true))
            ->values();
    }

    /**
     * Top chunks per concept by cosine similarity between the concept's
     * name+summary embedding and stored chunk embeddings.
     * ponytail: full O(concepts x chunks) scan in PHP — fine for hundreds of
     * chunks; move to sqlite-vec if the corpus ever makes this slow.
     */
    public function retrieveChunks(Collection $concepts, int $perConcept = 2): Collection
    {
        $chunks = Chunk::all();

        if ($chunks->isEmpty() || $concepts->isEmpty()) {
            return collect();
        }

        $vectors = $this->llm->embedMany(
            $concepts->map(fn ($c) => "{$c->name}: {$c->summary}")->values()->all()
        );

        $picked = collect();

        foreach ($concepts->values() as $i => $concept) {
            $picked = $picked->concat(
                $chunks->sortByDesc(fn ($chunk) => $this->cosine($vectors[$i], $chunk->embedding))
                    ->take($perConcept)
            );
        }

        return $picked->unique('id')->values();
    }

    public function cosine(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $normA += $v * $v;
            $normB += $b[$i] * $b[$i];
        }

        return ($normA && $normB) ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
    }

    /**
     * MODE 2 round trip: send Mission + pasted result + concept list, parse the
     * structured evaluation, store the attempt, walk every scored concept up or
     * down the ladder, and record struggle notes.
     */
    public function evaluate(Exercise $exercise, string $pasted): Attempt
    {
        $concepts = $exercise->concepts()->get()->keyBy('id');

        $systemPrompt = $exercise->type === 'side_quest'
            ? resource_path('prompts/side-quest-engine.md')
            : resource_path('prompts/exercise-engine.md');

        $eval = $this->llm->chatJson(
            StudyMode::renderPrompt(file_get_contents($systemPrompt)),
            $this->buildEvalRequest($exercise, $pasted),
        );

        // Only trust scores for concepts actually in this exercise; a concept
        // the LLM failed to score is skipped rather than punished.
        $scores = collect($eval['concept_scores'] ?? [])
            ->filter(fn ($s) => isset($s['concept_id'], $s['score']) && $concepts->has($s['concept_id']))
            ->map(fn ($s) => ['concept_id' => (int) $s['concept_id'], 'score' => max(0, min(100, (int) $s['score']))]);

        $suggested = collect($eval['suggested_struggle_notes'] ?? [])
            ->filter(fn ($n) => isset($n['concept_id'], $n['note']) && $concepts->has($n['concept_id']));

        $attempt = DB::transaction(function () use ($exercise, $pasted, $eval, $scores, $suggested, $concepts) {
            $attempt = Attempt::create([
                'exercise_id' => $exercise->id,
                'pasted_result' => $pasted,
                'evaluation_md' => $eval['feedback_md'] ?? '(no feedback returned)',
                'score' => $scores->isEmpty() ? null : (int) round($scores->avg('score')),
            ]);

            foreach ($suggested as $note) {
                StruggleNote::create([
                    'concept_id' => $note['concept_id'],
                    'note' => $note['note'],
                    'weight' => max(1, min(3, (int) ($note['weight'] ?? 1))),
                ]);
            }

            foreach ($scores as $s) {
                $this->applyLadder($concepts[$s['concept_id']], $s['score'], $suggested->pluck('concept_id')->contains($s['concept_id']));
            }

            $exercise->update(['status' => 'evaluated']);

            return $attempt;
        });

        $this->exportToObsidian($exercise->fresh());

        return $attempt;
    }

    /**
     * Quick inline check of ONE drill from the exercise page. Scores only that
     * drill (against its published solution) and keeps the latest answer per
     * drill. It never touches the ladder — the full evaluation does that, and
     * it receives these inline answers as part of the submission.
     */
    public function checkDrill(Exercise $exercise, string $key, string $answer): DrillAttempt
    {
        $drill = app(MissionReference::class)->extractSection($exercise->mission_md, $key[0], (int) substr($key, 1));

        if ($drill === null) {
            throw new \InvalidArgumentException("Mission M{$exercise->id} has no {$key}.");
        }

        $eval = $this->llm->chatJson(
            StudyMode::renderPrompt(file_get_contents(resource_path('prompts/drill-check.md'))),
            "Drill (with its published solution when available):\n---\n## {$drill}\n---\n\nLearner's answer:\n---\n{$answer}\n---",
        );

        return DrillAttempt::updateOrCreate(
            ['exercise_id' => $exercise->id, 'drill_key' => $key],
            [
                'answer' => $answer,
                'score' => isset($eval['score']) ? max(0, min(100, (int) $eval['score'])) : null,
                'feedback_md' => $eval['feedback_md'] ?? '(no feedback returned)',
            ],
        );
    }

    /** Best-effort: a broken vault export should never fail a mission or evaluation. */
    private function exportToObsidian(Exercise $exercise): void
    {
        if (! config('obsidian.enabled')) {
            return;
        }

        rescue(fn () => app(ObsidianExportService::class)->export($exercise), report: true);
    }

    /**
     * Spec §5 spacing algorithm, exactly: score >= 70 promotes to the next
     * ladder rung (stays at 30 past the top); < 70 is a lapse — increment
     * lapses, halve the interval (min 1 day), and create/boost a struggle
     * note (skipped when the evaluation already suggested one).
     */
    public function applyLadder(Concept $concept, int $score, bool $hasFreshNote = false): void
    {
        if ($score >= 70) {
            $interval = collect(self::LADDER)->first(fn ($d) => $d > $concept->interval_days) ?? 30;
        } else {
            $interval = max(1, intdiv($concept->interval_days, 2));
            $concept->lapses++;

            if (! $hasFreshNote) {
                $existing = StruggleNote::where('concept_id', $concept->id)
                    ->where('resolved', false)->latest('id')->first();

                $existing
                    ? $existing->update(['weight' => min(3, $existing->weight + 1)])
                    : StruggleNote::create([
                        'concept_id' => $concept->id,
                        'note' => "Scored {$score}/100 on evaluation — needs another pass.",
                        'weight' => 1,
                    ]);
            }
        }

        $concept->interval_days = $interval;
        $concept->next_review_at = today()->addDays($interval)->toDateString();
        $concept->save();
    }

    /** Learner's own free-text note, attached to every concept of the exercise with weight 2. */
    public function addOwnNote(Exercise $exercise, string $note): int
    {
        $ids = $exercise->concepts()->pluck('concepts.id');

        foreach ($ids as $id) {
            StruggleNote::create(['concept_id' => $id, 'note' => $note, 'weight' => 2]);
        }

        return $ids->count();
    }

    private function buildEvalRequest(Exercise $exercise, string $pasted): string
    {
        $conceptLines = $exercise->concepts()->get()
            ->map(fn ($c) => "- (concept_id {$c->id}) [{$c->pivot->role}] {$c->name}: {$c->summary}")
            ->implode("\n");

        // The evaluator is stateless; prior feedback carries the Boss Fight
        // protocol phase ("Skeleton approved", last validated layer).
        $previous = $exercise->attempts()->orderByDesc('id')->take(5)->get()
            ->reverse()->values()
            ->map(fn ($a) => "---\n{$a->evaluation_md}")
            ->implode("\n") ?: '(none — this is the first attempt)';

        // Drills solved inline on the mission page are part of this submission,
        // so they are never treated as skipped.
        $inline = $exercise->drillAttempts()->orderBy('id')->get()
            ->map(fn ($d) => "### {$d->drill_key} (quick-check score: ".($d->score ?? '—').")\n{$d->answer}")
            ->implode("\n\n") ?: '(none)';
        $pasted = trim($pasted) !== '' ? $pasted : '(nothing pasted — only the inline drills below)';

        return <<<REQ
            MODE 2 — EVALUATE SUBMISSION

            Original Mission:
            ---
            {$exercise->mission_md}
            ---

            Learner's pasted result:
            ---
            {$pasted}
            ---

            Drills the learner solved inline on the mission page (part of this submission — D2 = Drill 2):
            ---
            {$inline}
            ---

            Previous evaluations of this mission (oldest first):
            {$previous}

            Concept list (use these concept_id values in your JSON):
            {$conceptLines}
            REQ;
    }

    /** MODE 1 request per resources/prompts/exercise-engine.md. */
    private function buildRequest(Collection $new, Collection $due, Collection $chunks, string $difficulty, int $drills, int $bosses): string
    {
        $phase = $this->roadmap->currentPhaseTopic();
        $week = $this->roadmap->currentWeek();

        $concepts = $new->map(fn ($c) => "- [NEW] (id {$c->id}) {$c->name}: {$c->summary}")
            ->concat($due->map(fn ($c) => "- [REVIEW, lapses={$c->lapses}] (id {$c->id}) {$c->name}: {$c->summary}"))
            ->implode("\n");

        $notes = StruggleNote::where('resolved', false)
            ->whereIn('concept_id', $new->concat($due)->pluck('id'))
            ->get()
            ->map(fn ($n) => "- concept id {$n->concept_id}: {$n->note} (weight {$n->weight})")
            ->implode("\n") ?: '(none)';

        $sources = $chunks->values()
            ->map(fn ($chunk, $i) => '### Chunk '.($i + 1)."\n{$chunk->text}")
            ->implode("\n\n") ?: '(none)';

        $instructions = LearnerInstructions::get() ?: '(none)';

        $date = today()->toDateString();

        return <<<REQ
            MODE 1 — GENERATE MISSION

            Today's date: {$date}
            Learner's current roadmap phase: Phase {$phase->phase_number} — {$phase->name} (study week {$week})
            Requested difficulty: {$difficulty}
            Requested drills: {$drills}
            Requested Boss Fights: {$bosses}

            Concepts:
            {$concepts}

            Learner's personal instructions:
            {$instructions}

            Active struggle notes:
            {$notes}

            Source chunks (what the learner actually studied):
            {$sources}
            REQ;
    }
}
