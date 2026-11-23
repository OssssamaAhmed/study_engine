# Study Engine — Update 6

**What's new:** Tutor message limit raised to 20000 chars; Concepts and History pages paginated (20/page); tutor chat drills can be saved as real scored missions (Save as mission button); concepts named in personal instructions are pulled into upcoming missions first

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
`backup-before-update-6` inside this folder, so the update can be undone.

## Files in this update (12)

- `CLAUDE.md`
- `app/Http/Controllers/ChatController.php`
- `app/Http/Controllers/ConceptController.php`
- `app/Services/ExerciseService.php`
- `resources/prompts/tutor-chat.md`
- `resources/views/chat.blade.php`
- `resources/views/concepts/index.blade.php`
- `resources/views/history.blade.php`
- `resources/views/settings.blade.php`
- `routes/web.php`
- `tests/Feature/ChatMissionPaginationFeedbackTest.php`
- `tests/TestCase.php`