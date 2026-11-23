# PROJECT SPEC — Study Engine (Laravel)

You are Claude Code acting as a senior Laravel architect and builder. Your job in this session is to BUILD the application described below. You are not the tutor — the tutoring intelligence lives inside the app as a runtime feature that calls an LLM with the system prompt stored at `resources/prompts/exercise-engine.md`. Do not conflate the two roles.

## 1. Purpose

A local, single-user Laravel web app that manages my study journey toward becoming an AI Security Engineer using Spaced Interleaved Retrieval Practice. I paste in transcripts/notes of what I learned; the app extracts concepts, schedules reviews, and generates one daily interleaved exercise ("Mission") by calling an LLM. I paste my solution back; the app evaluates it and updates the schedule.

## 2. Environment (do not assume anything else)

- OS: Windows 11, no Docker, no WSL. Everything must run natively.
- I am a Laravel beginner. For EVERY CLI command or file edit you ask me to run manually, explain HOW to run it and WHY it is needed, in one or two plain sentences.
- Check what is installed before assuming: PHP, Composer, Node. If missing, give me the exact download link and install steps for Windows.
- Laravel: latest stable version.
- Main database: SQLite (single file, zero setup).
- Vector store: SQLite with the `sqlite-vec` extension. If loading `sqlite-vec` on Windows PHP proves impractical, fall back to storing embeddings as JSON columns and computing cosine similarity in PHP — at my data scale (hundreds of chunks) this is acceptable. State which path you chose and why.
- LLM provider: Ollama running locally (default), with an optional switch to the Anthropic API.
  - `.env` keys: `AI_PROVIDER=ollama|anthropic`, `OLLAMA_BASE_URL=http://localhost:11434`, `OLLAMA_CHAT_MODEL=qwen2.5:7b`, `OLLAMA_EMBED_MODEL=nomic-embed-text`, `ANTHROPIC_API_KEY=`, `ANTHROPIC_MODEL=claude-haiku-4-5`.
  - Build a single `LlmService` interface with two drivers so switching providers is one .env change.
  - Never hardcode keys. Ship a `.env.example`. Explain to me how `.env` works the first time it comes up.
  - Walk me through installing Ollama for Windows and pulling the two models before Milestone 2 testing.

## 3. Source-of-truth data

Three files will be placed in `storage/app/roadmap/`:
- `AI_Engineering_Roadmap_v3_1.json` — the curriculum truth. Parse it to know phases, weeks, and the final goal. The app must re-read it on demand (a "Resync roadmap" button), so my edits to the file take effect without code changes.
- The roadmap PDF and brain-dump md are reference only; do not parse them at runtime.

Never deviate from the JSON as curriculum truth.

## 4. Data model (migrations required)

- `topics` — id, phase_number, name, week_start, week_end (seeded from the roadmap JSON)
- `transcripts` — id, topic_id (nullable), raw_text, source_note, created_at
- `concepts` — id, topic_id, name, summary, first_seen_at, ease (float, default 2.5), interval_days (int, default 0), next_review_at (date), lapses (int, default 0), retired (bool)
- `chunks` — id, transcript_id, concept_id (nullable), text, embedding (vec or JSON)
- `exercises` — id, date, mission_md (the full generated exercise), status (pending|submitted|evaluated)
- `exercise_concept` — pivot: exercise_id, concept_id, role (new|review)
- `attempts` — id, exercise_id, pasted_result, evaluation_md, score (0–100), created_at
- `struggle_notes` — id, concept_id, note, weight (1–3), resolved (bool), created_at

## 5. Core logic

### Ingestion
1. I paste a transcript into the UI and tag the topic (or let the LLM infer it from the roadmap phases).
2. App chunks the text (~500 tokens, overlap ~50), embeds each chunk, stores them.
3. LLM extracts a list of discrete concepts (name + 1-line summary) from the transcript; new concepts are created with `next_review_at = tomorrow`; concepts that already exist are linked, not duplicated.

### Spacing algorithm (implement exactly)
- Review ladder: intervals of 1, 3, 7, 14, 30 days. On a successful review (attempt score ≥ 70 for that concept), promote to the next rung. Score < 70 counts as a lapse: increment `lapses`, halve the current interval (minimum 1 day), and create/boost a struggle_note.
- A concept is "due" when `next_review_at <= today`.
- Manual override: I can mark a struggle_note resolved or a concept retired from the UI.

### Daily exercise generation
- One Mission per day, generated on demand from the dashboard.
- Composition rule: ~60% of the exercise targets concepts from the most recent transcript(s), ~40% targets due-review concepts, prioritised by (lapses desc, next_review_at asc). If nothing is due, 100% new material. If I learned a lot today (many new concepts), scale the exercise up accordingly rather than dropping concepts.
- Retrieval: pull the relevant chunks for every selected concept and pass them as context to the LLM together with the runtime system prompt at `resources/prompts/exercise-engine.md`, the concept list (marked new vs review), my target role (AI Security Engineer), and current roadmap phase.
- Store the returned Mission as an exercise, linked to its concepts via the pivot.

### Evaluation
- I paste my result into the exercise page. App sends: runtime system prompt + original Mission + my pasted result + the concept list.
- LLM returns structured JSON: overall feedback (markdown), per-concept scores (0–100), and suggested struggle notes. Parse it, store the attempt, update each concept's ladder per the spacing algorithm, create struggle notes where suggested.
- After evaluation I can add my own struggle note in free text ("this was hard for me") — the app attaches it to the concepts of that exercise with weight 2.

## 6. UI (Blade + Tailwind via Vite, keep it simple, no SPA framework)

Pages:
1. **Dashboard** — today's Mission (or "Generate today's Mission" button), due-review count, current phase/week from roadmap, streak counter.
2. **Ingest** — paste transcript, pick topic, submit; show extracted concepts for confirmation before saving.
3. **Exercise** — Mission rendered from markdown, textarea to paste result, submit for evaluation, evaluation shown below with per-concept scores.
4. **Concepts** — table with ease, interval, next review, lapses; filter by due/struggling; retire toggle.
5. **History** — past exercises and attempts.
6. **Settings** — provider switch display, resync roadmap button.

## 7. Build milestones (build and verify one at a time, in order; stop after each and have me confirm it works before continuing)

1. Scaffold Laravel + SQLite + migrations + roadmap JSON parser + seeded topics. Verify: `php artisan migrate` clean, topics visible in a debug route.
2. Ollama integration: LlmService with both drivers, embedding + chat smoke test via an artisan command (`php artisan llm:test`).
3. Ingestion pipeline: paste transcript → chunks + embeddings + concept extraction with confirmation UI.
4. Exercise engine: selection algorithm + retrieval + generation + storage; dashboard shows Mission.
5. Evaluation loop: paste result → structured evaluation → ladder updates + struggle notes.
6. Remaining UI pages, polish, README with full run instructions.

## 8. Non-negotiables

- Windows-native paths and commands only.
- No API keys in code, ever.
- Copy `resources/prompts/exercise-engine.md` from the file I provide (`RUNTIME_SYSTEM_PROMPT.md` in the project root) — do not write your own version.
- Write a `CLAUDE.md` at project root after Milestone 1 summarising the architecture and conventions so future sessions have context.
- Keep dependencies minimal; prefer first-party Laravel features.
