# Study Engine — Update 49

**What's new:** Phase page: Brief / Implementation Plan / Skeleton shown as 3 tabs, one at a time (remembers last tab; generate opens its tab)

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
`backup-before-update-49` inside this folder, so the update can be undone.

## Files in this update (3)

- `app/Http/Controllers/PhaseController.php`
- `resources/views/phases/show.blade.php`
- `tests/Feature/PhaseLearnerModeTest.php`