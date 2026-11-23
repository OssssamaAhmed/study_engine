# Study Engine — Update 16

**What's new:** Fix active phase detection: currentPhaseTopic() now progress-gated (lowest non-completed phase), not calendar-week-based; dashboard progress bar and stats use completed phase_progress rows instead of elapsed days

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
`backup-before-update-16` inside this folder, so the update can be undone.

## Files in this update (4)

- `app/Http/Controllers/DashboardController.php`
- `app/Services/RoadmapService.php`
- `resources/views/dashboard.blade.php`
- `scripts/debug_phase.php`