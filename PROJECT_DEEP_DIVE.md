# Study Engine — Complete Project Deep Dive

> This document is written to be the **only context** a reader needs to reason about this codebase. It assumes zero prior familiarity. Every claim below was verified against the actual source files as of 2026-08-25 (repo version `37`, see `version.txt` / `updates/manifest.json`). Where the code disagrees with the project's own architecture notes (`CLAUDE.md`), that disagreement is called out explicitly rather than silently "corrected."

---

## 1. What this app is

Study Engine is a **local, single-user web application** that helps one person study for two different career goals — becoming an **AI Security Engineer** or becoming a **Laravel & React Web Developer** — using a technique called **Spaced Interleaved Retrieval Practice**. The learner pastes in transcripts of what they just studied (lecture notes, article text, their own notes). The app uses a Large Language Model (LLM) to pull out the individual "concepts" contained in that text, stores them, and schedules each one for review in the future using a spaced-repetition algorithm (similar in spirit to flashcard apps like Anki, but instead of flashcards it generates whole hands-on coding "Missions"). Each day the learner can ask the app to generate a Mission: a short markdown document with several drills and one big integrative "Boss Fight" challenge, mixing brand-new concepts with concepts that are due for review, calling an LLM to write it. The learner does the work outside the app (in their own editor/notebook), pastes their result back in, and the app calls the LLM again to grade it, updating each concept's review schedule based on the score. Beyond this core loop the app also has an AI tutor chat, a "Side Quest" mode for on-demand micro-drills targeting a specific weak spot, an exam-prep engine, and a "Mega Portfolio Project" system that walks the learner through researching, briefing, planning, and skeleton-outlining one large realistic capstone project per curriculum phase — with the results exportable as Word-compatible documents.

The two "tracks" (AI Security Engineer vs Laravel Web Development) are functionally two parallel copies of the entire app's data (own topics, own concepts, own missions, own chat history) sharing one codebase and one database, switched instantly from a toggle on the dashboard. Everything runs on the user's own Windows PC — there is no server, no multi-user support, no cloud database. The developer explicitly treats the local SQLite database as containing **real, valuable user study history** — nothing in this codebase should ever run `migrate:fresh` or otherwise wipe it without the user's explicit, current-session consent.

---

## 2. Tech stack & environment

| Layer | Choice |
|---|---|
| Language / framework | PHP 8.4, Laravel 13 |
| Database | SQLite, single file at `database/database.sqlite` (also used as an in-memory SQLite DB for the test suite) |
| Frontend | Server-rendered Blade templates, Tailwind CSS v4 via Vite, a small amount of vanilla JS (no SPA framework, no Livewire/Inertia) |
| Package managers | Composer 2 (PHP), npm (Node 22, for the Vite asset build only) |
| LLM access | HTTP calls to one of five possible providers (FreeLLMAPI proxy, Google Gemini, OpenRouter, Ollama, Anthropic) via a small first-party driver abstraction — no LLM SDK dependency |
| Hosting model | Local, single-user, no Docker, no WSL — runs natively on Windows 11 |
| Auth | Laravel's default `User`/session scaffolding exists (from the framework starter kit) but is **not actually used** by the app's real functionality — there's no login flow wired into the study-tracking features; this is a single-user local tool |

**Environment specifics** (from `CLAUDE.md`, verified structurally):
- PHP 8.4 is a **portable** (non-installer) build at `C:\Users\HP\tools\php`, added to the user's PATH, with Composer's `composer.bat` shim living in the same folder.
- TLS/HTTPS calls (needed for every LLM provider call) depend on a CA bundle at `C:\Users\HP\tools\php\cacert.pem`, wired into that install's `php.ini` via the `curl.cainfo` and `openssl.cafile` settings. If any HTTPS call throws cURL error 60 ("SSL certificate problem"), these two `php.ini` lines are the first thing to check.
- Node 22 is a normal system install, used only to run `npm install` / `npm run build` (Vite) — nothing about the running app depends on a Node process; the compiled assets in `public/build/` are static files served by PHP.

**How to run it** (from `README.md`):
```powershell
composer install
npm install
npm run build
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```
Then edit `.env` to set LLM credentials (see §5.2 "LLM provider routing" below), and either:
```powershell
php artisan serve
```
or use the bundled one-click launcher:
```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\start-study-environment.ps1"
```
which starts both the local **FreeLLMAPI** proxy process and `php artisan serve` in background windows and opens the app in a browser. This script is mirrored inside the repo as `start-study-environment.ps1`. **There is a second, undocumented, near-identical launcher `start-study.ps1`** also present in the repo root — it additionally opens Obsidian and points at a *different* FreeLLMAPI install path (`C:\Users\HP\tools\freellmapi`) than the documented/mirrored script (`E:\Tools\freellmapi`). Do not confuse the two; the documented one (`start-study-environment.ps1`) is the maintained path.

Tests run against an in-memory SQLite database:
```powershell
php artisan test
```

---

## 3. Top-level directory map

Only files/folders relevant to understanding the app are listed; vendor/build output is omitted.

```
study_engine/
├── CLAUDE.md                          Architecture/decisions doc for future coding-agent sessions (human-maintainer-oriented, not zero-context)
├── PROJECT_SPEC.md                    Original build spec for the SINGLE-TRACK app, predates the dual-track system — historical source of truth for baseline requirements
├── README.md                          Setup/run instructions, pages-at-a-glance table, daily workflow walkthrough
├── RUNTIME_SYSTEM_PROMPT.md           STALE original copy of the mission-generation system prompt — see §9 gotchas, no longer loaded by any code path
├── AI_Engineering_Roadmap_v3.2_Karpathy_Updated.json   Root/master copy of the AI Security curriculum JSON (hand-edited source of truth; not read directly by the app)
├── Laravelstudy.json                  Root/master copy of the Laravel Web curriculum, in a SIMPLER shape than storage/app/roadmap's copy (see §9)
├── version.txt                        Current update-package version number (plain integer text)
├── start-study-environment.ps1        Documented one-click launcher (mirrors E:\start-study-environment.ps1)
├── start-study.ps1                    UNDOCUMENTED second launcher — different FreeLLMAPI path, also opens Obsidian
├── phpunit.xml                        Test config — pins AI_PROVIDER/AI_EMBED_PROVIDER=openrouter so tests never call a real API via a .env-configured provider
├── artisan                            Laravel CLI entrypoint
├── composer.json / composer.lock      PHP dependencies
├── package.json / package-lock.json   Node/Vite dependencies
├── vite.config.js                     Vite build config (compiles resources/css + resources/js)
│
├── app/
│   ├── Console/Commands/
│   │   ├── ExportMissionsToObsidian.php   `study:export-obsidian` — bulk (re-)export missions to an Obsidian vault
│   │   ├── LlmTest.php                    `llm:test` — smoke-tests the active LLM provider (chat + embed)
│   │   └── UpdatePackage.php              `update:package` — cuts a versioned diff package for distributing this app to other offline copies
│   ├── Http/
│   │   ├── Controllers/                   One controller per feature area (see §7 for full route table)
│   │   │   ├── ChatController.php         `/chat` — AI tutor chat
│   │   │   ├── ConceptController.php      `/concepts` — browse/retire concepts, resolve struggle notes
│   │   │   ├── Controller.php             Empty base class
│   │   │   ├── DashboardController.php    `/` — home page, stats, mission generation trigger
│   │   │   ├── ExamPrepController.php     `/exam-prep` — exam-prep hub + sessions
│   │   │   ├── ExerciseController.php     `/exercise/{id}` — mission page, submit/evaluate/reset/note/add-more
│   │   │   ├── HistoryController.php      `/history` — past missions list
│   │   │   ├── IngestController.php       `/ingest` — transcript → concepts pipeline
│   │   │   ├── NotificationController.php `/notifications` — exam/system alerts
│   │   │   ├── PhaseController.php        `/phases` — roadmap timeline + Mega Portfolio Project 4-stage flow + document export
│   │   │   └── SideQuestController.php    `/side-quest` — targeted micro-drill generator
│   │   └── Middleware/
│   │       └── RecordActivity.php         Runs on every request; touches the active track's activity-streak file
│   ├── Models/                            Eloquent models — one per table (see §4 for full schema)
│   │   ├── Concerns/ScopedToMode.php      Trait: global "current track" query scope + auto-stamp on create
│   │   ├── Attempt.php, Chunk.php, Concept.php, ExamPrepSession.php, Exercise.php,
│   │   │   Notification.php, PhaseProgress.php, StruggleNote.php, Topic.php, Transcript.php, User.php
│   ├── Providers/AppServiceProvider.php   Standard Laravel service provider (framework boilerplate)
│   ├── Services/                          The actual business logic layer (see §6 for behavior)
│   │   ├── DocumentExportService.php      Renders stored markdown as .txt/.md/.doc/.docx downloads
│   │   ├── ExamPrepService.php            Exam-prep session generation/evaluation
│   │   ├── ExerciseService.php            THE core engine: concept selection, mission generation, evaluation, spacing ladder
│   │   ├── IngestService.php              Transcript chunking, embedding, concept extraction
│   │   ├── Llm/                           One HTTP driver class per LLM provider, behind a shared interface
│   │   │   ├── AnthropicDriver.php, FreeLlmApiDriver.php, GeminiDriver.php,
│   │   │   │   LlmDriver.php (interface), OllamaDriver.php, OpenRouterDriver.php
│   │   ├── LlmService.php                 Picks the right driver(s) per config, tolerant JSON parsing, cross-provider fallback
│   │   ├── MissionReference.php           Parses "M3", "M3 D2", "B1" etc. out of chat messages
│   │   ├── NotificationService.php        Generates/dedupes exam-related notifications
│   │   ├── ObsidianExportService.php      Writes a mission as a Markdown note into an Obsidian vault folder
│   │   ├── PhaseService.php               The Mega Portfolio Project 4-stage flow + phase completion gating
│   │   └── RoadmapService.php             Reads/parses the roadmap JSON, computes current phase/week, syncs `topics`
│   └── Support/                           Small stateless/flat-file helper classes
│       ├── ActivityLog.php                Per-track flat-file day log → streak count
│       ├── LearnerInstructions.php        Free-text "personal instructions" flat file (shared across tracks)
│       ├── LearnerName.php                Learner's display name flat file (shared across tracks)
│       └── StudyMode.php                  THE dual-track switch + prompt token substitution
│
├── resources/
│   ├── css/app.css                        Design tokens + component classes + `.mission` markdown-render styling (see §5.4)
│   ├── js/app.js                          Entry point bundled by Vite (loaded by the layout; most interactive JS actually lives inline in layouts/app.blade.php and phases/show.blade.php)
│   ├── prompts/                           The five LIVE LLM system prompts (see §8)
│   │   ├── exercise-engine.md             Mission generation (MODE 1) + evaluation (MODE 2) — the main engine prompt
│   │   ├── tutor-chat.md                  `/chat` system prompt
│   │   ├── mega-project.md                Mega Portfolio Project — brief (MODE 1), evaluation (MODE 2), implementation plan (MODE 3), skeleton (MODE 4)
│   │   ├── exam-prep.md                   Exam session generation (MODE 1) + evaluation (MODE 2)
│   │   └── side-quest-engine.md           Side Quest generation (MODE 1) + evaluation (MODE 2, effectively unused — see §9)
│   └── views/                             Blade templates (see §6 per-page detail)
│       ├── layouts/app.blade.php          Shared shell: sidebar nav, flash messages, `data-llm` form JS, mission-outline scroll-spy JS
│       ├── dashboard.blade.php, chat.blade.php, history.blade.php, settings.blade.php, welcome.blade.php (unused Laravel boilerplate)
│       ├── concepts/index.blade.php
│       ├── exam_prep/index.blade.php, session.blade.php
│       ├── exercise/show.blade.php
│       ├── ingest/form.blade.php, confirm.blade.php
│       ├── notifications/index.blade.php
│       ├── partials/generation-options.blade.php   Shared difficulty/drills/bosses form fields
│       ├── phases/index.blade.php, show.blade.php, partials/export-buttons.blade.php
│       └── side_quest/index.blade.php
│
├── database/
│   ├── migrations/                        9 app-relevant migrations, chronological (see §4)
│   ├── seeders/DatabaseSeeder.php         Delegates entirely to `RoadmapService::syncTopics()`
│   └── database.sqlite                    THE real data file — never wipe without explicit consent
│
├── routes/
│   ├── web.php                            Every HTTP route in the app (see §7)
│   └── console.php                        Only the default Laravel `inspire` stub — no custom console routes
│
├── config/
│   ├── ai.php                             LLM provider config (see §5.2)
│   └── obsidian.php                       Obsidian export toggle/path/folder config
│
├── storage/app/roadmap/                   Where the LIVE curriculum JSON actually lives (copied here by hand from the root master files, then "Resync roadmap" re-reads it)
│
├── tests/                                 Feature + Unit test suite (see §6.13 note / list in §9)
│
└── updates/                               Distributed-copy update-package system (see §6.12)
    ├── manifest.json                      {version, files: {path: md5}} — current shipped-state fingerprint
    └── update-NNN-<date>/                 One folder per cut package (files/, README.md, apply-update.ps1, version.txt, deletions.txt if any)
```

---

## 4. Data model

Nine business tables plus Laravel's standard framework tables (`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` — unremarkable, not detailed further). Schema below reflects the state **after all 9 app migrations**, applied in this order:

1. `2026_07_09_000001_create_study_engine_tables.php` — original 8 tables (`topics`, `transcripts`, `concepts`, `chunks`, `exercises`, `exercise_concept`, `attempts`, `struggle_notes`).
2. `2026_07_23_000001_create_phase_and_exam_tables.php` — adds `phase_progress`, `notifications`, `exam_prep_sessions`.
3. `2026_07_23_100000_add_type_to_exercises.php` — adds `exercises.type` (default `'mission'`).
4. `2026_08_11_000001_add_mode_to_study_engine_tables.php` — the dual-track migration: adds an indexed `mode` string column (default `'ai_security'`) to `transcripts`, `concepts`, `chunks`, `exercises`, `notifications`, `exam_prep_sessions`; adds `mode` (no separate index) to `topics` and `phase_progress`, and on both of those swaps their `unique(phase_number)` constraint for a composite `unique(phase_number, mode)`.
5. `2026_08_25_000001_add_research_context_to_phase_progress.php` — adds `phase_progress.research_context` (longtext, nullable).
6. `2026_08_25_000002_add_implementation_plan_to_phase_progress.php` — adds `phase_progress.implementation_plan_md` and `phase_progress.skeleton_md` (both longtext, nullable).

### `topics`
Seeded from the active track's roadmap JSON (`RoadmapService::syncTopics()`), never edited by the learner directly.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `phase_number` | unsigned int | |
| `name` | string | |
| `week_start` | unsigned int | |
| `week_end` | unsigned int | |
| `mode` | string, default `'ai_security'` | which track this topic row belongs to |

No `created_at`/`updated_at` (`Topic::$timestamps = false`). **Constraint:** `UNIQUE(phase_number, mode)` — both tracks number their phases 1..N independently, so the same phase number legitimately exists twice (once per track).

### `transcripts`
| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `topic_id` | FK → `topics`, nullable, `nullOnDelete` | |
| `raw_text` | text | the pasted transcript |
| `source_note` | string, nullable | |
| `created_at` | timestamp, default CURRENT | only column tracked (model sets `UPDATED_AT = null`) |
| `mode` | string, default `'ai_security'`, indexed | |

### `concepts`
The unit the whole spaced-repetition system revolves around.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `topic_id` | FK → `topics`, `cascadeOnDelete` | |
| `name` | string | |
| `summary` | string | one-line description |
| `first_seen_at` | timestamp, default CURRENT | |
| `ease` | float, default `2.5` | present in schema; not actually read/written by any of the current spacing logic (`ExerciseService::applyLadder()` uses a fixed ladder, not an ease factor) |
| `interval_days` | int, default `0` | current position on the ladder; `0` = "new," never yet promoted |
| `next_review_at` | date, nullable | when this concept becomes "due" |
| `lapses` | int, default `0` | count of sub-70 evaluations |
| `retired` | bool, default `false` | learner can manually retire a concept from `/concepts` |
| `mode` | string, default `'ai_security'`, indexed | |

No timestamps columns at all beyond `first_seen_at` (model `$timestamps = false`).

### `chunks`
Retrieval-augmented-generation source material — one row per ~2000-character slice of an ingested transcript, plus its embedding vector.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `transcript_id` | FK → `transcripts`, `cascadeOnDelete` | |
| `concept_id` | FK → `concepts`, nullable, `nullOnDelete` | **column exists but is deliberately never populated** — chunk-to-concept matching happens at retrieval time via cosine similarity, not a stored link |
| `text` | text | the chunk's raw text |
| `embedding` | JSON, nullable | array of floats; model casts this to/from PHP `array` |
| `mode` | string, default `'ai_security'`, indexed | |

No timestamps.

### `exercises`
A "Mission" (or Side Quest, or exam-prep-adjacent... no — exam prep is a separate table). One row per generated mission document.

| Column | Type | Notes |
|---|---|---|
| `id` | PK — this IS the "M{id}" mission reference number shown throughout the UI | |
| `date` | date | |
| `mission_md` | text | the full generated markdown mission |
| `status` | string, default `'pending'` | `pending` \| `submitted` (not actually set anywhere observed — evaluation jumps straight to `evaluated`) \| `evaluated` |
| `type` | string, default `'mission'` | `mission` \| `side_quest` |
| `mode` | string, default `'ai_security'`, indexed | |

No timestamps (`Exercise::$timestamps = false`).

### `exercise_concept` (pivot)
| Column | Type | Notes |
|---|---|---|
| `exercise_id` | FK → `exercises`, `cascadeOnDelete` | |
| `concept_id` | FK → `concepts`, `cascadeOnDelete` | |
| `role` | string | `new` \| `review` \| `side_quest` |

**Composite PRIMARY KEY** `(exercise_id, concept_id)`, no surrogate `id`. **No `mode` column** — deliberately; it scopes transitively through both parent FKs, which are themselves mode-scoped.

### `attempts`
A single submission+evaluation event against an exercise. Resubmission creates a NEW row (not an update), so an exercise can have many attempts.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `exercise_id` | FK → `exercises`, `cascadeOnDelete` | |
| `pasted_result` | text | learner's submitted work |
| `evaluation_md` | text, nullable | LLM's feedback markdown |
| `score` | unsigned int, nullable | rounded average of per-concept scores |
| `created_at` | timestamp, default CURRENT | only column tracked (`UPDATED_AT = null`) |

**No `mode` column** — scopes transitively through `exercise_id`.

### `struggle_notes`
| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `concept_id` | FK → `concepts`, `cascadeOnDelete` | |
| `note` | text | |
| `weight` | unsigned tinyint, default `1` | intended range 1–3 |
| `resolved` | bool, default `false` | |
| `created_at` | timestamp, default CURRENT | only column tracked |

**No `mode` column** — scopes transitively through `concept_id`. This is why `ChatController::learnerState()` deliberately queries struggle notes via `->whereHas('concept')` rather than a direct query — a direct unscoped query on `struggle_notes` could otherwise leak the other track's notes into a tutor prompt, since the table itself carries no mode filter of its own.

### `phase_progress`
One row per phase number (per track) tracking the Mega Portfolio Project pipeline for that phase.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `phase_number` | unsigned int | |
| `status` | string, default `'in_progress'` | `in_progress` \| `pending_project` \| `completed` |
| `mega_project_md` | text, nullable | Stage 2 output — the project brief |
| `research_context` | longtext, nullable | the pasted-back external research used to ground the brief (Stage 1→2), if that path was used |
| `implementation_plan_md` | longtext, nullable | Stage 3 output — the Milestone-Based WBS plan |
| `skeleton_md` | longtext, nullable | Stage 4 output — the plain-English pseudocode skeleton |
| `project_submission` | text, nullable | learner's submitted project work |
| `project_eval_md` | text, nullable | evaluation feedback |
| `project_score` | unsigned int, nullable | |
| `completed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |
| `mode` | string, default `'ai_security'` | |

**Constraint:** `UNIQUE(phase_number, mode)`.

### `notifications`
| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `type` | string | `checkpoint_near` \| `cert_near` (only two types actually generated by `NotificationService`, though the model/UI account for a generic `phase_ready`/`general` styling fallback too) |
| `title` | string | |
| `message` | text | |
| `link` | string, nullable | e.g. `/exam-prep` |
| `is_read` | bool, default `false` | |
| `created_at` / `updated_at` | timestamps | |
| `mode` | string, default `'ai_security'`, indexed | |

### `exam_prep_sessions`
| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `exam_target` | string | |
| `method` | string | `active_recall` \| `spaced_1357` \| `test_maker` \| `metacognitive` \| `ingested_guide` (the last one is used by `ingestStudyGuide()`, not a real "session method" the learner picks) |
| `study_guide_text` | text, nullable | |
| `session_data` | JSON, nullable | the generated question set / metadata blob, cast to array |
| `user_submission` | text, nullable | |
| `evaluation_md` | text, nullable | |
| `score` | unsigned int, nullable | |
| `created_at` / `updated_at` | timestamps | |
| `mode` | string, default `'ai_security'`, indexed | |

### Relationships summary (Eloquent, even where no DB-level FK constraint enforces it)
- `Topic` 1—* `Transcript`, `Topic` 1—* `Concept`
- `Transcript` 1—* `Chunk`
- `Concept` 1—* `StruggleNote`
- `Concept` *—* `Exercise` through `exercise_concept` (pivot carries `role`)
- `Exercise` 1—* `Attempt`

---

## 5. Core cross-cutting systems

### 5.1 The dual-track system

**`App\Support\StudyMode`** (`app/Support/StudyMode.php`) is the single source of truth for which of the two tracks — `StudyMode::AI_SECURITY` (`'ai_security'`) or `StudyMode::LARAVEL_WEB` (`'laravel_web'`) — is currently active.

- **`StudyMode::current()`**: `self::normalize(session('study_mode', self::fromFile()))`. It first checks the browser session key `study_mode`; if that key is absent, it falls back to reading the flat file `storage/app/private/active-mode.txt` (`fromFile()`, defaulting to `ai_security` if the file doesn't exist either). The result always passes through `normalize()`, which maps any unrecognized string silently back to `ai_security` — the app can never crash on a corrupted mode value.
- **`StudyMode::set(string $mode)`**: writes the normalized mode to BOTH the session (`session(['study_mode' => $mode])`, for the current browser tab) AND the flat file (for durability across CLI/artisan commands, which have no session). This dual-write is why the flat-file fallback keeps terminal commands (like `php artisan db:seed`, or `update:package`) track-aware even with no active browser session.
- **`StudyMode::label()` / `shortLabel()` / `roleWithArticle()` / `all()`**: display helpers — `all()` returns the full `[value => label]` map, used to render the dashboard's track-switcher pills.
- **`StudyMode::renderPrompt(string $raw, ?string $mode = null)`**: does a single `strtr()` pass substituting every `{{TRACK_*}}` token in a raw prompt-file string with track-specific literal text. Every prompt file that needs track-aware wording is passed through this before being sent to the LLM.

The global-scope trait **`App\Models\Concerns\ScopedToMode`** is how every study-data model gets automatically filtered to "only rows belonging to the currently active track," with zero code changes needed at any pre-existing call site:

```php
trait ScopedToMode
{
    public static function bootScopedToMode(): void
    {
        static::addGlobalScope('mode', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable().'.mode', StudyMode::current());
        });

        static::creating(function ($model) {
            $model->mode ??= StudyMode::current();
        });
    }

    public function scopeAnyMode(Builder $query): Builder
    {
        return $query->withoutGlobalScope('mode');
    }
}
```
- The global scope adds a table-qualified `WHERE {table}.mode = ?` to every query against the model.
- The `creating` event auto-fills `mode` on any new row that doesn't already have one set explicitly.
- `Model::anyMode()->...` is the deliberate escape hatch to see across both tracks (used sparingly, e.g. in reset/admin-style code).

**Models using `ScopedToMode`** (8): `Topic`, `Transcript`, `Concept`, `Chunk`, `Exercise`, `Notification`, `PhaseProgress`, `ExamPrepSession`.
**Models NOT using it**: `Attempt` and `StruggleNote` (their tables have no `mode` column at all — they scope transitively through their parent FK, `exercise_id`/`concept_id`, which is itself mode-scoped), and `User` (irrelevant to track data).

**Bulk operations that bypass Eloquent entirely** — and therefore see neither the global scope nor the `creating` event — must set `mode` explicitly on every row:
- `RoadmapService::syncTopics()`'s raw `DB::table('topics')->upsert($rows, ['phase_number','mode'], [...])` — tags every row with `StudyMode::current()` before upserting.
- The raw deletes performed by the Settings danger-zone `/settings/reset` handler (a closure in `routes/web.php`) — scoped to the active track manually since it wipes real data.

**What's isolated per track vs shared:**
- Isolated: all study data (topics/transcripts/concepts/chunks/exercises/attempts/struggle_notes/phase_progress/notifications/exam_prep_sessions), the `ActivityLog` streak file (`activity-days-{mode}.txt` — two separate files), the tutor chat session (`ChatController::chatKey()` returns `'chat_' . StudyMode::current()` — two independent session arrays), and the roadmap JSON file consulted (`RoadmapService::activeFilePath()` picks a different file per track — see §9).
- Shared (a deliberate decision, confirmed by `CLAUDE.md` and the code, not an oversight): `App\Support\LearnerName` and `App\Support\LearnerInstructions` — both flat files with no per-mode suffix, because they describe the learner as a person, not track-specific data.

### 5.2 LLM provider routing

Config lives in `config/ai.php`:

```php
'provider'       => env('AI_PROVIDER', 'openrouter'),
'embed_provider' => env('AI_EMBED_PROVIDER'),                  // no default — null means "derive it"
'fallback'       => env('AI_FALLBACK_PROVIDER', 'openrouter'),

'gemini'     => ['key' => env('GEMINI_API_KEY'), 'model' => env('GEMINI_MODEL', 'gemini-2.5-pro')],
'openrouter' => ['key' => ..., 'base_url' => ..., 'chat_model' => env('OPENROUTER_CHAT_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free'), 'embed_model' => env('OPENROUTER_EMBED_MODEL', 'openai/text-embedding-3-small')],
'ollama'     => ['base_url' => ..., 'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen2.5:7b'), 'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text')],
'anthropic'  => ['key' => ..., 'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5')],
'freellmapi' => ['key' => ..., 'base_url' => env('FREELLMAPI_BASE_URL', 'http://127.0.0.1:3001/v1'), 'chat_model' => env('FREELLMAPI_CHAT_MODEL', 'auto')],
```
Note `gemini`, `anthropic`, and `freellmapi` have **no `embed_model` key at all** — none of them are treated as first-class embedding providers by this config.

**`App\Services\LlmService`** constructor logic (paraphrased): `$provider = config('ai.provider')`. `$embedProvider = config('ai.embed_provider') ?: match($provider) { 'gemini','anthropic' => 'openrouter', default => $provider }`. In words:
1. If `AI_EMBED_PROVIDER` is explicitly set in `.env`, it always wins, no matter what the chat provider is.
2. Otherwise, embeddings default to whatever the chat provider is — **except** `gemini` and `anthropic`, which have no embeddings API in this app and automatically fall back to `openrouter`.
3. **`freellmapi` has no case in that `match`**, so with `AI_EMBED_PROVIDER` unset, embeddings would default to `freellmapi` itself, which would try (and — per its driver code — actually attempt, hardcoding `text-embedding-3-small` as the model name against FreeLLMAPI's own `/embeddings` endpoint) to embed through the FreeLLMAPI proxy. The shipped `.env` sidesteps this by setting `AI_EMBED_PROVIDER=openrouter` explicitly.

A single driver instance is reused for both chat and embedding if `$provider === $embedProvider`; otherwise two separate driver instances are constructed via `makeDriver()`, which `match`es the provider name to one of `OpenRouterDriver`, `OllamaDriver`, `AnthropicDriver`, `GeminiDriver`, `FreeLlmApiDriver` (throwing `RuntimeException` on an unknown provider string).

**Cross-provider fallback (now documented in `CLAUDE.md`'s "LLM provider" bullet too)**: `LlmService::chat()` wraps the primary driver call in try/catch. On ANY `\Throwable`, it computes a `fallbackProvider()` (reads `config('ai.fallback')`, returns null if unset, if it equals the current provider, or if the fallback provider's config array has an empty `key`). If a valid fallback exists, it logs a warning and retries **once** on a freshly constructed driver for that fallback provider — this single retry attempt is NOT itself wrapped in try/catch, so a fallback failure propagates as a real exception. This sits on top of (and is separate from) each individual driver's own internal retry logic:
- `OpenRouterDriver`: up to 2 attempts, specifically handling 200-status responses that embed an `error` key in the body (observed free-tier flakiness) and empty completions.
- `GeminiDriver`: up to 3 attempts with exponential backoff, specifically for HTTP 429/503 (rate-limited/overloaded).
- `FreeLlmApiDriver`, `AnthropicDriver`, `OllamaDriver`: no internal retry.

**Embedding call shape**: `LlmService::embed(string $text)` / `embedMany(array $texts)` delegate straight to the embed driver — `embedMany` is used everywhere possible (ingestion, concept-to-chunk retrieval) to batch all vectors into one HTTP round trip.

**`LlmService::chatJson(string $system, string $user)`**: calls `chat()` then runs the reply through a tolerant JSON parser, `parseJson()`:
```php
$reply = preg_replace('/^```(?:json)?|```$/m', '', trim($reply));   // strip markdown fences if present
$start = strpos($reply, '{'); $end = strrpos($reply, '}');
$candidate = substr($reply, $start, $end - $start + 1);
// try json_decode($candidate); on failure, retry once after escapeControlChars($candidate)
```
`escapeControlChars()` is a small state machine that walks the string character by character, tracking whether it's currently inside a JSON string literal, and only escapes raw control characters (newlines, tabs, etc. below `0x20`) when inside a string — this recovers otherwise-invalid JSON from models that emit literal newlines inside a JSON string value (e.g. multi-paragraph markdown feedback) instead of the required `\n` escape sequence, without corrupting anything outside string literals. If both attempts fail, it throws `RuntimeException('LLM returned unparseable JSON (...)')` including the raw reply for debugging.

**Embedding model constancy is a hard rule, not just convention**: stored 1536-dimensional vectors (`chunks.embedding`) are only mathematically comparable to new query vectors produced by the *exact same* embedding model. Nothing in the code enforces this — it is purely an operational discipline documented in `CLAUDE.md` and the README: never change `AI_EMBED_PROVIDER`/the embedding model once real chunks exist, or every future cosine-similarity retrieval against old chunks becomes meaningless.

### 5.3 The `{{TRACK_*}}` prompt templating system

Four of the five live prompt files (`exercise-engine.md`, `tutor-chat.md`, `mega-project.md`, `side-quest-engine.md`) contain `{{TRACK_*}}` placeholder tokens. `exam-prep.md` contains none (its content is track-agnostic by design — "Certification Coach" wording works for either track unmodified). Every call site that loads one of these files always pipes it through `StudyMode::renderPrompt()` before sending it to the LLM, even for `exam-prep.md` (a harmless no-op there, since there's nothing to substitute).

All 12 tokens `StudyMode::renderPrompt()` knows about, and their literal replacement text:

| Token | `ai_security` | `laravel_web` |
|---|---|---|
| `{{TRACK_ROLE}}` | AI Security Engineer | Laravel & React Web Developer |
| `{{TRACK_ROLE_WITH_ARTICLE}}` | an AI Security Engineer | a Laravel & React Web Developer |
| `{{TRACK_ROADMAP_DESC}}` | a fixed 21-phase roadmap | your Laravel & React roadmap |
| `{{TRACK_QUALITY_DIMENSION}}` | AI Security Controls | Code Architecture & Best Practices |
| `{{TRACK_FORESHADOW_EXAMPLES}}` | model routing, input validation gates, log triage, secret handling | queues, API design, deployment pipelines |
| `{{TRACK_EVALUATOR_TITLE}}` | Senior AI Security Architect | Senior Laravel & React Architect |
| `{{TRACK_PROBLEM_LABEL}}` | AI Security | Web Application |
| `{{TRACK_PROBLEM_EXAMPLES}}` | threat detection, prompt injection defense, red teaming, vector store security, agent sandbox security, ML model auditing, guardrails, LLM observability, or security log analysis | a booking or reservation system, an inventory/order-management tool, a content platform with roles and permissions, a REST API consumed by a frontend, or an authenticated reporting dashboard |
| `{{TRACK_PROBLEM_RATIONALE}}` | why an AI Security Engineer builds this, real-world attack vectors or vulnerabilities addressed | why a Laravel & React Web Developer builds this, real-world user needs or business requirements addressed |
| `{{TRACK_CONSIDERATIONS}}` | security considerations | code quality considerations |
| `{{TRACK_ARCHITECT_TITLE}}` | security architect | software architect |
| `{{TRACK_FLAW_LABEL}}` | security flaws | bugs or design flaws |

Every token used in any prompt file has a definition here (no orphans), and every defined token is used by at least one file. `StudyMode::normalize()` guarantees the mode key passed into this lookup is always one of the two known values (falling back to `ai_security` otherwise), so `renderPrompt()` can never fail to find a substitution.

### 5.4 Design system / CSS conventions

`resources/css/app.css` defines Tailwind v4 `@theme` tokens ("ops-console" palette — warm paper background, near-black "ink" text/buttons, one cyan-teal accent color, the dark hero panel being the only intentionally loud surface) and `@layer components` classes reused everywhere instead of ad-hoc utility soup:

- `.btn` / `.btn-accent` / `.btn-outline` / `.btn-sm` — button variants.
- `.input` — text input/textarea/select styling.
- `.card` — the base panel (`rounded-xl border border-line bg-surface shadow-xs`) used for almost every content block in the app.
- `.hero` / `.hero-eyebrow` — the dashboard's dark greeting panel.
- `.progress-track` / `.progress-seg` (with `.is-done` / `.is-current`, the latter pulsing via a `seg-pulse` keyframe animation unless `prefers-reduced-motion`) — the phase-by-phase roadmap progress bar.
- `.eyebrow` — small uppercase monospace kicker label.
- `.badge` + `.badge-pending` / `.badge-evaluated` / `.badge-submitted` / `.badge-quiet` — status pills keyed to `Exercise::status`.
- `.stat-num` — large tabular-nums monospace number for dashboard stat tiles.
- `.label` — form field label styling.

The **`.mission`** block is the styling applied to every place raw markdown gets rendered via `Str::markdown()` — mission text, evaluation feedback, mega-project briefs/plans/skeletons, exam-prep feedback, and tutor chat replies all reuse it. It styles headings, paragraphs, lists, `<strong>`, inline `<code>`, `<pre>`/code blocks, and `<blockquote>`.

**Recently fixed bug, worth knowing about so it isn't reintroduced**: Tailwind v4's `preflight` reset zeroes out default browser table borders and `<hr>` borders. This was silently breaking every markdown-rendered table/divider inside `.mission` content (mission tables, evaluation tables, mega-project plan tables all rendered with invisible borders). The fix adds explicit rules scoped to `.mission`:
```css
.mission hr {
    border: 0; border-top: 1px solid var(--color-line); margin: 1.75rem 0;
}
.mission table {
    display: block; overflow-x: auto; max-width: 100%;
    width: 100%; border-collapse: collapse; margin: 1rem 0 1.25rem;
    font-size: 0.85rem; line-height: 1.5;
}
.mission thead { background: var(--color-paper); }
.mission th {
    text-align: left; font-weight: 650; color: var(--color-ink);
    padding: 0.55rem 0.85rem; border: 1px solid var(--color-line);
    white-space: nowrap;
}
.mission td {
    padding: 0.55rem 0.85rem; border: 1px solid var(--color-line);
    vertical-align: top; color: var(--color-ink-soft);
}
.mission tbody tr:nth-child(even) { background: var(--color-paper); }
```
`display: block; overflow-x: auto` on the `table` element itself makes wide tables scroll horizontally inside their own box rather than breaking the page's layout; `tbody tr:nth-child(even)` zebra-stripes rows. Any future work touching `.mission` CSS should preserve these rules — a Tailwind version bump or preflight change could silently re-break table/hr rendering again.

There is also a **mission table-of-contents / scroll-spy** feature, purely client-side, paired with CSS: it only activates on pages containing BOTH `article.mission[data-outline]` (only `exercise/show.blade.php`'s mission body carries this attribute) and a `#mission-toc` sidebar element, and only if the mission has ≥2 h2/h3 headings. See §5.5 for the JS; the CSS side (`.toc-link`, `.toc-sub`, `.toc-link.active`, `scroll-margin-top` on `.mission h2/h3`, `html { scroll-behavior: smooth }`) provides the "Google-Docs-style" current-section highlight and smooth anchor-jump behavior.

### 5.5 The `data-llm` JS convention and shared layout JS

Every Blade form that triggers an LLM call is marked `<form data-llm>`. A single delegated listener in `resources/views/layouts/app.blade.php` handles all of them uniformly:
```js
document.querySelectorAll('form[data-llm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        const button = e.submitter || form.querySelector('button');
        if (!button) return;
        setTimeout(() => {
            button.disabled = true;
            button.classList.add('animate-pulse');
            button.textContent = 'Working — the model can take a minute or two…';
        }, 0);
    });
});
```
It disables the submitting button, adds Tailwind's `animate-pulse` class, and swaps its label to a "Working…" message — deferred one tick via `setTimeout(...,0)` so it doesn't race the native form submission. This single mechanism is reused across mission generation, ingest preview/confirm, chat send, exercise submit, phase project/plan/skeleton generation and submission, exam-prep start/submit, and side-quest launch — no page defines its own loading-state JS.

The layout also defines the **mission outline scroll-spy** (see §5.4):
```js
const outline = document.querySelector('article.mission[data-outline]');
const tocBox = document.getElementById('mission-toc');
if (outline && tocBox) {
    const heads = [...outline.querySelectorAll('h2, h3')];
    if (heads.length >= 2) {
        // insert "On this page" label, build one <a class="toc-link"> per heading,
        // assign each heading a synthetic id="sec-N",
        // watch them with an IntersectionObserver (rootMargin: '-15% 0px -75% 0px')
        // and toggle .active on whichever TOC link matches the currently-intersecting heading
    }
}
```
And the mobile sidebar toggle (`openSidebar()`/`closeSidebar()`, toggling a `.open` class plus `aria-expanded`, closable via `Escape`). No other shared/global JS exists in the layout; page-specific behavior (e.g. the Mega Project research-flow's fetch/clipboard logic) is written inline in that page's own Blade file.

---

## 6. Every user-facing workflow, end-to-end

### 6.1 Dashboard (`/`)

**Entry point**: `GET /` → `DashboardController::index()`.

The dashboard is the home page and mission-launch hub. The controller gathers, in one pass: the current roadmap week (`RoadmapService::currentWeek()`), the full topic list, the current phase's topic row, unread notifications (`NotificationService::getUnread()` — this call has the side effect of generating any newly-due notifications first), a phase-completability check for the current phase (`PhaseService::checkPhaseCompletable()`), the set of completed phase numbers, today's (or the latest unfinished) exercise, the due-concept count, total concept count, missions/side-quests-completed counts, the streak (`ActivityLog::streak()`), the learner's name, and a computed roadmap-progress summary (`% covered`, phases done, weeks left).

The view (`dashboard.blade.php`) renders:
- A dark **hero panel** greeting the learner by name, showing the current phase/week, and a **track-switcher** — one small form per `StudyMode::all()` entry posting to `POST /settings/mode`, the active track rendered as a disabled white pill.
- A **`.progress-track`** bar — one segment per phase, sized proportionally to its week span, colored done/current/upcoming.
- An **exam-alert banner** if any notifications are pending, linking to `/exam-prep`.
- An **Active Phase card** (if a current phase exists) with a "Mark Phase Completed" button and two status panels (topic-coverage check, Mega Project pass/score).
- A **5-tile stat grid**: due-for-review count, streak, total concepts, missions completed, side quests completed.
- A **mission action card**: if a mission exists for today (or an unfinished one is pending from an earlier day — the "Unfinished mission" banner), a button links straight to `/exercise/{id}`; otherwise a `POST /exercise/generate` form (`data-llm`) with the shared difficulty/drills/bosses controls (`partials/generation-options.blade.php`) plus quick links to `/ingest` and `/side-quest`.

**`POST /exercise/generate`** → `DashboardController::generate()`: validates `difficulty` (nullable, in Easy/Medium/Hard), `drills` (nullable int 0–20), `bosses` (nullable int 0–10); calls `ExerciseService::generate()`. On a `RuntimeException` (e.g. "nothing to practice yet" or the drills=0-and-bosses=0 rejection), redirects back to `/` with the exception message as the flash `status`. On success, redirects to `/` with no explicit flash — the newly created mission is simply picked up by `index()`'s next render.

### 6.2 Ingestion (`/ingest`)

Two-step preview/confirm flow, entry point `GET /ingest` → `IngestController::show()` (lists all topics for the picker).

**Step 1 — `POST /ingest/preview`** → `IngestController::preview()`: validates `raw_text` (required), `source_note` (nullable, ≤255 chars), `topic_id` (nullable, must exist). Calls `IngestService::extractConcepts($rawText, $topic)`:
- Chunking happens later, not here — this step is purely an LLM call asking for JSON: `{"topic_phase_number": <int>, "concepts": [{"name": "...", "summary": "..."}]}`. If a topic was pre-picked, the LLM is told the phase is already known; otherwise it's given the full topic list and asked to infer the best-matching phase.
- The controller resolves `$topic` (either the one explicitly picked, or the one matching the LLM's guessed `topic_phase_number`, or — as a last resort — the first topic in the system).
- Renders `ingest/confirm.blade.php` with an editable checklist of extracted `{name, summary}` pairs (each with a `keep` checkbox — unchecked ones are discarded on confirm), the topic picker, and the shared difficulty/drills/bosses controls.

**Step 2 — `POST /ingest/confirm`** → `IngestController::confirm()`: validates the full concepts array (`concepts.*.name`, `concepts.*.summary` required, `concepts.*.keep` optional), `topic_id` (now required), and an `action` field (`save` or `save_generate`). Filters to only `keep`-checked concepts. Calls `IngestService::store($topic, $rawText, $sourceNote, $keptConcepts)`:
1. `IngestService::chunk()` splits `raw_text` into ~2000-character pieces with 200-character overlap (`$step = size - overlap`; loop advances `$start` by `$step` each time, stopping once the last chunk would reach the end of the text). This approximates ~500-token chunks with ~50-token overlap; mid-word cuts are considered harmless for embedding purposes.
2. All chunks are embedded in **one batched `embedMany()` call**.
3. Inside a DB transaction: creates the `Transcript` row, one `Chunk` row per chunk+vector pair, and for each kept concept — looks up an existing `Concept` by case-insensitive name match (`whereRaw('lower(name)=?', ...)`); if found, links it (no summary merge, the existing summary wins); if not, creates a new one with `next_review_at = tomorrow`.
4. Returns `{new, linked, chunks, concept_ids}` counts.

If `action === 'save_generate'`, the controller then calls `ExerciseService::generateFromConcepts($conceptIds, ...)` and redirects straight to the new mission's page; otherwise it redirects back to `/ingest` with a summary flash message ("Saved: N chunks embedded, N new concepts (review tomorrow), N already known.").

### 6.3 Mission generation (dashboard path vs transcript-driven path)

Both paths funnel through the same private `ExerciseService::storeMission()`, but they differ in how concepts are chosen and in daily-frequency limits:

- **Dashboard path** (`ExerciseService::generate()`): one mission per calendar day. If `todays()` (an `Exercise` dated today) already exists, it's returned unchanged rather than regenerating. Otherwise calls the public `selectConcepts()` algorithm (see §6.4) to get `[new, review]` concept collections.
- **Transcript-driven path** (`ExerciseService::generateFromConcepts($conceptIds, ...)`): called right after ingestion. The just-saved concept ids become the `[new]` set unconditionally (even if they'd technically already be "due" or not-new by ladder position — this path trusts the caller). The `[review]` set is `reviewSelection(cap, excludeIds: $conceptIds)` where `cap = max(2, ceil(count(new) * 2/3))` — same ~60/40 ratio, but drawing from the general review pool while excluding the concepts just ingested. **This path allows multiple missions per day** (no `todays()` short-circuit) — it's how "Save & generate drills" on the ingest-confirm page can be used more than once daily.

`storeMission($new, $due, $difficulty, $drills, $bosses, $type)`:
1. Rejects `drills === 0 && bosses === 0` with a `RuntimeException` (learner must request at least one of the two).
2. `ExerciseService::retrieveChunks($new->concat($due))` (see §6.4 for the retrieval algorithm).
3. Picks the system prompt file — `side-quest-engine.md` if `$type === 'side_quest'`, else `exercise-engine.md` — renders it through `StudyMode::renderPrompt()`, and calls `LlmService::chat()` (plain markdown reply, not JSON) with a MODE-1 request built by `buildRequest()`: today's date, current phase/week, requested difficulty/drills/bosses, concept lines tagged `[NEW]` or `[REVIEW, lapses=N]`, unresolved struggle notes for those concepts, retrieved chunks, and the learner's personal instructions (or `(none)`).
4. Inside a DB transaction: creates the `Exercise` row and attaches `$new` concepts with pivot `role='new'`, `$due` concepts with `role='review'`.
5. Outside the transaction, best-effort exports the mission to Obsidian (see §6.11) — failures here are swallowed (`rescue()`), never blocking mission creation.

**`ExerciseService::fromChat(string $missionMd)`**: the "Save as mission" path from tutor chat (see §6.9). Lower-cases the mission text and finds every non-retired `Concept` whose lower-cased name is a literal substring of it; creates an `Exercise` (`status='pending'`) and attaches all matched concepts with `role='review'`, inside a transaction. No LLM call — the mission text already exists (it's a prior chat reply).

**`ExerciseController::addMore()`** (`POST /exercise/{exercise}/add-more`): generates a follow-up mission reusing the same concepts already attached to an existing exercise, via `generateFromConcepts()`.

### 6.4 Concept selection algorithm & retrieval (the heart of the spacing system)

**`ExerciseService::selectConcepts(): array`** — used only by the dashboard path:
```php
$new = Concept::where('retired', false)->where('interval_days', 0)
    ->orderByDesc('first_seen_at')->get();

return $new->isEmpty()
    ? [$new, $this->reviewSelection(8, dueOnly: true)]
    : [$new, $this->reviewSelection(max(2, (int) ceil($new->count() * 2/3)))];
```
- "New" = **every single** non-retired concept that has never been successfully evaluated yet (`interval_days === 0`) — none are ever dropped; missions scale up to cover all of them.
- If there IS new material: the review slot count is `max(2, ceil(newCount * 2/3))` — targeting roughly a 60% new / 40% review composition.
- If there is NO new material: falls back to up to 8 strictly-due (not-yet-due-backfill-excluded) review concepts.
- If neither exists, both collections come back empty and `generate()` throws.

**`ExerciseService::reviewSelection(int $cap, array $excludeIds = [], bool $dueOnly = false): Collection`** (the shared review-picking logic behind both `selectConcepts()` and `generateFromConcepts()`):
1. Pulls all non-retired, previously-reviewed concepts (`interval_days > 0`), excluding `$excludeIds`.
2. Computes `feedbackWords()` — words ≥4 characters parsed out of the learner's free-text `LearnerInstructions`, minus a ~30-word filler blacklist (`with, that, this, from, have, when, will, ...`).
3. Splits the pool into `$flagged` (concept name contains any feedback word) vs `$rest` — flagged concepts are prioritized ahead of everything else regardless of due status. This is how a learner typing "I'm shaky on dict methods" into Settings → Personal Instructions actually pulls "dict methods"-named concepts into the front of the review queue on the very next mission.
4. `$rest` is split into `$due` (next_review_at ≤ today) and `$upcoming` (not yet due).
5. Final order: `$flagged` (sorted lapses-desc then next_review_at-asc) → `$due` (same sort) → unless `$dueOnly`, `$upcoming` (sorted next_review_at-asc, i.e. soonest-due-in-the-future first) as a **backfill** — this is what guarantees true interleaving of already-learned material even on days when technically nothing is "due," per an explicit user requirement noted in the code.
6. `->take($cap)`.

**`ExerciseService::retrieveChunks(Collection $concepts, int $perConcept = 2): Collection`** — the retrieval-augmented-generation step. Loads every `Chunk` row (no filtering — full table scan), embeds all concepts' `"{name}: {summary}"` strings in one batched `embedMany()` call, then for each concept sorts ALL chunks by `cosine(conceptVector, chunkVector)` descending and takes the top 2, concatenating and de-duplicating across concepts. This is explicitly an O(concepts × chunks) full scan done in PHP (not SQLite-vec, not any ANN index) — acceptable per `CLAUDE.md` at "hundreds of chunks" scale, not designed to scale further.
```php
function cosine(array $a, array $b): float {
    $dot = $normA = $normB = 0.0;
    foreach ($a as $i => $v) { $dot += $v*$b[$i]; $normA += $v*$v; $normB += $b[$i]*$b[$i]; }
    return ($normA && $normB) ? $dot / (sqrt($normA)*sqrt($normB)) : 0.0;
}
```

### 6.5 Evaluation loop & the 1/3/7/14/30 spacing ladder

**`ExerciseController::submit()`** (`POST /exercise/{exercise}/submit`) → validates `pasted_result` (required) → `ExerciseService::evaluate($exercise, $pastedResult)`:

1. `buildEvalRequest()` assembles the MODE-2 request: the original `mission_md`, the pasted result, a `## Previous evaluations of this mission (oldest first)` section containing the last 5 attempts' `evaluation_md` blobs (oldest-first, `(none — this is the first attempt)` if there are none) — this is the entire mechanism by which the stateless evaluator can "remember" prior context like a Boss Fight's already-approved skeleton (see §6.6) — and the concept list with roles.
2. `LlmService::chatJson()` (JSON mode) against `exercise-engine.md`'s MODE 2 rules, expecting `{feedback_md, concept_scores: [{concept_id, score, evidence}], suggested_struggle_notes: [{concept_id, note, weight}]}`.
3. Only `concept_id`s actually attached to the exercise are trusted (hallucinated ids from the LLM are silently ignored); scores are clamped to `[0,100]`; a concept the LLM simply fails to score is skipped, never punished.
4. Inside a transaction: creates an `Attempt` (`score` = rounded average of the trusted per-concept scores, or `null` if none scored), creates a `StruggleNote` per suggested note (weight clamped `[1,3]`), and for each scored concept calls `applyLadder()`. Marks the exercise `status = 'evaluated'`.
5. Outside the transaction, best-effort re-exports the (now-evaluated) mission to Obsidian.

**`ExerciseService::applyLadder(Concept $concept, int $score, bool $hasFreshNote)`** — the constant `LADDER = [1, 3, 7, 14, 30]` is the sole source of truth for interval progression:
```php
if ($score >= 70) {
    $interval = collect(self::LADDER)->first(fn ($d) => $d > $concept->interval_days) ?? 30;
} else {
    $interval = max(1, intdiv($concept->interval_days, 2));
    $concept->lapses++;
    if (!$hasFreshNote) {
        // boost the latest unresolved struggle note's weight (capped at 3), or create a new one
        // with auto-text "Scored {score}/100 on evaluation — needs another pass."
    }
}
$concept->interval_days = $interval;
$concept->next_review_at = today()->addDays($interval)->toDateString();
$concept->save();
```
- **Promotion** (score ≥ 70): jumps to the smallest ladder rung strictly greater than the current interval (0→1→3→7→14→30), staying capped at 30 forever after (once `interval_days ≥ 30`, `first()` finds nothing and falls back to `?? 30`).
- **Lapse** (score < 70): halves the current interval (floor 1 day, never zero), increments `lapses`, and — unless the evaluation JSON already suggested a fresh note for this concept — either bumps an existing unresolved note's weight (max 3) or creates a new auto-generated one.

**Resubmission**: `ExerciseController::reset()` (`POST /exercise/{exercise}/reset`) deletes all attempts on that exercise and resets `status` back to `pending`, letting the learner submit fresh — `evaluate()` is idempotent-safe to call again and re-runs the whole ladder logic on the new submission.

**Learner's own note**: `ExerciseController::note()` (`POST /exercise/{exercise}/note`) → `ExerciseService::addOwnNote()` — creates a weight-2 `StruggleNote` for every concept attached to that exercise, independent of any LLM call.

### 6.6 Boss Fight skeleton protocol (mission-internal — distinct from the Mega Project's "skeleton")

Every Mission's Boss Fight section ends with a `**Protocol:**` block that (per `exercise-engine.md`'s MODE 1 rules) requires the learner to submit **two things together**: (1) a numbered, plain-English step-by-step skeleton of their intended logic, and (2) their complete working code, in the same submission.

On evaluation (`exercise-engine.md` MODE 2 rules — this logic is entirely prompt-side, `ExerciseService` never inspects the Boss Fight text itself):
- Code present but no skeleton → the evaluator asks for resubmission with a skeleton, leaves the Boss Fight's concepts unscored (drills are still scored normally), no penalty.
- Both present → the evaluator judges the skeleton's logic first. If sound, its `feedback_md` MUST contain the literal marker text **"Skeleton approved"**, then it evaluates the code against both the skeleton and the mission's own published solution, scoring all Boss Fight concepts. If the skeleton is flawed, it explains the fix but still evaluates the submitted code.

`ExerciseService`'s only mechanical involvement is shipping the last 5 raw `evaluation_md` blobs (§6.5, `buildEvalRequest()`) back to the (stateless, per-call) evaluator so it can see its own prior "Skeleton approved" text and reason about phase/continuity across resubmissions — the state machine itself lives entirely in the prompt text, not in PHP.

`tutor-chat.md`'s "Boss Fight coaching" section describes a richer, layer-by-layer coaching version of the same underlying idea for use in `/chat`: if a learner asks for Boss Fight help without having shown a skeleton yet, the tutor should tease them lightly and demand the skeleton first; once judged sound, it should "guide the build one architectural layer at a time — outer structure first, then data handling, then inner logic — validating each layer before naming the next." `side-quest-engine.md`'s own MODE 2 describes an even more explicit three-state machine (unsubmitted → approved → layer-by-layer) for Side Quest Boss Fights specifically — `ExerciseService::evaluate()` now actually routes to it for `Exercise::type === 'side_quest'` (see §6.10 and the fixed cross-wire note in §9), so this richer protocol is live, not dead code.

**⚠️ This is a completely different feature from the Mega Portfolio Project's Stage-4 "skeleton"** (§6.7) — see the explicit naming-collision callout at the end of §6.7.

### 6.7 Mega Portfolio Project (the 4-stage flow under `/phases/{n}`)

Entry point: `GET /phases/{phase}` → `PhaseController::show()`. Data comes from `PhaseService::checkPhaseCompletable($phase)` plus the roadmap phase info. All four stages are optional/sequential and gated in order; each generation call is a separate LLM round trip stored into a different `phase_progress` column.

**Concept mastery check** (shown at the top of the page, non-blocking): `PhaseService::checkPhaseCompletable()` compares the phase's required-topic keyword list (`RoadmapService::getRequiredTopicsForPhase()`) against the learner's actual ingested `Concept` names/summaries for that phase's topic (case-insensitive substring match). Missing topics are listed as **advisory only** — they never block phase completion, only the project score does (see below).

**Stage 1 — Research prompt** (`GET /phases/{phase}/research-prompt`, JSON endpoint, called via `fetch()` from an inline script in `phases/show.blade.php`, not a page navigation): `PhaseService::generateResearchPrompt($phase)` builds a large hand-authored prompt — this text is **never sent to this app's own LLM** — it's meant to be copied and pasted by the learner into an external Claude Pro session to do real, up-to-date (2026) market/industry research. The prompt embeds the learner's full concept history up to and including the current phase (a `$priorPhaseSummary` listing every earlier phase's learned concepts), the current phase's concepts, the target role (`StudyMode::label()`), and asks for: Top 5 hottest project areas, key technologies, attack vectors (or, for Laravel Web, presumably the analogous "problem space" framing via the `{{TRACK_*}}` tokens — though this specific prompt in `PhaseService` is hand-written PHP text rather than a `resources/prompts/*.md` file, so it does not itself go through `StudyMode::renderPrompt()`'s token system; it interpolates `StudyMode::label()` directly instead), what makes a standout project, emerging standards, and a final recommendation. The Blade UI shows this in a readonly textarea with a "Copy Prompt to Clipboard" button (falls back to `.select()` if the Clipboard API is unavailable).

**Stage 2 — Generate the brief** (two possible paths, both populate `phase_progress.mega_project_md`):
- **Research-grounded**: `POST /phases/{phase}/project-from-research` → `PhaseController::generateFromResearch()` (validates `research`, required, ≥100 chars) → `PhaseService::generateMegaProjectFromResearch($phase, $researchText)`. Sends `mega-project.md`'s MODE 1 (`MODE: GENERATE_MEGA_PROJECT`) with the pasted-back research embedded as a `## REAL-WORLD RESEARCH` section, instructing the brief to be grounded in it.
- **Legacy/quick-regenerate**: `POST /phases/{phase}/project` → `PhaseController::generateProject()` → `PhaseService::generateMegaProject($phase)`. Same MODE 1 prompt, no research context — used for the "Quick Regenerate" button once a brief already exists.

**Both paths null out the downstream artifacts on regeneration** — confirmed directly in the code:
```php
$progress->update([
    'mega_project_md' => $briefMd,
    // (research_context => $researchContext, only on the research-grounded path)
    'implementation_plan_md' => null,
    'skeleton_md' => null,
    'status' => $progress->status === 'completed' ? 'completed' : 'pending_project',
]);
```
So regenerating the brief always invalidates Stage 3 and Stage 4's outputs, forcing them to be regenerated — they'd otherwise reference a brief that no longer exists.

**Stage 3 — Implementation Plan** (`POST /phases/{phase}/implementation-plan` → `PhaseController::generateImplementationPlan()` → `PhaseService::generateImplementationPlan($phase)`): **requires a brief to already exist** — throws `RuntimeException('Generate the Mega Project brief before generating an implementation plan.')` otherwise. Sends `mega-project.md`'s MODE 3 (`MODE: GENERATE_IMPLEMENTATION_PLAN`) with the full brief embedded, storing the result into `implementation_plan_md`. This MODE encodes a **"Milestone-Based WBS with Definition-of-Done Gates"** method:
- Core principle (quoted from the prompt): *"Nothing is 'in progress' or 'mostly done.' Every unit of work resolves to a binary checklist, and every milestone ends in a hard gate that must be 100% true before the next milestone starts. Prose only appears in clearly labeled 'index' or 'summary' blocks that are explicitly marked non-authoritative; the checklists are the only controlling content."*
- Each milestone (`M1`, `M2`, ...) contains, in order: a **snapshot block** (counts/named items only, "index only, non-controlling"), a **quick-summary block** (1–3 plain-English paragraphs, "non-controlling"), numbered subsections (`M1.1`, `M1.2`, ...) of strictly binary `- [ ]` checklist items (no vague qualifiers like "mostly" or "as needed," with explicit "do NOT" boundary items where scope creep is likely), a rolled-up **FINAL ACCEPTANCE CRITERIA** checklist, and a short **COMPLETE ONLY WHEN:** closing gate (2–6 hard conditions).
- Optional **Architecture Lock** — if a milestone mandates a specific pattern, it's stated once at the top with a one-line disambiguation from similarly-named patterns (the prompt's own example: "this is the Repository Pattern, not the Decorator Pattern, even though a Decorator may wrap it later").
- Optional **Blocker Handling Rule** — if a milestone depends on a third-party/external service: document blockers, keep unblocked work moving, never silently cut scope or extend the deadline.
- Explicitly excludes legal/contractual language (this is a solo learner project, not a client engagement).

**Stage 4 — Plain-English Skeleton** (`POST /phases/{phase}/skeleton` → `PhaseController::generateSkeleton()` → `PhaseService::generateSkeleton($phase)`): **requires the implementation plan to already exist** — throws `RuntimeException('Generate the implementation plan before generating a skeleton.')` otherwise. Sends `mega-project.md`'s MODE 4 (`MODE: GENERATE_PLAIN_ENGLISH_SKELETON`) with both the brief and the full plan embedded, storing the result into `skeleton_md`. This produces a whole-project, milestone-by-milestone, **no-real-code** pseudocode walkthrough — one `## Skeleton for Milestone N` section per plan milestone, each one required to be "traceable back to a specific milestone or checklist item in the Implementation Plan" (no invented requirements), plus a data/control-flow summary and a plain-English suggested file/module map.

**Submission & evaluation** (`POST /phases/{phase}/submit` → `PhaseController::submitProject()` → `PhaseService::evaluateMegaProject($phase, $submission)`): if no brief exists yet, one is silently auto-generated via the legacy (no-research) path first, rather than erroring. Sends `mega-project.md`'s MODE 2 (`MODE: EVALUATE_MEGA_PROJECT`), with the grading context **conditionally including** whichever of the plan/skeleton actually exist:
```php
$userPrompt = "MODE: EVALUATE_MEGA_PROJECT\n\nProject Brief:\n{$progress->mega_project_md}\n\n";
if ($progress->implementation_plan_md) {
    $userPrompt .= "Implementation Plan (check the submission against its milestone gates):\n{$progress->implementation_plan_md}\n\n";
}
if ($progress->skeleton_md) {
    $userPrompt .= "Plain-English Skeleton (reference structure):\n{$progress->skeleton_md}\n\n";
}
$userPrompt .= "Learned Concepts: {$conceptNames}\n\nLearner Submitted Code & Documentation:\n{$submission}";
```
A learner who never generated Stage 3/4 still gets evaluated against just the brief. Response JSON: `{feedback_md, overall_score, passed, concept_scores: [{concept_name, score, feedback}], security_insights}` (`passed` defaults to `score >= 70` if the model omits it). Storing the result auto-completes `phase_progress.status` to `'completed'` (with `completed_at`) when `passed` is true — the topic-coverage check remains advisory-only and never blocks this.

**`checkPhaseCompletable()` / `POST /phases/{phase}/complete`**: `is_completable` is purely `$projectPassed` (`project_score >= 70`) — missing topics only add an advisory reason string, never block. `markPhaseCompleted()` re-checks completability and, if satisfied, `firstOrCreate`s/updates `phase_progress` to `status='completed'`.

**Document export** (`GET /phases/{phase}/export/{document}/{format}` → `PhaseController::export()`): `$document` ∈ `{brief, plan, skeleton}` (mapping to `mega_project_md`/`implementation_plan_md`/`skeleton_md`), `$format` ∈ `{txt, md, doc, docx}`; anything else → `404`. If the target column is empty (not generated yet) → `404`. Delegates rendering to `DocumentExportService::export($content, $title, $format)`:
- `.txt` / `.md`: the raw stored markdown, served with `text/plain`/`text/markdown` content-type respectively — no transformation.
- `.doc`: converts markdown to HTML via `Str::markdown($markdown, ['html_input'=>'strip', 'allow_unsafe_links'=>false])`, wraps it in an HTML document carrying Microsoft Office XML namespaces (`xmlns:o`, `xmlns:w`) and a `<!--[if gte mso 9]>` Word-View/Zoom conditional-comment block, served with `Content-Type: application/msword`. This is the classic "HTML masquerading as .doc" trick — Word opens plain HTML directly when it's served with a `.doc` extension/mimetype; no real binary Word format is generated.
- `.docx`: a **genuinely valid, hand-rolled minimal OOXML package**, built with PHP's built-in `ZipArchive` — no external Composer library. `toDocx()` builds `<w:body>` paragraph XML via `markdownToDocxParagraphs()`:
  - Headings `#`–`######` → sized/bold text runs (half-point font sizes `[1=>32, 2=>28, 3=>26, 4=>24, 5=>22, 6=>20]`, i.e. H1=16pt down to H6=10pt).
  - Checklists `- [ ]` / `- [x]` (case-insensitive) → `☐ ` / `☑ ` prefixed, indented paragraphs.
  - Bullets `- ` / `* ` → `• ` prefixed, indented paragraphs.
  - Fenced code blocks → each line its own paragraph in Consolas (`<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>`).
  - Inline `**bold**` text → `parseInlineRuns()` splits the line on `\*\*(.+?)\*\*` with `PREG_SPLIT_DELIM_CAPTURE`, producing an alternating plain/bold/plain/... array, each part becoming its own `<w:r>` run (bold = odd array index).
  - The final zip is exactly **three parts** — no `word/styles.xml`, no core properties, no theme:
    1. `[Content_Types].xml` — declares default content types plus an `Override` for `/word/document.xml`.
    2. `_rels/.rels` — the package-level relationship `rId1` pointing at `word/document.xml`.
    3. `word/document.xml` — the actual `<w:body>` content plus a fixed `<w:sectPr>` (US Letter page size, 1-inch margins).
  - Formatting is applied via **direct run properties** (`<w:rPr>` on each `<w:r>`), not paragraph styles, since there's no `styles.xml` to reference.
  - Written to a `tempnam()` temp file via `ZipArchive::open(..., OVERWRITE)`, three `addFromString()` calls, `close()`, bytes read back and the temp file deleted.

The three export buttons appear via one shared partial, `resources/views/phases/partials/export-buttons.blade.php`, included once under each of the brief/plan/skeleton sections in `phases/show.blade.php` — it just renders four links to `/phases/{phase}/export/{document}/{format}` for `txt`/`md`/`doc`/`docx`, parameterized by which `$document` it was included with.

**⚠️ Naming collision, stated explicitly**: "skeleton" means two unrelated things in this codebase.
1. **Boss Fight skeleton** (§6.6): a short, **learner-written**, per-drill plain-English numbered plan submitted alongside actual code for ONE Boss Fight inside a regular Mission, gated by the literal evaluator marker text **"Skeleton approved"**, coached "one architectural layer at a time."
2. **Mega Project skeleton** (this section, Stage 4): an **LLM-generated**, whole-project, multi-milestone pseudocode document grounded in the Stage-3 Implementation Plan, stored in `phase_progress.skeleton_md`, used purely as reference material fed back into project grading — nothing about it is learner-submitted or gate-approved section by section.
They share zero code, zero data flow, and zero UI. Do not assume the Boss Fight's approval-gate mechanic applies to the Mega Project skeleton, or vice versa.

### 6.8 `/phases` roadmap timeline

`GET /phases` → `PhaseController::index()`: for every phase in the active roadmap, computes `checkPhaseCompletable()`, and renders `phases/index.blade.php` as a vertical timeline — one row per phase with a numbered/checkmarked circle, week range, topic-coverage indicator, and Mega Project score/status indicator, each linking to `/phases/{n}`.

### 6.9 Tutor chat (`/chat`)

`GET /chat` → `ChatController::show()` renders the session-held message history (`session('chat_' . StudyMode::current())`, capped at 30 entries).

`POST /chat` → `ChatController::send()`: validates `message` (required, ≤20000 chars — long enough for pasted code). Appends the learner's message to the session array, then builds a single folded transcript string (all messages, `"{ROLE}:\n{content}"` blocks joined by blank lines — necessary because every LLM driver in this app takes one system + one user string, not a real multi-turn messages array). Calls `MissionReference::resolve($message)` (see §6.13) — if it finds any `M3`/`M3 D2`/`B1`-style references, a `REFERENCED MATERIAL` block is appended to the transcript before it's sent. The system prompt is `tutor-chat.md` rendered through `StudyMode::renderPrompt()`, concatenated with `ChatController::learnerState()`'s live-state block (current phase/name, ≤10 due concept names, personal instructions or `(none)`, ≤10 unresolved struggle notes sorted by weight — queried via `->whereHas('concept')` specifically to avoid leaking the other track's struggle notes, since `struggle_notes` has no `mode` column of its own). On failure, the learner's message is still saved to session (so it isn't lost) and the page reloads with an error flash; on success, the tutor's reply is appended and only the **last 30 entries** are kept in session going forward.

**"Save as mission"**: any tutor reply containing the literal substring `**Task` (i.e. it looks like it contains a drill) gets a "Save as mission" button in the UI, posting to `POST /chat/mission` → `ChatController::saveMission()` (validates `index`, looks the message up in the session array, rejects if it's not a `tutor`-role message) → `ExerciseService::fromChat()` — turning an ad-hoc chat drill into a real, gradeable `Exercise` the learner can submit against and which then runs the normal ladder/spacing loop.

`POST /chat/clear` → `ChatController::clear()`: forgets the session key entirely (per-track, so clearing one track's chat leaves the other's untouched).

### 6.10 Side Quest (`/side-quest`)

`GET /side-quest` → `SideQuestController::index()`: lists past side quests (`Exercise::sideQuests()`, paginated 10/page) plus the creation form.

`POST /side-quest` → `SideQuestController::store()`: validates `topic` (required, 5–500 chars — a free-text description of the confusion), optional `concept_id`, plus the shared difficulty/drills/bosses controls. Calls `ExerciseService::generateSideQuest()`:
1. Builds a `$concepts` collection: the explicit `conceptId` if given, plus any non-retired concept whose name is a substring of the (lower-cased) topic text, plus — if still empty — **creates a brand-new temporary `Concept`** under the current phase's topic, named from `Str::limit($topicPrompt, 80)`, with a synthetic summary, `next_review_at = tomorrow` — this is what integrates a totally novel confusion into the normal spacing ladder from day one.
2. Retrieves chunks, renders `side-quest-engine.md`, and calls `chat()` with a Side-Quest-specific request builder (structurally like the regular mission builder but includes the raw confusion description instead of a new/review split).
3. Stores the exercise with `type='side_quest'`, all concepts attached with `role='side_quest'`. **Does not** call the Obsidian exporter (unlike regular missions).

The generated document follows a **Micro-Ladder Progression** structure (`side-quest-engine.md` MODE 1): `## Diagnosis` (names the confusion, explains the underlying mechanism, previews the fix) → Drill 1 [LEVEL 1: FOUNDATION] → Drill 2 [LEVEL 2: ONE STEP DEEPER] → Drill 3 [LEVEL 3: MIXED STRUCTURES] → Drill 4 [LEVEL 4: EDGE CASES] (extra requested drills insert between levels 3 and 4) → optional Boss Fight → Submission format → Solutions. Multiple-choice questions are explicitly disallowed here (unlike regular missions, which permit up to 3 as minor reinforcement).

`ExerciseService::evaluate()` — the one method that actually grades any submitted exercise — picks its MODE 2 system prompt by `Exercise::type`, exactly mirroring generation's own type branch: `side-quest-engine.md` for `type === 'side_quest'`, `exercise-engine.md` for everything else. `side-quest-engine.md`'s own MODE 2 defines a more detailed three-state Boss Fight skeleton-approval machine than `exercise-engine.md`'s single-submission one (see §6.6), and it is genuinely live: `buildEvalRequest()` already ships the exercise's last 5 `evaluation_md` blobs as `Previous evaluations` regardless of type, which is exactly the state Side Quest's multi-attempt protocol needs to track its own "Skeleton approved" phase across resubmissions.

### 6.11 Exam Prep (`/exam-prep`)

`GET /exam-prep` → `ExamPrepController::index()`: shows upcoming exam alerts (`RoadmapService::getUpcomingExams()`, see below) and the 10 most recent `ExamPrepSession`s.

**Ingest a study guide** (`POST /exam-prep/ingest` → `ExamPrepController::ingest()` → `ExamPrepService::ingestStudyGuide()`): validates `target` and `report_text` (≥50 chars); creates an `ExamPrepSession` with `method='ingested_guide'` and no LLM call at all — purely a storage operation for later reference by a real session.

**Start a session** (`POST /exam-prep/start` → `ExamPrepController::startSession()` → `ExamPrepService::generateSession($target, $method, $studyGuideText)`): validates `method` ∈ `{active_recall, spaced_1357, test_maker, metacognitive}`. Attempts to fuzzy-match `$target` against a roadmap checkpoint (`RoadmapService::getCheckpoints()`); if matched, embeds that checkpoint's pre-baked `mcq_exam`/`short_answer_questions` data into the LLM request. Also includes up to the first 4000 characters of any provided study-guide text. Calls `exam-prep.md`'s MODE 1 (`MODE: GENERATE_EXAM`) via `chatJson()`, expecting `{exam_target, method, instructions, questions: [{id, question, options: {A,B,C,D}, distractor_analysis, correct_answer, explanation}]}`, stored whole into `ExamPrepSession::session_data`.

The four "methods" are framing/strategy labels described in the prompt, not separate code branches — `ExamPrepService` doesn't switch on `$method` beyond passing the string through:
- **Active Recall / Simulated Conditions**: timed MCQ/short-answer mock exam with post-submission explanations.
- **1-3-5-7 Rule**: spaced review sessions at 1/3/5/7-day intervals.
- **Test-Maker Strategy**: teaches distractor-option analysis (absolute-language traps like "always"/"never," plausible-sounding technical misnomers).
- **Metacognitive Preparation**: forces a written "think-aloud" reasoning journal per answer before the learner locks it in.

**Submit for evaluation** (`POST /exam-prep/session/{id}/submit` → `ExamPrepController::submitSession()` → `ExamPrepService::evaluateSession()`): sends `exam-prep.md`'s MODE 2 (`MODE: EVALUATE_EXAM`) with the full `session_data` and the learner's submission, expecting `{overall_score, feedback_md, metacognitive_evaluation, distractor_mastery_score, concept_breakdown: [{concept, status, recommendation}]}` via `chatJson()`; stores the results back onto the session row.

**Exam alerts** (`RoadmapService::getUpcomingExams()`, consumed by `NotificationService`): a progress-gated trigger — a "checkpoint" (one of 4 hardcoded phase-range groupings covering the full AI Security 21-phase roadmap) or "certification" (a hardcoded name→target-phase map) alert only fires once the learner's `highestActivePhase()` reaches or exceeds that checkpoint/cert's trigger phase — explicitly documented in the code as guaranteeing "zero premature exam triggers." Coverage percentage (ingested concepts / required topics across the range) is computed and shown alongside the alert.

### 6.12 Notifications (`/notifications`)

`NotificationService::checkAndGenerate()` (called as a side effect at the top of both `getUnread()` and `getAll()`) first purges any stale `checkpoint_near`/`cert_near` notification whose title no longer appears in the current `getUpcomingExams()` result (handles progress resets or corrections), then, for each currently-upcoming exam target, creates a new `Notification` only if one of the same `type`+`title` wasn't already created in the last 7 days (a dedup window preventing notification spam on every page load).

`GET /notifications` → `NotificationController::index()` — paginated (15/page) list. `POST /notifications/{id}/read` marks one read (and, if it carries a `link`, redirects straight there instead of back to the notifications page). `POST /notifications/read-all` / `POST /notifications/clear-all` do exactly what they say — the latter deletes every `Notification` row (implicitly scoped to the active track only, via the model's global scope).

### 6.13 Mission referencing (`MissionReference::resolve()`)

Used exclusively by `/chat` to let the learner refer to past mission content by number instead of pasting it. `resolve(string $message)` runs a single regex over the whole chat message:
```php
preg_match_all('/\bM(\d+)(?:[\s-]*([DB])(\d+))?\b/i', $message, $refs, PREG_SET_ORDER);
```
This one pattern captures `M3` (whole mission), `M3 D2` / `M3-D2` (drill 2 of mission 3), and `M3 B1` (boss fight 1 of mission 3) in a single pass. If no `M`-prefixed reference is found at all, it falls back to a bare pattern `/\b([DB])(\d+)\b/i` (matching plain `D2` or `B1`), interpreted as "in my most recent mission" — resolved against `Exercise::orderByDesc('date')->orderByDesc('id')->first()`.

For each match, `lookup($missionId, $type, $n)`:
- `$type === ''` (whole-mission reference like `M3`): returns the entire `mission_md`, prefixed with the id/date.
- Otherwise, `extractSection()` splits `mission_md` on `^## ` (H2 headings, multiline mode). For drills, it matches a section whose heading starts with `Drill {n}`. For boss fights, since Boss Fight headings carry no number of their own, it counts every section starting with `Boss Fight` and matches on the running ordinal (`$bossSeen === $n`) — this is how `B1`/`B2` address multiple Boss Fights in one mission by position.
- If a section is found, `matchingSolution()` also pulls out the matching `### Solution — {name}` block (matched by the same "evocative name" extracted from the section's own heading, via a regex isolating the text after an em-dash/hyphen and before any `[tag]` suffix) and appends it.

The resolved text block(s) are appended to the chat request as a `REFERENCED MATERIAL` section — the learner never has to paste mission text manually; typing "help me with M3 D2" is enough.

### 6.14 Settings (`/settings`)

All handled by inline closures directly in `routes/web.php` (no dedicated controller class):

- `GET /settings`: shows provider info (`config('ai.provider')` etc., read-only, edited via `.env` + `php artisan llm:test`), current `LearnerInstructions`/`LearnerName`.
- `POST /settings/mode`: validates `mode` ∈ `{ai_security, laravel_web}`; calls `StudyMode::set()`; if the newly-active track has zero topics, immediately runs `RoadmapService::syncTopics()` (auto-bootstraps a track's topics the very first time it's switched to).
- `POST /settings/profile`: saves the learner's display name (`LearnerName::set()`, ≤60 chars).
- `POST /settings/instructions`: saves free-text personal instructions (`LearnerInstructions::set()`, ≤4000 chars) — injected into every mission-generation request and the tutor chat's live-state block, and drives the review-concept-prioritization keyword matching described in §6.4.
- `POST /settings/resync`: re-runs `RoadmapService::syncTopics()` against whatever JSON currently sits in `storage/app/roadmap/` for the active track — it does **not** pull anything from the root-level master JSON files (see §9).
- `POST /settings/reset` (danger zone): requires the literal string `RESET` typed into a confirmation field; inside a transaction, wipes **only the active track's** attempts, `exercise_concept` pivot rows, exercises, struggle notes, chunks, concepts, and transcripts, clears that track's chat session, resets that track's roadmap `start_date` to today (written via the `File` facade — mocked in tests via `File::partialMock()` so the real JSON is never touched by the test suite). The other track, this track's `topics` rows, the learner name, and personal instructions all deliberately survive untouched — the latter two because they're shared learner-identity data, not track data.

### 6.15 Obsidian export

Config: `OBSIDIAN_EXPORT_ENABLED`, `OBSIDIAN_VAULT_PATH`, `OBSIDIAN_MISSIONS_FOLDER` in `.env` (read via `config/obsidian.php`). `ObsidianExportService::export(Exercise $exercise)` writes a Markdown note to `Storage::disk('obsidian')` at `"{missions_folder}/M{id} - {safe title}.md"`, with YAML frontmatter (`study_engine_id`, `reference: "M{id}"`, `title`, `status`, a `source` URL back into the running app, `tags: [study-engine, study-mission]`, and a YAML list of concept names) followed by a link back to the app and the raw `mission_md` content verbatim. Triggered automatically (best-effort, failures swallowed) after every regular mission generation and evaluation — but **not** after Side Quest generation. Can also be re-run in bulk on demand: `php artisan study:export-obsidian [--id=... --id=...]` — with no `--id` flags, re-exports every mission in the system; with one or more, only those. Prints one line per export (success or failure) and a final summary count; returns a non-zero exit code if any export failed.

---

## 7. Every route

All defined in `routes/web.php` — no route groups, no explicit middleware declarations in the file itself (global middleware like `RecordActivity` is registered at the framework level, applying app-wide). Only `history` carries an explicit route `->name()`.

| Method | URI | Handler | Purpose |
|---|---|---|---|
| GET | `/` | `DashboardController::index` | Home page |
| POST | `/exercise/generate` | `DashboardController::generate` | One-per-day mission generation |
| GET | `/exercise/{exercise}` | `ExerciseController::show` | Mission detail page |
| POST | `/exercise/{exercise}/submit` | `ExerciseController::submit` | Submit pasted work for grading |
| POST | `/exercise/{exercise}/reset` | `ExerciseController::reset` | Clear attempts, allow resubmission |
| POST | `/exercise/{exercise}/note` | `ExerciseController::note` | Attach learner's own struggle note |
| POST | `/exercise/{exercise}/add-more` | `ExerciseController::addMore` | Generate a follow-up mission on the same concepts |
| GET | `/chat` | `ChatController::show` | Tutor chat page |
| POST | `/chat` | `ChatController::send` | Send a chat message |
| POST | `/chat/clear` | `ChatController::clear` | Clear chat history (active track only) |
| POST | `/chat/mission` | `ChatController::saveMission` | Save a tutor reply as a real mission |
| GET | `/concepts` | `ConceptController::index` | Browse concepts (`?filter=all\|due\|struggling`) |
| POST | `/concepts/{concept}/retire` | `ConceptController::toggleRetire` | Retire/reactivate a concept |
| POST | `/notes/{note}/resolve` | `ConceptController::resolveNote` | Resolve a struggle note |
| GET | `/phases` | `PhaseController::index` | Roadmap timeline |
| GET | `/phases/{phase}` | `PhaseController::show` | Single phase + Mega Project flow |
| GET | `/phases/{phase}/research-prompt` | `PhaseController::researchPrompt` | JSON: Stage 1 external-research prompt text |
| POST | `/phases/{phase}/project` | `PhaseController::generateProject` | Stage 2 brief (no research, legacy/quick path) |
| POST | `/phases/{phase}/project-from-research` | `PhaseController::generateFromResearch` | Stage 2 brief (research-grounded) |
| POST | `/phases/{phase}/implementation-plan` | `PhaseController::generateImplementationPlan` | Stage 3 WBS implementation plan |
| POST | `/phases/{phase}/skeleton` | `PhaseController::generateSkeleton` | Stage 4 plain-English skeleton |
| GET | `/phases/{phase}/export/{document}/{format}` | `PhaseController::export` | Download brief/plan/skeleton as txt/md/doc/docx |
| POST | `/phases/{phase}/submit` | `PhaseController::submitProject` | Submit + evaluate the project |
| POST | `/phases/{phase}/complete` | `PhaseController::attemptComplete` | Manually mark the phase completed |
| GET | `/notifications` | `NotificationController::index` | Notifications list |
| POST | `/notifications/read-all` | `NotificationController::markAllRead` | Mark every notification read |
| POST | `/notifications/clear-all` | `NotificationController::clearAll` | Delete every notification |
| POST | `/notifications/{id}/read` | `NotificationController::markRead` | Mark one read (redirects to its `link` if set) |
| POST | `/notifications/{id}/delete` | `NotificationController::destroy` | Delete one notification |
| GET | `/exam-prep` | `ExamPrepController::index` | Exam prep hub |
| POST | `/exam-prep/ingest` | `ExamPrepController::ingest` | Ingest a study guide/report (no LLM call) |
| POST | `/exam-prep/start` | `ExamPrepController::startSession` | Generate an exam-prep session |
| GET | `/exam-prep/session/{id}` | `ExamPrepController::showSession` | Session detail page |
| POST | `/exam-prep/session/{id}/submit` | `ExamPrepController::submitSession` | Submit for evaluation |
| GET | `/side-quest` | `SideQuestController::index` | Side Quest hub |
| POST | `/side-quest` | `SideQuestController::store` | Launch a Side Quest |
| GET | `/history` (name: `history`) | `HistoryController::index` | Paginated past-missions list |
| DELETE | `/history/{exercise}` | `HistoryController::destroy` | Delete a mission |
| GET | `/settings` | closure | Settings page |
| POST | `/settings/mode` | closure | Switch active track |
| POST | `/settings/profile` | closure | Save learner name |
| POST | `/settings/reset` | closure | Danger-zone reset (active track only) |
| POST | `/settings/instructions` | closure | Save personal instructions |
| POST | `/settings/resync` | closure | Re-sync topics from `storage/app/roadmap/` |
| GET | `/ingest` | `IngestController::show` | Ingest form |
| POST | `/ingest/preview` | `IngestController::preview` | LLM concept extraction, shows editable checklist |
| POST | `/ingest/confirm` | `IngestController::confirm` | Save (optionally + generate drills) |
| GET | `/debug/topics` | closure | Raw JSON dump of all topics (Milestone-1-era verification route) |

`routes/console.php` contains only Laravel's default `inspire` Artisan stub — no custom console routes.

---

## 8. Every LLM prompt file

All five live prompt files sit in `resources/prompts/`, loaded via `file_get_contents()`/`File::get()` at the point of use and always passed through `StudyMode::renderPrompt()` before being sent as the LLM's system message (a no-op for files with no `{{TRACK_*}}` tokens).

### `exercise-engine.md`
- **Loaded by**: `ExerciseService::storeMission()` (MODE 1, mission generation for regular — non-side-quest — missions) and `ExerciseService::evaluate()` (MODE 2, evaluation for regular missions; Side Quest exercises are routed to `side-quest-engine.md`'s own MODE 2 instead — see §6.10).
- **MODE 1 — GENERATE MISSION**: markdown output, structure `# Mission — {date} — {title}` → `## Briefing` → one `## Drill N — {name} [NEW|REVIEW]` per requested drill (each with **Setup/Task/Output/Concepts**) → one `## Boss Fight — {name}` per requested boss fight (**Setup/The Mission/Output/Concepts/Protocol**) → `## Submission format` → `## Solutions` (`### Solution — {name}` per drill/boss). Rules: only use provided concepts/chunks; interleave new+review; drills demand active production (≤3 MCQs allowed, never a whole mission of them); concrete Setups with real values; drills isolate 1–2 concepts, Boss Fights integrate every concept; honor exact requested drill/boss counts (0 or more each, but not both 0); honor personal instructions and requested difficulty; frame at least one task adversarially; Solutions show the simplest approach first, then an idiomatic alternative.
- **Input**: date, phase/week, requested difficulty/drills/bosses, `[NEW]`/`[REVIEW, lapses=N]` concept list, unresolved struggle notes, retrieved chunks, personal instructions.
- **MODE 2 — EVALUATE SUBMISSION**: strict JSON only —
  ```json
  {
    "feedback_md": "markdown feedback per drill: what was correct, what was wrong and WHY, what excellent looks like",
    "concept_scores": [{"concept_id": 12, "score": 0, "evidence": "one line referencing the learner's work"}],
    "suggested_struggle_notes": [{"concept_id": 12, "note": "specific weakness", "weight": 1}]
  }
  ```
  Scoring anchors: 90+ correct with sound reasoning, 70–89 correct but shaky, 40–69 partial/core misunderstanding, <40 incorrect/not attempted.
- **Input**: original `mission_md`, pasted result, last 5 `evaluation_md` entries (oldest-first), concept list with roles.
- Contains the mission-internal Boss Fight skeleton protocol described in §6.6.

### `tutor-chat.md`
- **Loaded by**: `ChatController::send()` — the entire always-active `/chat` system prompt, no MODE sections.
- **Content**: base rules (simplest-approach-first, short variable names, hint-before-solution, Markdown output), a self-description of the app for meta-questions, the mission-reference syntax explanation, the "Boss Fight coaching" section (layer-by-layer build-out coaching, §6.6), guidance for generating ad-hoc practice drills directly in chat, and guidance on teaching the "write first, then ask AI" workflow.
- **Input appended by PHP**: `ChatController::learnerState()`'s live-state block (phase, ≤10 due concepts, personal instructions, ≤10 unresolved struggle notes).
- **Output**: plain Markdown prose, no JSON.

### `mega-project.md`
- **Loaded by**: `PhaseService`'s five generation/evaluation methods (§6.7), each prefixing its user prompt with a literal `MODE: <NAME>` line the system prompt keys off.
- **MODE 1 (GENERATE_MEGA_PROJECT)**: markdown brief, exactly 8 sections (Title/Phase/Target Role header, Executive Summary & Real-World Problem, Concepts Integrated, Technical Requirements & Deliverables, Architecture & Implementation Guidelines, Evaluation Rubric & Pass Criteria — Core Functionality 40% / `{{TRACK_QUALITY_DIMENSION}}` 30% / Test Suite & Validation 15% / Code Quality & README 15%, pass = 70/100).
- **MODE 2 (EVALUATE_MEGA_PROJECT)**: strict JSON —
  ```json
  {
    "feedback_md": "# Project Evaluation Summary\n\n...",
    "overall_score": 85,
    "passed": true,
    "concept_scores": [{"concept_name": "Concept Name", "score": 90, "feedback": "..."}],
    "security_insights": "Key architectural strengths or vulnerabilities found..."
  }
  ```
- **MODE 3 (GENERATE_IMPLEMENTATION_PLAN)**: markdown Milestone-Based WBS document (see full method description in §6.7). Required structure: `# Implementation Plan: [Title]` → optional `## Architecture Lock` → repeated `## Milestone N: [Name]` blocks (Snapshot, Quick Summary, numbered `- [ ]` subsections, FINAL ACCEPTANCE CRITERIA, COMPLETE ONLY WHEN, optional Blocker Handling Rule).
- **MODE 4 (GENERATE_PLAIN_ENGLISH_SKELETON)**: markdown, no real code — `# Implementation Skeleton: [Title]` → `## How This Fits Together` → one `## Skeleton for Milestone N` per plan milestone → `## Data & Control Flow Across Milestones` → `## Suggested File/Module Map`.
- **Tokens used**: `{{TRACK_ROLE}}`, `{{TRACK_EVALUATOR_TITLE}}`, `{{TRACK_PROBLEM_LABEL}}`, `{{TRACK_PROBLEM_EXAMPLES}}`, `{{TRACK_PROBLEM_RATIONALE}}`, `{{TRACK_CONSIDERATIONS}}`, `{{TRACK_QUALITY_DIMENSION}}`, `{{TRACK_ARCHITECT_TITLE}}`, `{{TRACK_FLAW_LABEL}}`.

### `exam-prep.md`
- **Loaded by**: `ExamPrepService::generateSession()` (MODE 1) and `::evaluateSession()` (MODE 2). Contains **no `{{TRACK_*}}` tokens** — track-agnostic wording by design.
- **MODE 1 (GENERATE_EXAM)**: strict JSON — `{exam_target, method, instructions, questions: [{id, question, options: {A,B,C,D}, distractor_analysis, correct_answer, explanation}]}`.
- **MODE 2 (EVALUATE_EXAM)**: strict JSON — `{overall_score, feedback_md, metacognitive_evaluation, distractor_mastery_score, concept_breakdown: [{concept, status, recommendation}]}`.
- Both modes are JSON-only — the only one of the five prompt files with no markdown-output mode at all.
- Documents four named methods as background framing (Simulated Conditions / 1-3-5-7 Rule / "Think Like a Test Maker" / Metacognitive Preparation) — see §6.11.

### `side-quest-engine.md`
- **Loaded by**: `ExerciseService::generateSideQuest()` (MODE 1) and `ExerciseService::evaluate()` (MODE 2, for `Exercise::type === 'side_quest'`).
- **MODE 1 (GENERATE SIDE QUEST)**: markdown, Micro-Ladder Progression structure (`## Diagnosis` → Drill 1 [FOUNDATION] → Drill 2 [ONE STEP DEEPER] → Drill 3 [MIXED STRUCTURES] → Drill 4 [EDGE CASES] → optional Boss Fight → Submission format → Solutions). No MCQs allowed (unlike `exercise-engine.md`, which permits up to 3).
- **MODE 2 (EVALUATE SUBMISSION)**: same JSON schema as `exercise-engine.md` MODE 2, but with a more explicit three-state Boss Fight skeleton machine (unsubmitted → approved → layer-by-layer) than `exercise-engine.md`'s simpler single-pass version. Live and reachable as of the evaluation-routing fix described in §6.10/§9.
- **Tokens used**: `{{TRACK_ROLE_WITH_ARTICLE}}`, `{{TRACK_ROADMAP_DESC}}`, `{{TRACK_ROLE}}`.

### `RUNTIME_SYSTEM_PROMPT.md` (root-level — reference copy, not live)
Byte-identical to `resources/prompts/exercise-engine.md` (re-synced as of this revision — see §9). Same MODE 1/2 headers, same `{{TRACK_*}}` tokens, same JSON schema shape, same current drill/boss-count rules (0 or more of each, but not both zero). **No code in `app/` loads this file by path** — `exercise-engine.md` is the one actually sent to the LLM; this root copy exists purely as an easy-to-find reference for a human (or another AI) reading the repo without digging into `resources/prompts/`. Because nothing enforces the sync automatically, treat it as documentation that can drift again — if `exercise-engine.md` changes, re-copy it here in the same session, the way `CLAUDE.md`'s "Runtime LLM prompt" note already prescribes.

---

## 9. Known gotchas / non-obvious decisions

- **`RUNTIME_SYSTEM_PROMPT.md` vs `resources/prompts/exercise-engine.md` — fixed, but re-syncs are manual.** These two had drifted apart (dual-track tokens vs hardcoded AI-Security text, old vs new drill/boss-count rules); they were re-synced byte-identical as of this revision. Nothing automates this — anyone editing mission-generation behavior should edit the live `resources/prompts/exercise-engine.md` and remember to `cp` it over `RUNTIME_SYSTEM_PROMPT.md` in the same session, or the two will silently drift apart again.
- **Side Quest evaluation cross-wire — fixed.** `side-quest-engine.md` defines its own MODE 2 (evaluation) section with a richer three-state Boss Fight approval machine. `ExerciseService::evaluate()` previously always used `exercise-engine.md`'s MODE 2 regardless of `Exercise::type`, so Side Quest submissions were graded by the regular mission's simpler evaluation rules instead of their own. As of this revision, `evaluate()` branches on `Exercise::type` exactly like generation already did, so Side Quest submissions now grade against `side-quest-engine.md`'s own multi-attempt Boss Fight protocol.
- **Root-vs-storage roadmap JSON sync is manual, not automatic.** The curriculum JSON that actually drives the app lives in `storage/app/roadmap/` and is what `RoadmapService::load()` reads (no caching, re-read every call). The root-level files (`AI_Engineering_Roadmap_v3.2_Karpathy_Updated.json`, `Laravelstudy.json`) are the hand-edited "master copies." **Nothing in code copies root → storage automatically.** "Resync roadmap" (`POST /settings/resync`) only re-runs `syncTopics()` against whatever is already sitting in `storage/app/roadmap/` — editing the root file and clicking Resync does nothing until you've manually copied the file into `storage/app/roadmap/` first. As of the last check, the two are also out of sync in version: root is `AI_Engineering_Roadmap_v3.2_Karpathy_Updated.json` but `storage/app/roadmap/` still has the older `AI_Engineering_Roadmap_v3_1.json`.
- **The root `Laravelstudy.json` and the storage copy use genuinely different JSON shapes.** The root file is a bare array of `{phase_number, title, description, week_start, week_end, topics: [{name, description}]}` — it's raw source content, not something you can drop straight into `storage/`. `RoadmapService` expects `{meta, phases: [{phase, title, weeks, notes}], checkpoints: [], certifications: []}`; the `notes` field is what `PhaseService::generateMegaProject()` actually reads for the brief prompt (the original `topics` array is worth preserving alongside `notes` for reference even though nothing currently parses it).
- **`RoadmapService::activeFilePath()` behaves differently per track.** For `laravel_web`, it hard-targets `storage/app/roadmap/Laravelstudy.json` (throwing if absent — a real setup error, not a silent fallback). For `ai_security`, it globs `storage/app/roadmap/*.json`, explicitly excluding any file literally named `Laravelstudy.json` so the two can coexist in the same folder, falling back to globbing the repo root only if that storage folder is empty.
- **Embedding provider fallback rules are asymmetric across providers.** `gemini` and `anthropic` have no embeddings API in this app and automatically fall back to `openrouter` for embeddings if `AI_EMBED_PROVIDER` is unset. `freellmapi` has **no such automatic fallback** — with `AI_EMBED_PROVIDER` unset, it would try to embed through FreeLLMAPI's own proxy (its driver hardcodes `text-embedding-3-small` as the model name regardless of any configured embed model). The shipped `.env` avoids this by setting `AI_EMBED_PROVIDER=openrouter` explicitly. **Never change the embedding provider/model once real chunks exist** — stored vectors are only comparable to new query vectors from the exact same embedding model, and nothing in the code enforces this; it's an operational discipline only.
- **`LlmService` has an additional, generic cross-provider fallback layer** (`config('ai.fallback')`, default `openrouter`) on top of each driver's own internal retry logic — any `chat()` failure from the primary provider triggers one retry attempt against the configured fallback provider (if a usable one is configured and it isn't the same as the primary), logged as a warning. This sits underneath OpenRouter's own 2-attempt retry and Gemini's own 3-attempt exponential-backoff retry. This is now also documented in `CLAUDE.md`'s "LLM provider" bullet.
- **Two different launcher scripts exist and point at different things.** `start-study-environment.ps1` (documented, mirrored from `E:\start-study-environment.ps1`) is the maintained one. `start-study.ps1` is a second, undocumented launcher that also opens Obsidian and points at a different FreeLLMAPI install path (`C:\Users\HP\tools\freellmapi` vs the documented script's `E:\Tools\freellmapi`). Don't confuse the two.
- **The "skeleton" naming collision** (fully described in §6.6/§6.7): the Boss Fight skeleton (learner-written, per-drill, "Skeleton approved" gated) and the Mega Project skeleton (LLM-generated, whole-project, Stage 4 of the portfolio flow) share nothing but the word.
- **`robocopy /XD` does not match multi-segment relative paths** — when creating the shareable template copy at `E:\Dev Projects\study-engine-template`, excluding `storage\app\private` by that relative path silently failed to exclude it, leaking `learner-name.txt`/`laravel.log` into the copy; they had to be cleaned up by hand afterward. Use absolute paths or a post-copy cleanup step when scripting copies of this repo.
- **Portable PHP's TLS cert path is a recurring failure point.** Every LLM call is an HTTPS request; if `curl.cainfo`/`openssl.cafile` in the portable PHP's `php.ini` ever stop pointing at a valid `cacert.pem`, every LLM call fails with cURL error 60. This is the first thing to check on a fresh machine or after a PHP reinstall.
- **`updates/manifest.json` tracks essentially everything not explicitly excluded.** `UpdatePackage`'s `EXCLUDED_DIRS` (`.git/`, `.omc/`, `.claude/`, `node_modules/`, `updates/`, `storage/`, `bootstrap/cache/`, `vendor/`) are pruned from traversal entirely (the directory is never even descended into). Additional single-file exclusions: `.env`, root `version.txt`, `.phpunit.result.cache`, anything named `database.sqlite*`, anything ending `.log`, and the root roadmap JSON files (matched by regex `^(AI_.*Roadmap.*|Laravelstudy)\.json$`). If a future session adds a new directory of generated/local content, it needs to be added to `EXCLUDED_DIRS` or it will get shipped to every distributed copy.
- **`welcome.blade.php` is unused Laravel starter-kit boilerplate.** It exists in `resources/views/` but nothing routes to it — `/` is the real dashboard (`DashboardController::index`). Safe to ignore or delete; not part of the app's actual UI.
- **CLAUDE.md's "mission browser dropdown" claim — corrected.** The dashboard shows only the single current/pending mission, not a `<select>` of all missions — `/history` is the actual page for browsing every past mission (paginated 20/page). `CLAUDE.md`'s "Transcript-driven drills" section has been corrected to say this.
- **`exercises.status` never appears to reach `'submitted'` in practice** — the schema defines `pending`/`submitted`/`evaluated`, but the evaluation flow (`ExerciseService::evaluate()`) sets status straight from `pending` to `evaluated`; no code path was found setting `'submitted'` as an intermediate state.
- **`concepts.ease` is unused.** The column exists (float, default 2.5, per the original spec's Anki-style design) but the actual spacing logic (`applyLadder()`) only ever reads/writes `interval_days`, never `ease` — the implemented algorithm is a fixed ladder, not an ease-factor-adjusted one.
- **Never run `migrate:fresh` (or any destructive migration/reset) without the user's current, explicit consent** — `database/database.sqlite` holds real accumulated study history. Multiple `.bak`/timestamped backup copies of the database already exist in the repo from past caution around this (`database-before-freellmapi-...sqlite`, `database.sqlite.bak-dualmode`), which itself signals how seriously this rule is treated.
- **`phpunit.xml` pins `AI_PROVIDER=openrouter` / `AI_EMBED_PROVIDER=openrouter`** so the test suite never accidentally calls a real API following whatever the developer's local `.env` happens to have configured — tests mock the actual HTTP calls (`GeminiDriverTest` etc. use Laravel's HTTP fake) rather than depending on this pin alone for safety, but the pin ensures a stray unmocked call still fails predictably rather than hitting a live paid/rate-limited endpoint.

---

## 10. How to extend this app safely

- **Reuse the CSS component classes** (`.btn`/`.btn-accent`/`.btn-outline`/`.card`/`.badge-*`/`.eyebrow`/`.label`/`.input`, all defined in `resources/css/app.css`'s `@layer components`) instead of hand-rolling Tailwind utility soup on new markup — the whole app is visually consistent because every page draws from this same small set.
- **Any new form that triggers an LLM call must be marked `<form data-llm>`** — the shared JS in `layouts/app.blade.php` automatically gives it the disable/spinner/"Working…" treatment; there is no need (and no established pattern) for a bespoke per-page loading state.
- **Any new markdown-rendering surface should reuse the `.mission` CSS class**, not invent new prose styling — and should NOT touch the `table`/`th`/`td`/`hr` rules inside it without checking they still render borders correctly against whatever Tailwind version is in use (see the preflight-collapse gotcha in §5.4/§9).
- **Any new mode-scoped model needs `use ScopedToMode;`.** That single trait handles both the automatic `WHERE mode = ?` filtering on every query and the auto-stamp-on-create — it needs zero changes at existing call sites. Only skip it for a table that has no `mode` column of its own and instead scopes transitively through a mode-scoped parent FK (the existing pattern for `attempts` and `struggle_notes`) — and if you do skip it, be deliberate about any query that reads that table directly without going through the parent relation, the way `ChatController::learnerState()`'s `->whereHas('concept')` deliberately avoids leaking struggle notes across tracks.
- **Any bulk/raw-query write** (`DB::table(...)->upsert()`, `DB::table(...)->delete()`, or similar bypassing Eloquent) against a mode-scoped table **must set `mode` explicitly** — global scopes and the `creating` event never fire for raw query-builder operations. `RoadmapService::syncTopics()` is the reference example.
- **Any new track-aware prompt wording should go through the `{{TRACK_*}}` token system**, not a hardcoded if/else on `StudyMode::current()` inside a controller/service. Add new tokens to the `strtr()` map in `StudyMode::renderPrompt()` (mirroring the existing 12-token pattern) rather than inventing a second templating mechanism.
- **Migrations should stay additive.** Every migration in this app's history only adds columns/tables or swaps a unique constraint — none has ever dropped or renamed an existing study-data column. Follow that pattern; the local SQLite file holds real, non-reproducible study history, so destructive schema changes need explicit user sign-off and a backup, not just a migration that happens to work.
- **At the end of any session that changed shippable app files, run `php artisan update:package "<one-line summary>"`.** This is a standing rule (not optional), because other people run offline copies of this app (a family member's install, template users) that need to be able to catch up via `updates/update-NNN-<date>/apply-update.ps1`. Use `--dry` first to preview the diff if unsure what changed. If the session touched the roadmap JSON structure or anything under `storage/`, call that out explicitly in the package notes — both are deliberately excluded from the shipped package and recipients need to know to handle them by hand.
- **Never assume `RUNTIME_SYSTEM_PROMPT.md` is live** — always check/edit `resources/prompts/exercise-engine.md` for mission-generation/evaluation prompt changes; the root file is stale.
- **Keep `Str::markdown()` calls consistent with the rest of the app** — evaluation/brief/plan/skeleton rendering elsewhere in the codebase uses `Str::markdown($text)` with html stripped; new document-rendering views should match that (see `DocumentExportService::toDocHtml()`'s `['html_input' => 'strip', 'allow_unsafe_links' => false]` options as the reference configuration).
- **Prefer first-party Laravel features and keep dependencies minimal** — this is a stated project convention (`CLAUDE.md`/`PROJECT_SPEC.md`), reflected concretely in choices like hand-rolling `.docx` export via PHP's built-in `ZipArchive` instead of pulling in a document-generation library, and doing cosine similarity in plain PHP instead of a vector-search extension.
