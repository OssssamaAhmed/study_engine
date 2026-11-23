# Study Engine — Update 62

**What's new:** Phases: the app now writes the implementation plan for the next attempt (Fix Plan tab, one per attempt, same plan style, support-level buttons); replaces the copy-paste fix-plan prompt. Adds project_attempts + fix_plan columns — run php artisan migrate

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
`backup-before-update-62` inside this folder, so the update can be undone.

## Files in this update (11)

- `CLAUDE.md`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `database/migrations/2026_11_08_000002_add_fix_plan_to_phase_progress.php`
- `resources/prompts/mega-project.md`
- `resources/prompts/project-fix-plan.md`
- `resources/views/phases/show.blade.php`
- `routes/web.php`
- `tests/Feature/PhaseLearnerModeTest.php`
- `tests/Feature/ProjectResubmissionTest.php`