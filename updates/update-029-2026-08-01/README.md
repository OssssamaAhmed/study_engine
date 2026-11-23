# Study Engine — Update 29

**What's new:** Allow 0 drills and 0 boss fights (but not both); loosen validation min:0 across Dashboard, Ingest, SideQuest controllers and views; update exercise-engine and side-quest-engine prompts accordingly; add guard in ExerciseService throwing if both are 0

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
`backup-before-update-29` inside this folder, so the update can be undone.

## Files in this update (9)

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/IngestController.php`
- `app/Http/Controllers/SideQuestController.php`
- `app/Services/ExerciseService.php`
- `resources/prompts/exercise-engine.md`
- `resources/prompts/side-quest-engine.md`
- `resources/views/partials/generation-options.blade.php`
- `resources/views/side_quest/index.blade.php`
- `tests/Feature/ChatAndDifficultyTest.php`