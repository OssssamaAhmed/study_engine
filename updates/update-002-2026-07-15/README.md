# Study Engine — Update 2

**What's new:** Dashboard mission-control hero with Welcome greeting + roadmap progress track, learner name in Settings, Easy/Medium/Hard + drill-count pickers, reset-all-progress from Settings, refreshed UI, and this offline updater itself

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
`backup-before-update-2` inside this folder, so the update can be undone.

## Files in this update (15)

- `CLAUDE.md`
- `app/Console/Commands/UpdatePackage.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Support/LearnerName.php`
- `public/build/assets/app-DnzcCuir.css`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/views/dashboard.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/settings.blade.php`
- `routes/web.php`
- `scripts/apply-update.ps1`
- `tests/Feature/ExerciseSelectionTest.php`
- `tests/Feature/ProgressProfileResetTest.php`
- `tests/Feature/UpdatePackageTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-BVrpausG.css`