<?php

namespace App\Services;

use App\Models\Topic;
use Illuminate\Support\Facades\File;

class RoadmapService
{
    /**
     * Read the roadmap JSON fresh from disk on every call, so edits to the
     * file take effect on the next resync without any code change.
     */
    public function load(): array
    {
        $files = File::glob(storage_path('app/roadmap/*.json'));

        if (empty($files)) {
            $files = File::glob(base_path('*.json'));
        }

        if (empty($files)) {
            throw new \RuntimeException('No roadmap JSON found in storage/app/roadmap/ or base directory.');
        }

        return json_decode(File::get($files[0]), true, 512, JSON_THROW_ON_ERROR);
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

    /** Get upcoming checkpoints or certifications based on current week. */
    public function getUpcomingExams(?int $currentWeek = null): array
    {
        $week = $currentWeek ?? $this->currentWeek();
        $upcoming = [];

        foreach ($this->getCheckpoints() as $index => $cp) {
            $cpWeek = (int) ($cp['week'] ?? $cp['due_week'] ?? 0);
            $title = $cp['title'] ?? $cp['name'] ?? ('Checkpoint ' . ($index + 1));
            
            // Near if within 2 weeks or if current week passed and checkpoint not marked
            if ($cpWeek > 0 && ($cpWeek - $week <= 2)) {
                $upcoming[] = [
                    'type' => 'checkpoint',
                    'id' => 'cp_' . ($index + 1),
                    'title' => $title,
                    'week' => $cpWeek,
                    'due_in' => $cpWeek - $week,
                    'data' => $cp,
                ];
            }
        }

        foreach ($this->getCertifications() as $index => $cert) {
            $certWhen = $cert['when'] ?? '';
            $upcoming[] = [
                'type' => 'certification',
                'id' => 'cert_' . ($index + 1),
                'title' => $cert['name'],
                'provider' => $cert['provider'] ?? '',
                'when' => $certWhen,
                'data' => $cert,
            ];
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
        $phases = $this->phases();

        Topic::upsert($phases, ['phase_number'], ['name', 'week_start', 'week_end']);

        return count($phases);
    }

    /** 1-based study week since meta.start_date in the roadmap JSON. */
    public function currentWeek(): int
    {
        $start = \Illuminate\Support\Carbon::parse($this->load()['meta']['start_date']);

        return max(1, intdiv((int) $start->diffInDays(now(), false), 7) + 1);
    }

    /** Topic whose week range contains the current week; last topic once past the plan. */
    public function currentPhaseTopic(): ?Topic
    {
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
