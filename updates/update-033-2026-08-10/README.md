# Study Engine — Update 33

**What's new:** Add dual-mode study tracks (AI Security + Laravel Web), switchable from a dashboard toggle: unified schema via a 'mode' column and global Eloquent scope (ScopedToMode trait), per-track roadmap files, per-track LLM prompts (token-substituted, not forked), per-track streaks and chat sessions, and a track-scoped danger-zone reset.

## How to install (5 minutes, your data is safe)

1. Copy this whole `update-…` folder to the computer that runs Study Engine (a USB stick is fine).
2. Close the app if it is running (press `Ctrl+C` in the `php artisan serve` window).
3. Open PowerShell inside this folder: hold **Shift**, right-click an empty spot, choose **"Open PowerShell window here"**.
4. Run:
   ```
   powershell -ExecutionPolicy Bypass -File .\apply-update.ps1
   ```
5. When asked, type the full path of the Study Engine folder (the one containing the `artisan` file) and press Enter.
6. Start the app again: `php artisan serve`.

Your data is **never touched**: missions, concepts, scores, chat, your name, personal instructions, API keys
and the database stay exactly as they are. Every replaced file is first backed up into
`backup-before-update-33` inside this folder, so the update can be undone.

## Important: one manual step for the new Laravel Web track

This update adds a second study track (Laravel Web Development) alongside the existing AI Security
one, switchable from a toggle on the dashboard. **The AI Security track needs no action — it works
exactly as before.**

The Laravel Web track needs one file that this updater deliberately does **not** copy for you (same
reason your personal AI roadmap JSON never gets overwritten by an update): `storage/app/roadmap/Laravelstudy.json`.
Until that file exists, switching to Laravel Web mode on the dashboard will show an error instead of a
roadmap.

A starter template is included right here in this update folder: `laravelstudy.example.json`. To use it:

1. Copy `laravelstudy.example.json` from this folder into `storage/app/roadmap/Laravelstudy.json` inside
   your Study Engine folder (rename it exactly to `Laravelstudy.json`).
2. Edit it to reflect your own Laravel/React learning plan (phase titles, week ranges, notes) — it's
   plain JSON, the same shape your AI roadmap file uses.
3. In the app, switch to Laravel Web mode on the dashboard once — it reads the file automatically.

If you never plan to use the Laravel Web track, you can skip all of this entirely.

## Files in this update (40)

- `.env.example`
- `CLAUDE.md`
- `README.md`
- `app/Console/Commands/UpdatePackage.php`
- `app/Http/Controllers/ChatController.php`
- `app/Http/Controllers/IngestController.php`
- `app/Http/Controllers/PhaseController.php`
- `app/Http/Middleware/RecordActivity.php`
- `app/Models/Chunk.php`
- `app/Models/Concept.php`
- `app/Models/Concerns/ScopedToMode.php`
- `app/Models/ExamPrepSession.php`
- `app/Models/Exercise.php`
- `app/Models/Notification.php`
- `app/Models/PhaseProgress.php`
- `app/Models/Topic.php`
- `app/Models/Transcript.php`
- `app/Services/ExamPrepService.php`
- `app/Services/ExerciseService.php`
- `app/Services/PhaseService.php`
- `app/Services/RoadmapService.php`
- `app/Support/ActivityLog.php`
- `app/Support/StudyMode.php`
- `database/migrations/2026_08_11_000001_add_mode_to_study_engine_tables.php`
- `resources/prompts/exam-prep.md`
- `resources/prompts/exercise-engine.md`
- `resources/prompts/mega-project.md`
- `resources/prompts/side-quest-engine.md`
- `resources/prompts/tutor-chat.md`
- `resources/views/dashboard.blade.php`
- `resources/views/exam_prep/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/phases/index.blade.php`
- `resources/views/phases/show.blade.php`
- `resources/views/settings.blade.php`
- `routes/web.php`
- `tests/Feature/ActivityStreakTest.php`
- `tests/Feature/ChatAndDifficultyTest.php`
- `tests/Feature/ChatMissionPaginationFeedbackTest.php`
- `tests/TestCase.php`