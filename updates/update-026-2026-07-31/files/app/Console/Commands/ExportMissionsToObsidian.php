<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Services\ObsidianExportService;
use Illuminate\Console\Command;
use Throwable;

class ExportMissionsToObsidian extends Command
{
    protected $signature = 'study:export-obsidian {--id=* : Export only these exercise IDs}';

    protected $description = 'Export Study Engine missions into the configured Obsidian vault';

    public function handle(ObsidianExportService $exporter): int
    {
        if (! config('obsidian.enabled')) {
            $this->error('Obsidian export is disabled. Set OBSIDIAN_EXPORT_ENABLED=true in .env.');

            return self::FAILURE;
        }

        $ids = collect($this->option('id'))->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);

        $query = Exercise::query()->with('concepts')->orderBy('id');
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        $exported = $failed = 0;

        $query->each(function (Exercise $exercise) use ($exporter, &$exported, &$failed): void {
            try {
                $path = $exporter->export($exercise);
                $this->line("Exported M{$exercise->id}: {$path}");
                $exported++;
            } catch (Throwable $e) {
                report($e);
                $this->error("Failed M{$exercise->id}: {$e->getMessage()}");
                $failed++;
            }
        });

        $this->newLine();
        $this->info("Export complete. Exported: {$exported}; Failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
