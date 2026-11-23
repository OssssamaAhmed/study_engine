# Study Engine — Update 8

**What's new:** Streak now counts any day you use the app (any page visit), not just mission generation; first run credits past days from stored missions, submissions and ingests; full reset clears it

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
`backup-before-update-8` inside this folder, so the update can be undone.

## Files in this update (7)

- `CLAUDE.md`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Middleware/RecordActivity.php`
- `app/Support/ActivityLog.php`
- `bootstrap/app.php`
- `routes/web.php`
- `tests/Feature/ActivityStreakTest.php`