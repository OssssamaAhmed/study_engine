---
name: blast-radius
description: >
  Blast Radius Analysis Protocol for the Study Engine codebase.
  Use this skill whenever you are about to make ANY code change.
  It forces you to map every direct edit and every downstream file
  that could break, then produce a declaration for the user before
  touching a single line of code.
---

# Blast Radius Protocol — Study Engine

## Purpose

Prevent silent breakage. When you change one file in a Laravel app,
3-10 other files often depend on it. This skill forces you to find
them ALL before you start, declare them to the user, and only proceed
with the user's awareness.

---

## Step-by-step procedure (MANDATORY before any edit)

### 1. Identify the target file(s)
List every file you intend to touch. Be precise — include full paths.

### 2. Run the analyzer
For each file you will change, run:

```powershell
php scripts/blast_radius.php <relative-path-to-file>
```

The script prints:
- **Direct dependents** — files that `use`, `extend`, `include`, `@extends`,
  `@include`, or `import` the target
- **Indirect dependents** — files that depend on the direct dependents
- **Risk level** — Low / Medium / High based on how many dependents exist
- **Database impact** — flags if the file is a Model or Migration
- **Route impact** — flags if the file is a Controller referenced in routes

### 3. Classify the blast radius

| Category | What to check |
|----------|--------------|
| **PHP class** | `use ClassName`, `extends`, `implements`, `new ClassName()`, `app()->make()`, `resolve()` |
| **Blade template** | `@extends('layouts.X')`, `@include('partials.X')`, `@component` |
| **CSS / app.css** | Custom component classes used across all Blade files |
| **Model** | Every controller, service, and test that queries the model |
| **Migration** | The model, seeders, tests that reference columns |
| **Route** | Every `route('name')` or hardcoded `/path` in views |
| **Service class** | Every controller or other service that `new`s or injects it |
| **Config file** | Every `config('key')` call in the codebase |

### 4. Produce the declaration

Output this block **before the first tool call that edits a file**:

```
## ⚡ Blast Radius Declaration

**Files I will directly edit:**
- `app/Services/RoadmapService.php` — fixing currentPhaseTopic() logic

**Files that depend on the above and could break:**
- `app/Http/Controllers/DashboardController.php` — calls currentPhaseTopic()
- `app/Http/Controllers/PhaseController.php` — calls currentPhaseTopic()
- (analyzer output: 2 direct dependents, 0 indirect)

**Risky side-effects to watch:**
- Return type change from Topic|null to Topic|null (same, safe)
- New DB query added — adds 1 query per page load (negligible)

**Confidence the rest of the app stays green:** High

**Recommended safe approach:**
1. Edit only RoadmapService::currentPhaseTopic()
2. Keep the method signature identical
3. Run `php artisan test` after — no migration needed
```

### 5. Proceed only after declaration is visible

Do NOT call any file-writing tool before the declaration has been output.

### 6. Post-edit verification

After finishing the batch of changes:

- If PHP files changed → `php artisan test 2>&1`
- If CSS/JS changed → `npm run build 2>&1`
- If routes changed → `php artisan route:list 2>&1`
- If models/migrations changed → `php artisan migrate:status 2>&1`

Report the result to the user.

---

## Risk level thresholds

| Dependents found | Risk level | Action |
|-----------------|-----------|--------|
| 0 | Low | Proceed freely after declaration |
| 1–3 | Medium | Proceed after declaration, verify each dependent |
| 4–8 | High | Stop, explain full impact, ask user to confirm |
| 9+ | Critical | Stop, propose a safer incremental approach first |

---

## Special protected files (NEVER touch without explicit user request)

| File | Reason |
|------|--------|
| `resources/prompts/exercise-engine.md` | Runtime LLM prompt — byte-identical to RUNTIME_SYSTEM_PROMPT.md |
| `RUNTIME_SYSTEM_PROMPT.md` | Source of truth for mission shape |
| `database/database.sqlite` | Real study data — never wipe |
| `storage/app/roadmap/*.json` | Curriculum truth — only via Resync |
| `.env` | Keys — never log or echo |

---

## How to call the analyzer

```powershell
# Analyze a single file
php scripts/blast_radius.php app/Services/RoadmapService.php

# Analyze multiple files (runs for each)
php scripts/blast_radius.php app/Services/RoadmapService.php app/Http/Controllers/DashboardController.php

# Analyze with verbose mode (shows every grep match)
php scripts/blast_radius.php --verbose app/Services/RoadmapService.php
```
