# Study Engine — Update 63

**What's new:** Phase page: one document dropdown (Brief, Implementation Plan, Attempt 2, 3, 4...) instead of tab buttons; evaluation summary moved under the Phase Notes block; one saved plan per attempt (migration moves the existing plan, run php artisan migrate); Plain-English Skeleton removed

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
`backup-before-update-63` inside this folder, so the update can be undone.

## Files in this update (14)

- `CLAUDE.md`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `database/migrations/2026_11_09_000001_store_fix_plans_per_attempt.php`
- `public/build/assets/app-CnAHpRyi.css`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/prompts/mega-project.md`
- `resources/prompts/project-fix-plan.md`
- `resources/views/phases/show.blade.php`
- `routes/web.php`
- `tests/Feature/PhaseLearnerModeTest.php`
- `tests/Feature/ProjectResubmissionTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-n8ZhgzAN.css`