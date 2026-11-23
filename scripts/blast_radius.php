#!/usr/bin/env php
<?php
/**
 * Blast Radius Analyzer — Study Engine
 * ======================================
 * Statically analyzes the codebase to find every file that depends on
 * a target file, so agents can declare their full blast radius before
 * making any change.
 *
 * Usage:
 *   php scripts/blast_radius.php <file> [<file2> ...]
 *   php scripts/blast_radius.php --verbose <file>
 *
 * Exit codes:
 *   0  = Low risk  (0 dependents)
 *   1  = Medium    (1-3)
 *   2  = High      (4-8)
 *   3  = Critical  (9+)
 */

// ─── Config ──────────────────────────────────────────────────────────────────

$ROOT     = dirname(__DIR__);
$VERBOSE  = false;
$targets  = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--verbose' || $arg === '-v') {
        $VERBOSE = true;
    } else {
        $targets[] = $arg;
    }
}

if (empty($targets)) {
    echo "Usage: php scripts/blast_radius.php [--verbose] <file> [<file2> ...]\n";
    echo "Example: php scripts/blast_radius.php app/Services/RoadmapService.php\n";
    exit(0);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Recursively collect all files matching extensions under $dir. */
function collectFiles(string $dir, array $exts = ['php', 'blade.php', 'js', 'css']): array
{
    $result = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        foreach ($exts as $ext) {
            if (str_ends_with($path, '.' . $ext)) {
                $result[] = $path;
                break;
            }
        }
    }
    return $result;
}

/** Read a file, return '' on failure. */
function brReadFile(string $path): string
{
    return is_file($path) ? (file_get_contents($path) ?: '') : '';
}

/** Extract a bare class name from a path like app/Services/RoadmapService.php */
function className(string $path): string
{
    return pathinfo(basename($path), PATHINFO_FILENAME);
}

/** Extract namespace from a PHP file. */
function extractNamespace(string $content): string
{
    if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $m)) {
        return $m[1];
    }
    return '';
}

/** Return the fully-qualified class name for a PHP file. */
function fqcn(string $path, string $root): string
{
    $rel   = ltrim(str_replace([$root, '\\'], ['', '/'], $path), '/');
    $rel   = preg_replace('/\.php$/', '', $rel);
    $parts = explode('/', $rel);
    // Map directory prefixes to namespaces the Laravel way
    $nsMap = ['app' => 'App', 'tests' => 'Tests'];
    if (isset($nsMap[$parts[0]])) {
        $parts[0] = $nsMap[$parts[0]];
    }
    return implode('\\', $parts);
}

/** Check if $haystack contains any of $needles (case-insensitive). */
function containsAny(string $haystack, array $needles): array
{
    $hits = [];
    foreach ($needles as $needle) {
        if ($needle !== '' && stripos($haystack, $needle) !== false) {
            $hits[] = $needle;
        }
    }
    return $hits;
}

// ─── Protected files list ────────────────────────────────────────────────────

$PROTECTED = [
    'resources/prompts/exercise-engine.md',
    'RUNTIME_SYSTEM_PROMPT.md',
    'database/database.sqlite',
    '.env',
];

// ─── Scan all source files once ──────────────────────────────────────────────

$scanDirs  = ['app', 'resources', 'routes', 'tests', 'config', 'database'];
$allFiles  = [];
foreach ($scanDirs as $d) {
    $full = $ROOT . '/' . $d;
    if (is_dir($full)) {
        $allFiles = array_merge($allFiles, collectFiles($full));
    }
}
$allFiles[] = $ROOT . '/vite.config.js';

// ─── Analyse each target ────────────────────────────────────────────────────

$totalDependents = 0;
$allResults      = [];

foreach ($targets as $target) {
    // Normalise path
    $targetAbs = str_replace('\\', '/', realpath($ROOT . '/' . $target) ?: ($ROOT . '/' . $target));
    $targetRel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $targetAbs), '/');

    echo "\n";
    echo str_repeat('═', 70) . "\n";
    echo "  BLAST RADIUS ANALYSIS\n";
    echo "  Target : $targetRel\n";
    echo str_repeat('═', 70) . "\n\n";

    // ── Protected file check ──────────────────────────────────────────────
    foreach ($PROTECTED as $prot) {
        if (str_contains($targetRel, $prot)) {
            echo "  🚫 PROTECTED FILE — never edit without explicit user request\n";
            echo "     File: $prot\n\n";
        }
    }

    // ── Build search terms ────────────────────────────────────────────────
    $ext       = pathinfo($targetAbs, PATHINFO_EXTENSION);
    $isBlade   = str_ends_with($targetAbs, '.blade.php');
    $baseName  = className($targetAbs);
    $content   = brReadFile($targetAbs);
    $ns        = extractNamespace($content);
    $fqClass   = $ns ? "$ns\\$baseName" : $baseName;
    $viewName  = '';   // for blade files

    $searchTerms = [];

    if ($isBlade) {
        // Must be checked BEFORE generic PHP — pathinfo returns 'php' for blade files too.
        // Convert path to view dot-notation, e.g. resources/views/layouts/app.blade.php → layouts.app
        $viewName = str_replace(['resources/views/', '.blade.php', '/'], ['', '', '.'], $targetRel);
        $searchTerms[] = "@extends('$viewName'";
        $searchTerms[] = "@extends(\"$viewName\"";
        $searchTerms[] = "@include('$viewName'";
        $searchTerms[] = "@include(\"$viewName\"";
        $searchTerms[] = "@component('$viewName'";
        $searchTerms[] = "view('$viewName'";
        $searchTerms[] = "view(\"$viewName\"";
        $searchTerms[] = "'$viewName'";  // broader catch
    } elseif ($ext === 'php') {
        $searchTerms[] = $baseName;           // class name
        $searchTerms[] = "use $fqClass";      // use statement
        $searchTerms[] = "use $ns\\$baseName"; // aliased use
        $searchTerms[] = "new $baseName(";    // direct instantiation
        $searchTerms[] = "new \\$fqClass(";   // fully-qualified
        $searchTerms[] = "extends $baseName"; // inheritance
        $searchTerms[] = "implements $baseName"; // interface
        $searchTerms[] = "'$baseName'";       // string reference
        $searchTerms[] = "\"$baseName\"";     // string reference
        $searchTerms[] = "resolve('$fqClass')";
        // Extract method names defined in file for call-site search
        preg_match_all('/^\s+(?:public|protected|private)?\s+function\s+(\w+)\s*\(/m', $content, $mm);
        foreach (($mm[1] ?? []) as $method) {
            if (!in_array($method, ['__construct', '__destruct', 'setUp', 'tearDown'])) {
                $searchTerms[] = "->$method(";
                $searchTerms[] = "::$method(";
            }
        }
    } elseif ($ext === 'css') {
        // Extract custom component class names from @layer components
        preg_match_all('/^\s+\.([\w-]+)\s*\{/m', $content, $cm);
        foreach (($cm[1] ?? []) as $cls) {
            $searchTerms[] = "class=\"$cls";
            $searchTerms[] = "class='$cls";
            $searchTerms[] = " $cls ";
            $searchTerms[] = " $cls\"";
            $searchTerms[] = " $cls'";
        }
        $searchTerms[] = "app.css";
        $searchTerms[] = "@import";
    } elseif ($ext === 'js') {
        $searchTerms[] = basename($targetAbs);
        $searchTerms[] = "import";
    }

    // Filter empty terms
    $searchTerms = array_values(array_filter(array_unique($searchTerms)));

    // ── Scan every file for references ────────────────────────────────────
    $directDeps   = [];
    $dbImpact     = false;
    $routeImpact  = false;
    $isMigration  = str_contains($targetRel, 'database/migrations');
    $isModel      = str_contains($targetRel, 'app/Models');
    $isController = str_contains($targetRel, 'app/Http/Controllers');
    $isService    = str_contains($targetRel, 'app/Services');
    $isRoute      = str_contains($targetRel, 'routes/');

    foreach ($allFiles as $file) {
        $fileRel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $file), '/');
        if ($file === $targetAbs || $fileRel === $targetRel) continue;

        $body = brReadFile($file);
        $hits = containsAny($body, $searchTerms);

        if (!empty($hits)) {
            $directDeps[$fileRel] = $hits;
            if ($VERBOSE) {
                echo "  [dep] $fileRel\n";
                foreach ($hits as $h) echo "        matched: $h\n";
            }
        }
    }

    // ── Determine special impact ───────────────────────────────────────────
    if ($isModel || $isMigration) $dbImpact = true;
    if ($isController || $isRoute) $routeImpact = true;

    // ── Indirect dependents (1 level deep) ───────────────────────────────
    $indirectDeps = [];
    foreach (array_keys($directDeps) as $dep) {
        $depAbs = $ROOT . '/' . $dep;
        $depCls = className($depAbs);
        $depContent = brReadFile($depAbs);
        foreach ($allFiles as $file) {
            $fileRel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $file), '/');
            if ($file === $targetAbs || $fileRel === $dep || isset($directDeps[$fileRel])) continue;
            $body = brReadFile($file);
            if (stripos($body, $depCls) !== false) {
                $indirectDeps[$fileRel] = "depends on $dep";
            }
        }
    }

    // ── Risk level ────────────────────────────────────────────────────────
    $total     = count($directDeps);
    $totalDependents += $total;
    $riskLevel = match(true) {
        $total === 0  => ['label' => 'LOW',      'icon' => '🟢', 'exit' => 0],
        $total <= 3   => ['label' => 'MEDIUM',   'icon' => '🟡', 'exit' => 1],
        $total <= 8   => ['label' => 'HIGH',      'icon' => '🔴', 'exit' => 2],
        default       => ['label' => 'CRITICAL', 'icon' => '🚨', 'exit' => 3],
    };

    // ── Output ────────────────────────────────────────────────────────────
    echo "  Risk level : {$riskLevel['icon']} {$riskLevel['label']} ({$total} direct dependent" . ($total !== 1 ? 's' : '') . ")\n";
    if ($dbImpact)    echo "  DB impact  : ⚠️  YES — Model or Migration file\n";
    if ($routeImpact) echo "  Route impact: ⚠️  YES — Controller or Route file\n";
    echo "\n";

    // Direct dependents
    if (!empty($directDeps)) {
        echo "  ┌─ Direct dependents (" . count($directDeps) . ") ─────────────────────────\n";
        foreach ($directDeps as $file => $hits) {
            $type = match(true) {
                str_contains($file, 'Controllers') => '[Controller]',
                str_contains($file, 'Services')    => '[Service]',
                str_contains($file, 'Models')      => '[Model]',
                str_contains($file, 'views')       => '[Blade]',
                str_contains($file, 'routes')      => '[Route]',
                str_contains($file, 'tests')       => '[Test]',
                str_contains($file, 'config')      => '[Config]',
                default                            => '[File]',
            };
            echo "  │  $type $file\n";
            if ($VERBOSE) {
                foreach (array_slice($hits, 0, 3) as $h) {
                    echo "  │      » matched: " . trim($h) . "\n";
                }
            }
        }
        echo "  └──────────────────────────────────────────────────────────\n\n";
    } else {
        echo "  ✅ No direct dependents found — isolated change.\n\n";
    }

    // Indirect dependents
    if (!empty($indirectDeps)) {
        $limited = array_slice($indirectDeps, 0, 10, true);
        echo "  ┌─ Indirect dependents (may also be affected) ───────────\n";
        foreach ($limited as $file => $why) {
            echo "  │  [Indirect] $file\n";
        }
        if (count($indirectDeps) > 10) {
            echo "  │  ... and " . (count($indirectDeps) - 10) . " more\n";
        }
        echo "  └──────────────────────────────────────────────────────────\n\n";
    }

    // Recommendations
    echo "  ┌─ Recommended safe approach ────────────────────────────────\n";
    if ($riskLevel['label'] === 'LOW') {
        echo "  │  ✅ Low risk. Proceed after blast-radius declaration.\n";
    } elseif ($riskLevel['label'] === 'MEDIUM') {
        echo "  │  1. Declare this blast radius to the user before editing.\n";
        echo "  │  2. Edit $baseName first, keep method signatures identical.\n";
        echo "  │  3. Verify each dependent file still compiles/runs.\n";
        echo "  │  4. Run `php artisan test` after changes.\n";
    } elseif ($riskLevel['label'] === 'HIGH') {
        echo "  │  ⚠️  High risk — STOP and inform user of full impact.\n";
        echo "  │  1. Show this report to user and get confirmation.\n";
        echo "  │  2. Consider narrowing the change scope.\n";
        echo "  │  3. Change one file at a time, verify after each.\n";
        echo "  │  4. Run `php artisan test` after every step.\n";
    } else {
        echo "  │  🚨 CRITICAL — do NOT proceed without explicit user approval.\n";
        echo "  │  1. STOP immediately. Show this report to user.\n";
        echo "  │  2. Propose a safer, incremental refactor plan.\n";
        echo "  │  3. Wait for user to approve the plan before any edit.\n";
    }
    echo "  └──────────────────────────────────────────────────────────\n";

    // Post-edit verification commands
    echo "\n  ┌─ Run after your changes ───────────────────────────────────\n";
    if ($ext === 'php') {
        echo "  │  php artisan test\n";
        if ($isRoute) echo "  │  php artisan route:list\n";
        if ($isMigration) echo "  │  php artisan migrate:status\n";
    }
    if (in_array($ext, ['css', 'js']) || str_ends_with($targetAbs, '.blade.php')) {
        echo "  │  npm run build\n";
    }
    echo "  └──────────────────────────────────────────────────────────\n\n";

    $allResults[] = ['target' => $targetRel, 'risk' => $riskLevel, 'directCount' => $total];
}

// ─── Summary (multi-target) ───────────────────────────────────────────────────

if (count($targets) > 1) {
    echo "\n" . str_repeat('═', 70) . "\n";
    echo "  COMBINED SUMMARY\n";
    echo str_repeat('═', 70) . "\n";
    foreach ($allResults as $r) {
        echo "  {$r['risk']['icon']} {$r['risk']['label']} ({$r['directCount']} deps) — {$r['target']}\n";
    }
    echo "\n  Total dependents across all targets: $totalDependents\n";
}

// ─── Final exit code ─────────────────────────────────────────────────────────
$maxExit = 0;
foreach ($allResults as $r) {
    $maxExit = max($maxExit, $r['risk']['exit']);
}
exit($maxExit);
