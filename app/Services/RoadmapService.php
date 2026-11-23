<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\PhaseProgress;
use App\Models\Topic;
use App\Support\StudyMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RoadmapService
{
    private const LARAVEL_ROADMAP_FILE = 'Laravelstudy.json';

    /**
     * Read the roadmap JSON fresh from disk on every call, so edits to the
     * file take effect on the next resync without any code change.
     */
    public function load(): array
    {
        return json_decode(File::get($this->activeFilePath()), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * On-disk path of the roadmap JSON for the active track. Laravel Web
     * targets storage/app/roadmap/Laravelstudy.json explicitly — a missing
     * file is a real setup error, not something to silently fall back from.
     * AI Security keeps the original "whatever JSON is in the folder" glob,
     * excluding the Laravel file so both can sit side by side.
     */
    public function activeFilePath(): string
    {
        if (StudyMode::current() === StudyMode::LARAVEL_WEB) {
            $path = storage_path('app/roadmap/'.self::LARAVEL_ROADMAP_FILE);

            if (! File::exists($path)) {
                throw new \RuntimeException('Laravel Web roadmap not found — expected storage/app/roadmap/'.self::LARAVEL_ROADMAP_FILE.'.');
            }

            return $path;
        }

        $files = collect(File::glob(storage_path('app/roadmap/*.json')))
            ->reject(fn ($f) => basename($f) === self::LARAVEL_ROADMAP_FILE)
            ->values();

        if ($files->isEmpty()) {
            $files = collect(File::glob(base_path('*.json')));
        }

        if ($files->isEmpty()) {
            throw new \RuntimeException('No roadmap JSON found in storage/app/roadmap/ or base directory.');
        }

        return $files->first();
    }

    /** @return array<int, array{phase_number:int, name:string, week_start:int, week_end:int}> */
    public function phases(): array
    {
        return array_map(function (array $phase) {
            [$start, $end] = $this->parseWeeks((string) $phase['weeks']);

            return [
                'phase_number' => (int) $phase['phase'],
                'name' => $phase['title'],
                'week_start' => $start,
                'week_end' => $end,
            ];
        }, $this->load()['phases']);
    }

    /** Find a specific phase's raw data from JSON. */
    public function getPhase(int $phaseNumber): ?array
    {
        $data = $this->load();
        foreach ($data['phases'] as $p) {
            if ((int) $p['phase'] === $phaseNumber) {
                return $p;
            }
        }

        return null;
    }

    /** Retrieve all checkpoints from the roadmap JSON. */
    public function getCheckpoints(): array
    {
        $data = $this->load();

        return $data['checkpoints'] ?? [];
    }

    /** Retrieve all certifications from the roadmap JSON. */
    public function getCertifications(): array
    {
        $data = $this->load();

        return $data['certifications'] ?? [];
    }

    /**
     * Determine the highest phase number the learner has actively ingested content or practiced concepts for.
     */
    public function highestActivePhase(): int
    {
        $topicIds = Concept::distinct()->pluck('topic_id')->filter()->all();
        if (empty($topicIds)) {
            return 1;
        }

        $highest = Topic::whereIn('id', $topicIds)->max('phase_number');

        return max(1, (int) $highest);
    }

    /**
     * Map Checkpoint index (0 to 3) to phase ranges in the curriculum.
     */
    public function getCheckpointPhaseRange(int $index): array
    {
        $ranges = [
            0 => ['start' => 1, 'end' => 5, 'name' => 'Phases 1–5 (Python, DSA, EDA, SQL, ML)'],
            1 => ['start' => 6, 'end' => 11, 'name' => 'Phases 6–11 (Deep Learning, Cloud, FastAPI, RAG)'],
            2 => ['start' => 12, 'end' => 15, 'name' => 'Phases 12–15 (Fine-tuning, Evals, Agentic AI, Anthropic)'],
            3 => ['start' => 16, 'end' => 21, 'name' => 'Phases 16–21 (Agentic Security, MLOps, Economics, System Design)'],
        ];

        return $ranges[$index] ?? ['start' => 1, 'end' => 21, 'name' => 'Full Curriculum'];
    }

    /**
     * STRICTLY CONTENT-BASED & PROGRESS-GATED:
     * Checkpoint 1 (Phases 1-5) triggers ONLY when the learner reaches or completes Phase 5.
     * Checkpoint 2 (Phases 6-11) triggers ONLY when the learner reaches or completes Phase 11.
     * Checkpoint 3 (Phases 12-15) triggers ONLY when the learner reaches or completes Phase 15.
     * Checkpoint 4 (Phases 16-21) triggers ONLY when the learner reaches or completes Phase 21.
     * Zero premature exam triggers when learner is still early in a checkpoint phase range (e.g. Phase 1).
     */
    public function getUpcomingExams(?int $currentWeek = null): array
    {
        $activePhase = $this->highestActivePhase();
        $upcoming = [];

        foreach ($this->getCheckpoints() as $index => $cp) {
            $range = $this->getCheckpointPhaseRange($index);
            $title = $cp['title'] ?? $cp['name'] ?? ('Checkpoint ' . ($index + 1));
            $targetEndPhase = $range['end'];

            // Check if final phase of this checkpoint range is completed in phase_progress
            $finalPhaseProgress = PhaseProgress::where('phase_number', $targetEndPhase)->first();
            $finalPhaseCompleted = $finalPhaseProgress && in_array($finalPhaseProgress->status, [PhaseProgress::COMPLETED, PhaseProgress::MOVED_ON], true);

            // Checkpoint exam prep notification triggers ONLY when learner reaches the final phase of that checkpoint range OR completes it
            $isNear = ($activePhase >= $targetEndPhase) || $finalPhaseCompleted;

            if ($isNear) {
                // Count total required topics in this phase range
                $requiredTopicsCount = 0;
                for ($p = $range['start']; $p <= $range['end']; $p++) {
                    $requiredTopicsCount += count($this->getRequiredTopicsForPhase($p));
                }

                $topics = Topic::whereBetween('phase_number', [$range['start'], $range['end']])->pluck('id');
                $ingestedCount = Concept::whereIn('topic_id', $topics)->count();
                $coveragePct = $requiredTopicsCount > 0 ? min(100, (int) round(($ingestedCount / $requiredTopicsCount) * 100)) : 0;

                $upcoming[] = [
                    'type' => 'checkpoint',
                    'id' => 'cp_' . ($index + 1),
                    'title' => $title,
                    'active_phase' => $activePhase,
                    'phase_range' => $range['name'],
                    'target_phase' => $targetEndPhase,
                    'coverage_pct' => $coveragePct,
                    'ingested_count' => $ingestedCount,
                    'required_count' => $requiredTopicsCount,
                    'data' => $cp,
                ];
            }
        }

        $certPhaseMap = [
            'Claude Certified Architect - Foundations (CCA-F)' => 15,
            'Anthropic Academy course certificates' => 15,
            'Hugging Face AI Agents Course Certificate' => 14,
            'Hugging Face MCP Course Certificate' => 15,
            'AWS Certified Machine Learning Engineer - Associate' => 17,
            'NVIDIA Agentic AI Professional (NCP-AAI)' => 14,
            'Hugging Face Deep RL Course Certificate' => 6,
            'Google Cloud Professional ML Engineer' => 17,
        ];

        foreach ($this->getCertifications() as $index => $cert) {
            $name = $cert['name'];
            $targetPhase = $certPhaseMap[$name] ?? 15;

            // Cert near ONLY when learner reaches or completes the specific target phase for that cert
            if ($activePhase >= $targetPhase) {
                $upcoming[] = [
                    'type' => 'certification',
                    'id' => 'cert_' . ($index + 1),
                    'title' => $name,
                    'provider' => $cert['provider'] ?? '',
                    'target_phase' => $targetPhase,
                    'active_phase' => $activePhase,
                    'when' => $cert['when'] ?? "Phase {$targetPhase}",
                    'data' => $cert,
                ];
            }
        }

        return $upcoming;
    }

    /** Key concepts/topics expected for a given phase derived from notes & resources. */
    public function getRequiredTopicsForPhase(int $phaseNumber): array
    {
        $phase = $this->getPhase($phaseNumber);
        if (! $phase) {
            return [];
        }

        // This checklist is AI-Security-specific; a second track's phases
        // reuse the same numbers 1..N, so without this gate a Laravel Web
        // phase 3 would wrongly inherit AI Security phase 3's checklist.
        if (StudyMode::current() !== StudyMode::AI_SECURITY) {
            return [$phase['title']];
        }

        // Expected core topics for phases to validate completion
        $defaults = [
            1 => ['for loops', 'functions', 'error handling', 'virtual environments', 'git basics', 'pip'],
            2 => ['big-o intuition', 'hash maps', 'trees', 'graphs', 'two pointers'],
            3 => ['pandas', 'numpy', 'data visualization', 'data cleaning', 'groupby'],
            4 => ['sql joins', 'subqueries', 'window functions', 'ctes', 'data aggregation'],
            5 => ['classic algorithms', 'feature engineering', 'model evaluation', 'regression', 'classification'],
            6 => ['neural networks', 'anns', 'cnns', 'transformers', 'backprop'],
            7 => ['ec2', 's3', 'lambda', 'iam', 'cloud deployment'],
            8 => ['fastapi', 'rest apis', 'async python', 'pydantic', 'endpoint security'],
            9 => ['prompt engineering', 'context engineering', 'harness engineering', 'system prompts'],
            10 => ['langchain', 'generative ai', 'llm integration', 'chaining'],
            11 => ['vector databases', 'rag', 'embeddings', 'similarity search'],
            12 => ['fine-tuning', 'open-model serving', 'ollama', 'vllm'],
            13 => ['evals', 'llm testing', 'promptfoo', 'langsmith'],
            14 => ['agentic ai', 'multi-agent frameworks', 'crewai', 'langgraph', 'a2a protocol'],
            15 => ['anthropic API', 'claude code', 'agent sdk', 'mcp'],
            16 => ['agentic ai security', 'owasp agentic top 10', 'memory poisoning', 'prompt injection'],
            17 => ['mlops', 'ci/cd', 'github actions', 'technical writing'],
            18 => ['docker', 'llm observability', 'phoenix', 'telemetry'],
            19 => ['n8n', 'ai workflow automation', 'webhooks'],
            20 => ['prompt caching', 'semantic caching', 'llm router', 'production economics'],
            21 => ['ai system design', 'interview prep', 'architecture diagrams'],
        ];

        return $defaults[$phaseNumber] ?? [$phase['title']];
    }

    /** Upsert topics from the roadmap JSON. Safe to re-run ("Resync roadmap"). */
    public function syncTopics(): int
    {
        $mode = StudyMode::current();
        $phases = collect($this->phases())->map(fn (array $p) => [...$p, 'mode' => $mode])->all();

        // Raw query builder, not the Eloquent static: upsert() bypasses both
        // global scopes and model events entirely, so 'mode' must be set
        // explicitly on every row above rather than relied on to happen
        // automatically.
        DB::table('topics')->upsert($phases, ['phase_number', 'mode'], ['name', 'week_start', 'week_end']);

        return count($phases);
    }

    /** 1-based study week since meta.start_date in the roadmap JSON. */
    public function currentWeek(): int
    {
        $start = \Illuminate\Support\Carbon::parse($this->load()['meta']['start_date']);

        return max(1, intdiv((int) $start->diffInDays(now(), false), 7) + 1);
    }

    /**
     * The learner's ACTIVE phase topic.
     *
     * Progress-gated first: return the lowest-numbered phase that has NOT been
     * explicitly marked 'completed' in phase_progress.  This means Phase 1
     * stays the active phase until the learner clicks "Mark Phase Completed",
     * regardless of how many calendar weeks have elapsed.
     *
     * Fall-back (only when every phase row is 'completed', or no rows exist yet
     * but the calendar week is still inside a phase): use the date-based lookup
     * so a brand-new install still shows the right phase.
     */
    public function currentPhaseTopic(): ?Topic
    {
        // 1. Find the lowest phase number the learner has not moved past (completed, or moved on after one graded attempt).
        $completedPhaseNumbers = \App\Models\PhaseProgress::pastNumbers();

        $activeByProgress = Topic::when(! empty($completedPhaseNumbers), function ($q) use ($completedPhaseNumbers) {
            $q->whereNotIn('phase_number', $completedPhaseNumbers);
        })
        ->orderBy('phase_number')
        ->first();

        if ($activeByProgress) {
            return $activeByProgress;
        }

        // 2. All phases completed (or no topics at all) — fall back to date-based.
        $week = $this->currentWeek();

        return Topic::where('week_start', '<=', $week)->where('week_end', '>=', $week)->first()
            ?? Topic::orderByDesc('week_end')->first();
    }

    /** Weeks come as "3" or "1-2". */
    private function parseWeeks(string $weeks): array
    {
        [$start, $end] = array_pad(explode('-', $weeks, 2), 2, null);

        return [(int) trim($start), (int) trim($end ?? $start)];
    }
}
