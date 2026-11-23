# Study Engine — Update 4

**What's new:** Mission referencing: every mission has a reference number (M3); in Tutor chat say M3, M3 D2 or B1 and the tutor fetches that mission/drill/boss fight itself - no more pasting long text

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
`backup-before-update-4` inside this folder, so the update can be undone.

## Files in this update (11)

- `CLAUDE.md`
- `RUNTIME_SYSTEM_PROMPT.md`
- `app/Http/Controllers/ChatController.php`
- `app/Services/MissionReference.php`
- `resources/prompts/exercise-engine.md`
- `resources/prompts/tutor-chat.md`
- `resources/views/chat.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/exercise/show.blade.php`
- `resources/views/history.blade.php`
- `tests/Feature/MissionReferenceTest.php`