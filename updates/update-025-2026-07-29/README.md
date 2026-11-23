# Study Engine — Update 25

**What's new:** Chat falls back to a second provider when the primary is overloaded (Gemini 503); clearer Gemini overload error

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
`backup-before-update-25` inside this folder, so the update can be undone.

## Files in this update (32)

- `.gitignore`
- `RUNTIME_SYSTEM_PROMPT.md`
- `TEMP_DIR_DEBUG_HANDOFF.md`
- `app/Http/Controllers/ExerciseController.php`
- `app/Services/Llm/GeminiDriver.php`
- `app/Services/LlmService.php`
- `config/ai.php`
- `phpunit.xml`
- `resources/prompts/exercise-engine.md`
- `resources/views/exercise/show.blade.php`
- `routes/web.php`
- `tests/Feature/GeminiDriverTest.php`
- `tests/Unit/JsonParseTest.php`
- `vendor/_laravel_ide/_auth.php`
- `vendor/_laravel_ide/_facade.php`
- `vendor/_laravel_ide/_model_helpers.php`
- `vendor/_laravel_ide/_request.php`
- `vendor/_laravel_ide/discover-19804082b122017c46bdac205a3a1912.php`
- `vendor/_laravel_ide/discover-31b934f114eabc246b541addb8b458e3.php`
- `vendor/_laravel_ide/discover-43419e1773d5590e98dcbfc1b7903297.php`
- `vendor/_laravel_ide/discover-476fe65492650d7ce55de1f8f96024c5.php`
- `vendor/_laravel_ide/discover-4dd8aa2c89a79640c26a5af93ae899b1.php`
- `vendor/_laravel_ide/discover-5802e590a92e23606bb54d2909564178.php`
- `vendor/_laravel_ide/discover-5c3cbe5e47b16e6c5fd1967f09c399da.php`
- `vendor/_laravel_ide/discover-6d96201ad95cad12367117063c707ebf.php`
- `vendor/_laravel_ide/discover-7563160ca998bde672640aa1e68dfd08.php`
- `vendor/_laravel_ide/discover-80bc781b69500326630e3fef7a4fdfeb.php`
- `vendor/_laravel_ide/discover-bf37f1dc35944596ec71c910bc1ca84f.php`
- `vendor/_laravel_ide/discover-d7e0bf33adda1a48ff4c9bdab757e083.php`
- `vendor/_laravel_ide/discover-e4fb95a586b679e2bcd4cf4f0871799d.php`
- `vendor/_laravel_ide/discover-ec2e9975ebf5755f76a162b3dce99361.php`
- `vendor/_laravel_ide/discover-f5442aeac73a6a9021bd7ce113d6a606.php`