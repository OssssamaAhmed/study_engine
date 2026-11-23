<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Chunk;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Support\LearnerInstructions;
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
    public function generate(string $difficulty = 'Easy', int $drills = 5, int $bosses = 1): Exercise
    {
        if ($existing = $this->todays()) {
            return $existing;
        }

        [$new, $due] = $this->selectConcepts();

        if ($new->isEmpty() && $due->isEmpty()) {
            throw new \RuntimeException('Nothing to practice yet — ingest a transcript first.');
        }

        return $this->storeMission($new, $due, $difficulty, $drills, $bosses);
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
    public function generateFromConcepts(array $conceptIds, string $difficulty = 'Easy', int $drills = 5, int $bosses = 1): Exercise
    {
        $new = Concept::whereIn('id', $conceptIds)->where('retired', false)->get();

        if ($new->isEmpty()) {
            throw new \RuntimeException('No concepts to build drills from.');
        }

        $due = $this->reviewSelection(max(2, (int) ceil($new->count() * 2 / 3)), $conceptIds);

        return $this->storeMission($new, $due, $difficulty, $drills, $bosses);
    }

    private function storeMission(Collection $new, Collection $due, string $difficulty, int $drills, int $bosses): Exercise
    {
        $chunks = $this->retrieveChunks($new->concat($due));
        $mission = $this->llm->chat(
            file_get_contents(resource_path('prompts/exercise-engine.md')),
            $this->buildRequest($new, $due, $chunks, $difficulty, $drills, $bosses),
        );

        return DB::transaction(function () use ($mission, $new, $due) {
            $exercise = Exercise::create([
                'date' => today()->toDateString(),
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

        [$due, $upcoming] = $reviewed->partition(
            fn ($c) => $c->next_review_at !== null && $c->next_review_at->lte(today())
        );

        $picked = $due->sortBy([['lapses', 'desc'], ['next_review_at', 'asc']]);

        if (! $dueOnly) {
            $picked = $picked->concat($upcoming->sortBy('next_review_at'));
        }

        return $picked->take($cap)->values();
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

        $eval = $this->llm->chatJson(
            file_get_contents(resource_path('prompts/exercise-engine.md')),
            $this->buildEvalRequest($exercise, $pasted),
        );

        // Only trust scores for concepts actually in this exercise; a concept
        // the LLM failed to score is skipped rather than punished.
        $scores = collect($eval['concept_scores'] ?? [])
            ->filter(fn ($s) => isset($s['concept_id'], $s['score']) && $concepts->has($s['concept_id']))
            ->map(fn ($s) => ['concept_id' => (int) $s['concept_id'], 'score' => max(0, min(100, (int) $s['score']))]);

        $suggested = collect($eval['suggested_struggle_notes'] ?? [])
            ->filter(fn ($n) => isset($n['concept_id'], $n['note']) && $concepts->has($n['concept_id']));

        return DB::transaction(function () use ($exercise, $pasted, $eval, $scores, $suggested, $concepts) {
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
