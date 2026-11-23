# Study Engine — Update 50

**What's new:** Learner mode for projects: strict beginner step format (10 parts, fading support, glossary, END OF DOCUMENT), one milestone per click with auto-continue for cut-off replies, collapsible reference solutions

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
`backup-before-update-50` inside this folder, so the update can be undone.

## Files in this update (11)

- `app/Http/Controllers/PhaseController.php`
- `app/Services/PhaseService.php`
- `resources/css/app.css`
- `resources/prompts/mega-project.md`
- `resources/views/phases/partials/next-milestone.blade.php`
- `resources/views/phases/show.blade.php`
- `routes/web.php`
- `tests/Feature/PhaseLearnerModeTest.php`