# PHP Temp Directory Issue — Handoff for Claude Code

## The Problem

When submitting an exercise at `POST /exercise/{id}/submit`, the browser shows:

```
Warning: PHP Request Startup: Unable to create temporary file. Check permissions in temporary files directory. in Unknown on line 0
Warning: PHP Request Startup: POST data can't be buffered; all data discarded in Unknown on line 0
```

These warnings are printed **before** Laravel can send HTTP headers, causing a cascading failure:
1. PHP can't create a temp file -> POST body is discarded
2. Warning text is output to the response body
3. Laravel tries to send headers (302 redirect or 500) -> "Cannot modify header information - headers already sent"
4. Fatal error

## Environment

- **OS:** Windows 11
- **PHP:** 8.4 portable at `C:\Users\HP\tools\php\php.exe`
- **php.ini:** `C:\Users\HP\tools\php\php.ini`
- **Project:** `E:\Dev_Projects\study_engine` (Laravel 13, SQLite)
- **Server:** `php artisan serve` (PHP built-in server)
- **Route:** `Route::post('/exercise/{exercise}/submit', [ExerciseController::class, 'submit'])` in `routes/web.php` line 20

## What Has Been Tried (all failed to fix the browser error)

### 1. Set sys_temp_dir and upload_tmp_dir in php.ini
Added to the end of `C:\Users\HP\tools\php\php.ini`:
```ini
sys_temp_dir = "E:\Dev_Projects\study_engine\storage\tmp"
upload_tmp_dir = "E:\Dev_Projects\study_engine\storage\tmp"
```
- Created `E:\Dev_Projects\study_engine\storage\tmp` directory
- **Verification via CLI works perfectly:**
  - `php -r "echo ini_get('upload_tmp_dir');"` shows the correct path
  - `tempnam()` succeeds from CLI
  - `is_writable()` returns true
- **But the error persists when using `php artisan serve`**

### 2. Previously tried C:\Users\HP\AppData\Local\Temp
Same result. CLI verification works, built-in server still fails.

### 3. Verified NOT the cause
- **Disk space:** C: has 17GB free, E: has 143GB free
- **open_basedir:** Not set in php.ini (no restriction)
- **.user.ini:** Does not exist in project root or public/
- **Directory existence:** Both temp dirs exist and are writable
- **php.ini loading:** Confirmed php_ini_loaded_file() returns C:\Users\HP\tools\php\php.ini

## Key Observation

The error happens at **PHP Request Startup** level, before any PHP script code runs. This suggests the PHP built-in web server process itself cannot write to the temp directory, even though the CLI process (same binary, same php.ini) can.

## Possible Angles Not Yet Explored

1. **PHP built-in server ignoring php.ini** - Maybe `php -S` does not load ini the same way. Try passing `-c` flag explicitly: `php -S 127.0.0.1:8000 -c C:\Users\HP\tools\php\php.ini`
2. **Windows ACL / inherited permissions** - The built-in server child process may have different effective permissions
3. **post_max_size or memory issue** - Currently post_max_size = 8M which should be fine, but worth checking the actual POST payload size
4. **Set TMP/TEMP environment variables** before running artisan serve
5. **Try a different temp path** - e.g. C:\temp or create one at C:\Users\HP\tools\php\tmp
6. **Check if antivirus/Windows Defender** is blocking temp file creation by the PHP server process
7. **Route-level investigation** - Look at ExerciseController::submit() to see if the issue is actually in the controller code and the temp warnings are a red herring being promoted to errors

## Current State of Modified Files

- `C:\Users\HP\tools\php\php.ini` - lines 1850-1852 have the temp dir settings (can be reverted if a different fix is found)
- `E:\Dev_Projects\study_engine\.gitignore` - /storage/tmp added
- `E:\Dev_Projects\study_engine\storage\tmp\` - created as an alternative temp directory
