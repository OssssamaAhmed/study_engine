# Study Engine

A local, single-user Laravel app that manages a study journey toward becoming an **AI Security Engineer** using **Spaced Interleaved Retrieval Practice**. Paste in transcripts of what you learned; the app extracts concepts, schedules reviews on a 1/3/7/14/30-day ladder, and generates daily interleaved exercises ("Missions") by calling an LLM. Paste your solution back; the app evaluates it and updates the schedule.

Everything runs natively on Windows — no Docker, no WSL.

## Requirements (already installed on this machine)

| Tool | Where | Check it works |
|---|---|---|
| PHP 8.4 | `C:\Users\HP\tools\php` (portable, on user PATH) | `php -v` |
| Composer 2 | same folder (`composer.bat`) | `composer -V` |
| Node 22 | system install | `node -v` |

> Fresh machine? Download PHP (NTS x64 zip) from https://windows.php.net/download, extract, copy `php.ini-development` to `php.ini` and enable the `curl, fileinfo, mbstring, openssl, pdo_sqlite, sqlite3, zip` extensions plus `curl.cainfo`/`openssl.cafile` pointing at a `cacert.pem` from https://curl.se/ca/cacert.pem. Get Composer from https://getcomposer.org, Node from https://nodejs.org.

## First-time setup

Open a terminal in the project folder and run:

```powershell
composer install          # installs PHP packages into vendor/
npm install               # installs frontend build tools
npm run build             # compiles CSS/JS into public/build/
copy .env.example .env    # your private config (never committed to git)
php artisan key:generate  # sets the app encryption key in .env
php artisan migrate --seed  # creates the SQLite database and loads roadmap topics from the JSON
```

Then edit `.env` and set your LLM credentials (see next section).

## Everyday Running (One-Click Setup)

To start both **FreeLLMAPI** and **Study Engine** together in background windows and automatically open the app in your browser, run:

```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\start-study-environment.ps1"
```

Or run Study Engine manually:

```powershell
php artisan serve
```
Open http://127.0.0.1:8000 and leave the terminal window running (Ctrl+C stops it).

## LLM provider

One `.env` line switches providers: `AI_PROVIDER=freellmapi|gemini|openrouter|ollama|anthropic`.

**FreeLLMAPI (Recommended)** — Local multi-provider load balancer running at `http://127.0.0.1:3001/v1`. Rotates 4–5 free AI models (Google Gemini, Groq, GitHub Models, OpenRouter) seamlessly with automatic fallback. Set `AI_PROVIDER=freellmapi` and paste your unified key into `FREELLMAPI_API_KEY=`.

**Gemini (Google AI Studio)** — Direct connection. Set `GEMINI_API_KEY=` from https://aistudio.google.com and set `AI_PROVIDER=gemini` (model via `GEMINI_MODEL`, default `gemini-2.5-flash`). Chat only.

**OpenRouter** — Direct connection. Set `OPENROUTER_API_KEY=` to your key from https://openrouter.ai. Chat uses `nvidia/nemotron-3-ultra-550b-a55b:free` (free), embeddings use `openai/text-embedding-3-small` (~$0.02 per million tokens).

**Ollama (fully local)** — install from https://ollama.com/download/windows, then `ollama pull qwen2.5:7b` and `ollama pull nomic-embed-text`.

**Anthropic** — set `ANTHROPIC_API_KEY=`. Chat only.

> **CRITICAL RULE**: Chat-only providers (FreeLLMAPI, Gemini, Anthropic) automatically keep embeddings on OpenRouter (`AI_EMBED_PROVIDER=openrouter`). **Never change the embedding provider once you have stored study data**, as existing 1536-dimensional vectors are only comparable to new vectors from the exact same model.

Verify whatever provider you picked:

```powershell
php artisan llm:test      # smoke-tests chat + embeddings against the active provider
```

## Obsidian Vault Integration

Study Engine can automatically export every generated or graded mission directly into an Obsidian vault as Markdown notes for offline search, linking, and AI Copilot queries.

Configure in `.env`:
```env
OBSIDIAN_EXPORT_ENABLED=true
OBSIDIAN_VAULT_PATH=E:/Dev_Projects/AI-Engineering-Vault
OBSIDIAN_MISSIONS_FOLDER="Study Engine/Missions"
```

## Pages at a glance

| URL | What it does |
|---|---|
| `/` | Dashboard — stats, roadmap progress bar, today's Mission card |
| `/ingest` | Paste a transcript → AI extracts concepts → confirm & save (optionally generate Mission immediately) |
| `/exercise/{id}` | Read the Mission, paste your result, submit for LLM evaluation |
| `/chat` | AI tutor chat — ask anything, get ad-hoc drills, save a reply as a real Mission |
| `/side-quest` | Targeted micro-drills for a specific confusion — describe your weakness, get a focused drill sequence |
| `/concepts` | Browse all concepts; filter by due/struggling; retire or resolve struggle notes |
| `/phases` | Full roadmap timeline — all phases, completion status, topic coverage |
| `/phases/{n}` | Single phase detail — AI Security Mega Portfolio Project brief, submit & evaluate |
| `/exam-prep` | Exam prep engine — ingest a study guide, run an active-recall / test-maker / spaced session |
| `/history` | Every past Mission and score, paginated |
| `/settings` | LLM provider info, personal instructions, resync roadmap, danger zone (reset) |
| `/notifications` | Exam alerts and system notifications |

## Daily workflow

1. **Ingest** — paste today's transcript/notes at `/ingest`, pick a topic (or let the AI infer it), review the extracted concepts, save. New concepts are scheduled for review tomorrow.
2. **Dashboard** — hit *Generate today's Mission*. Composition: ~60% recent material, ~40% due reviews (worst-lapsed first). One Mission per day from the dashboard button; the ingest flow can generate additional missions.
3. **Customise the Mission** — before generating you can pick:
   - *Difficulty*: Easy / Medium / Hard
   - *Drills*: 5–20 (default 5)
   - *Boss Fights*: 1–10 (default 1) — integrative real-world build challenges that combine all concepts
4. **Exercise page** — do the work, paste your result, submit. The LLM scores each concept 0–100: ≥70 promotes it up the review ladder (1→3→7→14→30 days); below 70 is a lapse (interval halved, struggle note created). Add your own "this was hard" note if needed.
5. **Side Quest** — hit `/side-quest` any time to describe a specific confusion and get a focused micro-drill sequence (3–20 drills, 0–10 boss fights).
6. **Phases & Portfolio** — check `/phases` to see your roadmap progress. Each phase has an AI Security Mega Portfolio Project: generate the brief, submit your work, get scored (≥70 passes).
7. **Exam Prep** — use `/exam-prep` when an exam is coming. Ingest a study guide or paste a report, then run an active-recall, spaced, test-maker, or metacognitive session.
8. **Concepts** — filter by due/struggling, retire concepts you've outgrown, resolve struggle notes.
9. **History** — every past Mission and score.
10. **Settings** — provider info, personal instructions (injected into every Mission), and the *Resync roadmap* button (run it after editing the roadmap JSON).

## The roadmap is the source of truth

The JSON file in `storage/app/roadmap/` defines the phases, week ranges, and the current phase shown on the dashboard (calculated from its `start_date`). Edit the file, click *Resync roadmap* in Settings, done — no code changes needed.

The root-level `AI_Engineering_Roadmap_v3.1.json` is the master copy you edit; Resync copies it into `storage/app/roadmap/`.

## Offline updates (for distributed copies)

The app ships an update system for offline copies (sister's install, etc.):

```powershell
# After changing app files — cuts an update package for other copies:
php artisan update:package "summary of what changed"

# Apply an update package (run from inside the update-NNN folder):
powershell -ExecutionPolicy Bypass -File .\apply-update.ps1
```

Update packages live in `updates/update-NNN-<date>/`. Apply them **in order** — the installer enforces this. Your data (database, `.env`, API keys) is never touched; replaced files are backed up first.

## Tests

```powershell
php artisan test          # runs the whole suite against an in-memory database
```

## Architecture notes

See `CLAUDE.md` for the full architecture summary (data model, spacing algorithm, LLM driver design, all decisions and their reasons).
