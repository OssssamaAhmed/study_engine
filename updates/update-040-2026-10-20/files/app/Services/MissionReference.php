<?php

namespace App\Services;

use App\Models\Exercise;

/**
 * Resolves mission references the learner types in tutor chat — M3 (whole
 * mission), M3 D2 / M3-D2 (drill 2 of mission 3), M3 B1 (boss fight 1), or
 * bare D2/B1 against the newest mission — into the actual mission text, so
 * the learner never pastes content by hand.
 */
class MissionReference
{
    public function resolve(string $message): string
    {
        $blocks = [];

        preg_match_all('/\bM(\d+)(?:[\s-]*([DB])(\d+))?\b/i', $message, $refs, PREG_SET_ORDER);

        foreach ($refs as $ref) {
            $blocks[] = $this->lookup((int) $ref[1], strtoupper($ref[2] ?? ''), (int) ($ref[3] ?? 0));
        }

        // Bare D2 / B1 means "in my current mission" — only when no M-ref present.
        if ($refs === [] && preg_match_all('/\b([DB])(\d+)\b/i', $message, $bare, PREG_SET_ORDER)) {
            $latest = Exercise::orderByDesc('date')->orderByDesc('id')->first();

            foreach ($bare as $ref) {
                $blocks[] = $latest
                    ? $this->lookup($latest->id, strtoupper($ref[1]), (int) $ref[2])
                    : strtoupper($ref[1]).$ref[2].': (no missions exist yet)';
            }
        }

        return implode("\n\n", array_filter($blocks));
    }

    private function lookup(int $missionId, string $type, int $n): string
    {
        $exercise = Exercise::find($missionId);

        if (! $exercise) {
            return "M{$missionId}: (no mission with that number)";
        }

        if ($type === '') {
            return "M{$missionId} — full mission of {$exercise->date->toDateString()}:\n{$exercise->mission_md}";
        }

        $section = $this->extractSection($exercise->mission_md, $type, $n);

        if ($section === null) {
            $label = $type === 'D' ? "drill {$n}" : "boss fight {$n}";

            return "M{$missionId} {$type}{$n}: (mission M{$missionId} has no {$label})";
        }

        return "M{$missionId} {$type}{$n} (from the mission of {$exercise->date->toDateString()}):\n## {$section}";
    }

    /**
     * Splits a mission into its '## ' sections (fence-aware, so a '## ' line
     * inside a code block never splits) and tags each drill 'D{n}' and each
     * Boss Fight 'B{k}' — the same references the tutor chat understands.
     *
     * @return list<array{md: string, key: ?string}>
     */
    public function sections(string $missionMd): array
    {
        $sections = [];
        $current = '';
        $inFence = false;

        foreach (preg_split('/\R/', $missionMd) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = ! $inFence;
            }
            if (! $inFence && str_starts_with($line, '## ') && trim($current) !== '') {
                $sections[] = $current;
                $current = '';
            }
            $current .= $line."\n";
        }
        if (trim($current) !== '') {
            $sections[] = $current;
        }

        $bossSeen = 0;

        return array_map(function ($md) use (&$bossSeen) {
            $key = match (true) {
                (bool) preg_match('/^## Drill\s+(\d+)\b/i', $md, $m) => 'D'.$m[1],
                (bool) preg_match('/^## Boss Fight/i', $md) => 'B'.(++$bossSeen),
                default => null,
            };

            return ['md' => rtrim($md), 'key' => $key];
        }, $sections);
    }

    /** Returns the '## ' section (heading + body) for drill N or the N-th Boss Fight, plus its solution when found. */
    public function extractSection(string $missionMd, string $type, int $n): ?string
    {
        $sections = preg_split('/^## /m', $missionMd);
        $wanted = null;
        $bossSeen = 0;

        foreach ($sections as $section) {
            if ($type === 'D' && preg_match('/^Drill\s+'.$n.'\b/i', $section)) {
                $wanted = rtrim($section);
                break;
            }
            if ($type === 'B' && stripos($section, 'Boss Fight') === 0 && ++$bossSeen === $n) {
                $wanted = rtrim($section);
                break;
            }
        }

        if ($wanted === null) {
            return null;
        }

        if ($solution = $this->matchingSolution($missionMd, $wanted)) {
            $wanted .= "\n\n{$solution}";
        }

        return $wanted;
    }

    /** Finds the '### Solution — {name}' block whose heading shares the section's evocative name. */
    private function matchingSolution(string $missionMd, string $section): ?string
    {
        // Heading shapes: "Drill 2 — Name [NEW]" / "Boss Fight — Name"
        $heading = strtok($section, "\n");
        if (! preg_match('/[—-]\s*(.+?)\s*(?:\[|$)/u', $heading, $m)) {
            return null;
        }
        $name = trim($m[1]);

        if ($name !== '' && preg_match(
            '/^(### Solution[^\n]*'.preg_quote($name, '/').'[^\n]*\n.*?)(?=\n### |\n## |\z)/msu',
            $missionMd,
            $sol,
        )) {
            return rtrim($sol[1]);
        }

        return null;
    }
}
