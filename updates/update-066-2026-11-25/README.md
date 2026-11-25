# Study Engine — Update 66

**What's new:** Project phase revamp: paste-in brief/plan/criteria workflow, score-parse eval, enhanced research prompt, passing_criteria_md migration

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
`backup-before-update-66` inside this folder, so the update can be undone.

## Files in this update (10)

- `.gitignore`
- `AI_Engineering_Roadmap_v3.2.pdf`
- `app/Http/Controllers/PhaseController.php`
- `app/Models/PhaseProgress.php`
- `app/Services/PhaseService.php`
- `database/migrations/2026_11_25_000001_add_passing_criteria_to_phase_progress.php`
- `public/build/assets/app-DD8_HELw.css`
- `public/build/manifest.json`
- `resources/views/phases/show.blade.php`
- `routes/web.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-CJwSU8kc.css`