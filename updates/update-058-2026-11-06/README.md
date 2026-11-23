# Study Engine — Update 58

**What's new:** Phase page: pick a support level (FULL / GUIDED / LIGHT / SPEC ONLY) per milestone for plan and skeleton; plans are now written at full detail with a Spec per section

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
`backup-before-update-58` inside this folder, so the update can be undone.

## Files in this update (6)

- `public/build/assets/app-n8ZhgzAN.css`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/prompts/mega-project.md`
- `resources/views/phases/show.blade.php`
- `tests/Feature/PhaseLearnerModeTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-B_JIwiMu.css`