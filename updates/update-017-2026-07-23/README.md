# Study Engine — Update 17

**What's new:** Add Side Quest system: targeted micro-ladder drills for specific weaknesses. New route /side-quest, sidebar link, dashboard shortcut, concept row buttons, dedicated system prompt, and ExerciseService::generateSideQuest method.

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
`backup-before-update-17` inside this folder, so the update can be undone.

## Files in this update (17)

- `.agents/AGENTS.md`
- `.agents/skills/blast-radius/SKILL.md`
- `app/Http/Controllers/SideQuestController.php`
- `app/Models/Exercise.php`
- `app/Services/ExerciseService.php`
- `database/migrations/2026_07_23_100000_add_type_to_exercises.php`
- `public/build/assets/app-Djz0kD3_.css`
- `public/build/manifest.json`
- `resources/prompts/side-quest-engine.md`
- `resources/views/concepts/index.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/side_quest/index.blade.php`
- `routes/web.php`
- `scripts/blast_radius.php`
- `test-results.xml`
- `tests/Feature/SideQuestTest.php`

Files removed by this update (the installer deletes them):
- `public/build/assets/app-DLav6cFM.css`
- `scripts/debug_phase.php`