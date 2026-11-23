# Study Engine — Update 40

**What's new:** Exercise page: Copy button under every drill and Boss Fight, inline answer box per drill with AI quick-check score, live drill total; full evaluation includes inline drill answers

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
`backup-before-update-40` inside this folder, so the update can be undone.

## Files in this update (15)

- `CLAUDE.md`
- `app/Http/Controllers/ExerciseController.php`
- `app/Models/DrillAttempt.php`
- `app/Models/Exercise.php`
- `app/Services/ExerciseService.php`
- `app/Services/MissionReference.php`
- `database/migrations/2026_10_20_000001_create_drill_attempts_table.php`
- `public/build/assets/app-CT2hpAfg.css`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/prompts/drill-check.md`
- `resources/views/exercise/partials/drill-tools.blade.php`
- `resources/views/exercise/show.blade.php`
- `routes/web.php`
- `tests/Feature/InlineDrillTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-CDKjSH2w.css`