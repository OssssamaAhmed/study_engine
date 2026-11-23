# Study Engine — Update 56

**What's new:** Learner mode for projects: every Goal line and skeleton step carries a small teal 'why' line (*→ ...*)

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
`backup-before-update-56` inside this folder, so the update can be undone.

## Files in this update (5)

- `public/build/assets/app-B_JIwiMu.css`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/prompts/mega-project.md`
- `resources/views/phases/show.blade.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-es6Gk_6h.css`