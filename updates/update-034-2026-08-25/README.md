# Study Engine — Update 34

**What's new:** Add Mega Portfolio research ingestion flow: 2-stage UI with Claude Pro research prompt generator (Stage 1) and research paste + brief generation (Stage 2); adds research_context column to phase_progress; fixes stale test assertion

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
`backup-before-update-34` inside this folder, so the update can be undone.

## Files in this update (14)

- `app/Http/Controllers/ExerciseController.php`
- `app/Http/Controllers/HistoryController.php`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `database/migrations/2026_08_25_000001_add_research_context_to_phase_progress.php`
- `public/build/assets/app-BpT6xnih.css`
- `public/build/manifest.json`
- `resources/views/dashboard.blade.php`
- `resources/views/exercise/show.blade.php`
- `resources/views/history.blade.php`
- `resources/views/phases/show.blade.php`
- `routes/web.php`
- `tests/Feature/PhaseCompletionTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-H4dNPvKR.css`