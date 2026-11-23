<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Offline update distribution for the copies of this app (sister's install,
 * template users). Keeps a hash manifest of every shippable file; each run
 * packages exactly what changed since the last package into
 * updates/update-NNN-<date>/ with a beginner-proof README and apply script.
 */
class UpdatePackage extends Command
{
    protected $signature = 'update:package
        {notes? : One-line summary written into the package README}
        {--dry : Show what would be packaged without writing anything}
        {--baseline= : Build updates/manifest.json from this app directory and exit}';

    protected $description = 'Cut an offline update package (changed files + README + apply script) for distributed copies.';

    /** Relative directory prefixes never shipped (data, caches, machine-local state). */
    private const EXCLUDED_DIRS = ['.git/', '.omc/', '.claude/', 'node_modules/', 'updates/', 'storage/', 'bootstrap/cache/', 'vendor/'];

    public function handle(): int
    {
        if ($baseline = $this->option('baseline')) {
            return $this->writeBaseline($baseline);
        }

        $manifestPath = base_path('updates/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error('No updates/manifest.json yet. Run: php artisan update:package --baseline=<path to the oldest distributed copy>');

            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $current = $this->hashTree(base_path());

        $changed = [];
        foreach ($current as $path => $hash) {
            if (($manifest['files'][$path] ?? null) !== $hash) {
                $changed[] = $path;
            }
        }
        // Files genuinely dropped by the app, not ones we simply stopped tracking
        // (e.g. a directory newly added to EXCLUDED_DIRS) — those must never be
        // deleted from a recipient's install.
        $deleted = array_values(array_filter(
            array_diff(array_keys($manifest['files']), array_keys($current)),
            fn ($path) => ! $this->excluded($path),
        ));

        if ($changed === [] && $deleted === []) {
            $this->info("Nothing changed since version {$manifest['version']} — no package needed.");

            return self::SUCCESS;
        }

        $version = $manifest['version'] + 1;

        if ($this->option('dry')) {
            $this->info(sprintf('Would build version %d: %d changed/new files, %d deletions.', $version, count($changed), count($deleted)));
            foreach (array_slice($changed, 0, 20) as $path) {
                $this->line("  ~ {$path}");
            }
            if (count($changed) > 20) {
                $this->line('  … and '.(count($changed) - 20).' more');
            }

            return self::SUCCESS;
        }

        $pkg = base_path(sprintf('updates/update-%03d-%s', $version, today()->toDateString()));

        foreach ($changed as $path) {
            File::ensureDirectoryExists(dirname("{$pkg}/files/{$path}"));
            File::copy(base_path($path), "{$pkg}/files/{$path}");
        }
        if ($deleted !== []) {
            File::put("{$pkg}/deletions.txt", implode(PHP_EOL, $deleted));
        }
        File::copy(base_path('scripts/apply-update.ps1'), "{$pkg}/apply-update.ps1");
        File::put("{$pkg}/version.txt", (string) $version);
        File::put("{$pkg}/README.md", $this->readme($version, $this->argument('notes') ?? '(no notes given)', $changed, $deleted));

        File::put($manifestPath, json_encode(['version' => $version, 'files' => $current], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put(base_path('version.txt'), (string) $version);

        $this->info(sprintf('Built %s — %d files, %d deletions. Give that folder to every copy of the app.', basename($pkg), count($changed), count($deleted)));

        return self::SUCCESS;
    }

    private function writeBaseline(string $dir): int
    {
        if (! is_dir($dir)) {
            $this->error("Not a directory: {$dir}");

            return self::FAILURE;
        }

        $version = File::exists(base_path('version.txt')) ? (int) trim(File::get(base_path('version.txt'))) : 1;

        File::ensureDirectoryExists(base_path('updates'));
        File::put(base_path('updates/manifest.json'), json_encode(
            ['version' => $version, 'files' => $this->hashTree(rtrim($dir, '/\\'))],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        $this->info("Baseline manifest written from {$dir} (version {$version}).");

        return self::SUCCESS;
    }

    /** @return array<string, string> relative path (forward slashes) => md5, sorted */
    private function hashTree(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $item) use ($root) {
                    $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

                    return ! $this->excluded($item->isDir() ? "{$rel}/" : $rel);
                },
            ),
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                $files[$rel] = md5_file($item->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    private function excluded(string $rel): bool
    {
        foreach (self::EXCLUDED_DIRS as $dir) {
            if (str_starts_with($rel, $dir)) {
                return true;
            }
        }

        $base = basename($rel);

        // .env and the sqlite files are the recipient's personal data; the root
        // roadmap JSON (either track) carries a personal start_date and/or
        // personal course choices; version.txt is written by the applier,
        // never shipped.
        return $rel === '.env'
            || $rel === 'version.txt'
            || $base === '.phpunit.result.cache'
            || str_starts_with($base, 'database.sqlite')
            || str_ends_with($base, '.log')
            || preg_match('/^(AI_.*Roadmap.*|Laravelstudy)\.json$/i', $rel) === 1;
    }

    private function readme(int $version, string $notes, array $changed, array $deleted): string
    {
        $list = implode("\n", array_map(fn ($p) => "- `{$p}`", $changed));
        $del = $deleted === [] ? '' : "\n\nFiles removed by this update (the installer deletes them):\n"
            .implode("\n", array_map(fn ($p) => "- `{$p}`", $deleted));
        $count = count($changed);

        return <<<MD
            # Study Engine — Update {$version}

            **What's new:** {$notes}

            ## How to install (5 minutes, your data is safe)

            1. Copy this whole `update-…` folder to the computer that runs Study Engine (a USB stick is fine).
            2. Close the app if it is running (press `Ctrl+C` in the `php artisan serve` window).
            3. Open PowerShell inside this folder: hold **Shift**, right-click an empty spot, choose **"Open PowerShell window here"**.
            4. Run:
               ```
               powershell -ExecutionPolicy Bypass -File .\\apply-update.ps1
               ```
            5. When asked, type the full path of the Study Engine folder (the one containing the `artisan` file) and press Enter.
            6. Start the app again: `php artisan serve`.

            Your data is **never touched**: missions, concepts, scores, chat, your name, personal instructions, API keys
            and the database stay exactly as they are. Every replaced file is first backed up into
            `backup-before-update-{$version}` inside this folder, so the update can be undone.

            ## Files in this update ({$count})

            {$list}{$del}
            MD;
    }
}
