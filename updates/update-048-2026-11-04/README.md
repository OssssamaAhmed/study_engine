# Study Engine — Update 48

**What's new:** Learner mode for portfolio projects: checkbox on Implementation Plan and Skeleton (shares the drills setting); mega-project.md gains a LEARNER MODE section (small steps, file+tool+meaning, exact PowerShell commands, Check it works / Try it / Common mistake)

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
`backup-before-update-48` inside this folder, so the update can be undone.

## Files in this update (8)

- `app/Http/Controllers/PhaseController.php`
- `app/Services/PhaseService.php`
- `resources/prompts/mega-project.md`
- `resources/views/phases/show.blade.php`
- `tests/Feature/PhaseLearnerModeTest.php`