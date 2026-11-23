# Study Engine — Update 61

**What's new:** Phases: fix-plan prompt after grading, resubmissions know earlier attempts (new project_attempts column + migration — run php artisan migrate), audit/grader grade only brief+gates read literally, plans get a Brief Coverage table

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
`backup-before-update-61` inside this folder, so the update can be undone.

## Files in this update (12)

- `CLAUDE.md`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `database/migrations/2026_11_08_000001_add_project_attempts_to_phase_progress.php`
- `resources/prompts/mega-project.md`
- `resources/prompts/project-audit.md`
- `resources/prompts/project-fix-plan.md`
- `resources/views/phases/show.blade.php`
- `routes/web.php`
- `tests/Feature/ProjectAuditTest.php`
- `tests/Feature/ProjectResubmissionTest.php`