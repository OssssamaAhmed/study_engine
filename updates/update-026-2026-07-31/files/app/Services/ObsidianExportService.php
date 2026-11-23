<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Facades\Storage;

class ObsidianExportService
{
    /** Write one mission as a Markdown note in the vault. Returns the relative path written. */
    public function export(Exercise $exercise): string
    {
        $exercise->loadMissing('concepts');

        $folder = trim(config('obsidian.missions_folder'), '/\\');
        $path = sprintf('%s/M%d - %s.md', $folder, $exercise->id, $this->safeFilename($exercise->title()));

        Storage::disk('obsidian')->put($path, $this->markdown($exercise));

        return $path;
    }

    private function markdown(Exercise $exercise): string
    {
        $concepts = $exercise->concepts->pluck('name')->filter()->values();
        $sourceUrl = rtrim(config('app.url'), '/').'/exercise/'.$exercise->id;

        $lines = [
            '---',
            'study_engine_id: '.$exercise->id,
            'reference: '.$this->yamlQuote('M'.$exercise->id),
            'title: '.$this->yamlQuote($exercise->title()),
            'status: '.$this->yamlQuote((string) $exercise->status),
            'source: '.$this->yamlQuote($sourceUrl),
            'tags: [study-engine, study-mission]',
            'concepts:',
        ];

        $lines = array_merge($lines, $concepts->isEmpty()
            ? ['  []']
            : $concepts->map(fn ($name) => '  - '.$this->yamlQuote($name))->all());

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# M'.$exercise->id.' — '.$exercise->title();
        $lines[] = '';
        $lines[] = '[Open in Study Engine]('.$sourceUrl.')';
        $lines[] = '';
        $lines[] = (string) $exercise->mission_md;

        return implode(PHP_EOL, $lines);
    }

    private function safeFilename(string $value): string
    {
        $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " .-");

        return $value === '' ? 'Study Mission' : mb_substr($value, 0, 120);
    }

    private function yamlQuote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', '\\n'], $value).'"';
    }
}
