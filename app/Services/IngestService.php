<?php

namespace App\Services;

use App\Models\Chunk;
use App\Models\Concept;
use App\Models\Topic;
use App\Models\Transcript;
use Illuminate\Support\Facades\DB;

class IngestService
{
    public function __construct(private LlmService $llm)
    {
    }

    /**
     * Split text into ~500-token chunks with ~50-token overlap.
     * Approximation: 1 token ~ 4 chars. Mid-word cuts are harmless for embeddings.
     */
    public function chunk(string $text, int $size = 2000, int $overlap = 200): array
    {
        $text = trim($text);
        $length = mb_strlen($text);

        if ($length === 0) {
            return [];
        }

        $chunks = [];
        $step = $size - $overlap;

        for ($start = 0; $start < $length; $start += $step) {
            $chunks[] = trim(mb_substr($text, $start, $size));

            if ($start + $size >= $length) {
                break;
            }
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    /**
     * Ask the LLM for discrete concepts (and a topic guess when none was picked).
     *
     * @return array{topic_phase_number: int|null, concepts: array<int, array{name: string, summary: string}>}
     */
    public function extractConcepts(string $rawText, ?Topic $topic): array
    {
        $topicLine = $topic
            ? "The transcript's topic is already known: phase {$topic->phase_number} — \"{$topic->name}\". Set topic_phase_number to {$topic->phase_number}."
            : "Pick the best matching topic for this transcript from the roadmap phases:\n"
                .Topic::orderBy('phase_number')->get(['phase_number', 'name'])->toJson();

        $system = 'You extract study concepts from learning transcripts inside a spaced-repetition app. '
            .'Reply with ONLY valid JSON — no markdown fences, no commentary.';

        $user = <<<PROMPT
            Transcript:
            ---
            {$rawText}
            ---

            {$topicLine}

            Extract the discrete concepts the learner actually studied (typically 3-15). Each concept needs a short name and a one-line summary.

            Return exactly this JSON shape:
            {"topic_phase_number": <int>, "concepts": [{"name": "...", "summary": "..."}]}
            PROMPT;

        $parsed = $this->llm->chatJson($system, $user);

        return [
            'topic_phase_number' => $parsed['topic_phase_number'] ?? null,
            'concepts' => array_values(array_filter(
                $parsed['concepts'] ?? [],
                fn ($c) => ! empty($c['name']) && ! empty($c['summary'])
            )),
        ];
    }

    /**
     * Persist a confirmed ingestion: transcript row, embedded chunks,
     * new concepts (next review tomorrow). Existing concept names are
     * linked (left as-is), never duplicated.
     *
     * @param array<int, array{name: string, summary: string}> $concepts
     * @return array{new: int, linked: int, chunks: int}
     */
    public function store(Topic $topic, string $rawText, ?string $sourceNote, array $concepts): array
    {
        $chunks = $this->chunk($rawText);
        $vectors = $this->llm->embedMany($chunks);

        return DB::transaction(function () use ($topic, $rawText, $sourceNote, $concepts, $chunks, $vectors) {
            $transcript = Transcript::create([
                'topic_id' => $topic->id,
                'raw_text' => $rawText,
                'source_note' => $sourceNote,
            ]);

            foreach ($chunks as $i => $text) {
                Chunk::create([
                    'transcript_id' => $transcript->id,
                    'text' => $text,
                    'embedding' => $vectors[$i],
                ]);
            }

            $new = $linked = 0;
            $conceptIds = [];

            foreach ($concepts as $concept) {
                $existing = Concept::whereRaw('lower(name) = ?', [mb_strtolower($concept['name'])])->first();

                if ($existing) {
                    $linked++;
                    $conceptIds[] = $existing->id;
                    continue;
                }

                $conceptIds[] = Concept::create([
                    'topic_id' => $topic->id,
                    'name' => $concept['name'],
                    'summary' => $concept['summary'],
                    'next_review_at' => now()->addDay()->toDateString(),
                ])->id;
                $new++;
            }

            return ['new' => $new, 'linked' => $linked, 'chunks' => count($chunks), 'concept_ids' => $conceptIds];
        });
    }

}
