# Study Engine — Update 38

**What's new:** Fix Side Quest evaluation routing (now grades against side-quest-engine.md instead of exercise-engine.md), resync RUNTIME_SYSTEM_PROMPT.md, fix .mission table/hr CSS, document LlmService fallback layer and correct stale dashboard-dropdown claim in CLAUDE.md

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
`backup-before-update-38` inside this folder, so the update can be undone.

## Files in this update (7)

- `CLAUDE.md`
- `PROJECT_DEEP_DIVE.md`
- `RUNTIME_SYSTEM_PROMPT.md`
- `app/Services/ExerciseService.php`
- `public/build/assets/app-CDKjSH2w.css`
- `public/build/manifest.json`
- `resources/css/app.css`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-BpT6xnih.css`