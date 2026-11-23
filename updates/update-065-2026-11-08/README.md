# Study Engine — Update 65

**What's new:** Phase progression: ONE graded Mega Project attempt (any score) now unlocks the next phase. New 'moved_on' phase status (no migration needed); the old project stays open for more attempts and turns Completed when it reaches 70. Dashboard shows a 'Projects still open' card; phases list shows a Moved on badge; the Mark Phase Completed button becomes 'Move on to Phase N' when the project is below 70. No storage/ or roadmap changes.

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
`backup-before-update-65` inside this folder, so the update can be undone.

## Files in this update (13)

- `CLAUDE.md`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `app/Services/RoadmapService.php`
- `public/build/assets/app-CJwSU8kc.css`
- `public/build/manifest.json`
- `resources/views/dashboard.blade.php`
- `resources/views/phases/index.blade.php`
- `resources/views/phases/show.blade.php`
- `tests/Feature/PhaseCompletionTest.php`
- `tests/Feature/PhaseMoveOnTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-CnAHpRyi.css`