# Study Engine — Update 64

**What's new:** Fix plans are now written in stages by FixPlanWriter (outline, one call per milestone, glossary) and checked in code: every finding must quote the brief, one section per finding, real Goal/Where steps with whys, no thinking aloud, nothing saved if the structure is missing. Prompts for audit and fix plan refined.

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
`backup-before-update-64` inside this folder, so the update can be undone.

## Files in this update (9)

- `CLAUDE.md`
- `app/Services/FixPlanWriter.php`
- `app/Services/PhaseService.php`
- `app/Support/StudyMode.php`
- `resources/prompts/mega-project.md`
- `resources/prompts/project-audit.md`
- `resources/prompts/project-fix-plan.md`
- `tests/Feature/ProjectAuditTest.php`
- `tests/Feature/ProjectResubmissionTest.php`