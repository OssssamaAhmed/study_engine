# ============================================================
#  Study Engine - Apply All Pending Updates (Batch Updater)
#  ============================================================
#  HOW TO USE (one time setup, then every time you get new folders):
#
#  1. Copy ALL new update-NNN-YYYY-MM-DD folders into the "updates" folder
#     inside Memona's Study Engine app.
#
#  2. Open PowerShell and run:
#       powershell -ExecutionPolicy Bypass -File "C:\path\to\study-engine\updates\apply-all-updates.ps1"
#
#     Or right-click this file and choose "Run with PowerShell".
#
#  That's it. It will automatically find and apply every update
#  in the correct order. It skips updates already applied.
# ============================================================

$ErrorActionPreference = 'Stop'

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Study Engine Batch Updater" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Determine the app root: this script lives inside the 'updates' folder,
# so the app root is one level up.
$updatesDir = $PSScriptRoot
$AppPath    = Split-Path $updatesDir -Parent

# Safety check - make sure we found the right folder
if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
    Write-Host "ERROR: Could not find Study Engine app at: $AppPath" -ForegroundColor Red
    Write-Host "       Make sure this script is inside the 'updates' folder of the app." -ForegroundColor Red
    Write-Host ""
    Read-Host "Press Enter to close"
    exit 1
}

Write-Host "App folder  : $AppPath" -ForegroundColor Gray
Write-Host "Updates in  : $updatesDir" -ForegroundColor Gray
Write-Host ""

# Read current app version
$verFile    = Join-Path $AppPath 'version.txt'
$appVersion = 1
if (Test-Path $verFile) {
    $appVersion = [int](Get-Content $verFile | Select-Object -First 1)
}
Write-Host "Current version: $appVersion" -ForegroundColor Yellow
Write-Host ""

# Find all update-NNN-* folders, sorted by the NNN number
$updateFolders = Get-ChildItem -Path $updatesDir -Directory |
    Where-Object { $_.Name -match '^update-(\d+)-' } |
    Sort-Object { [int]($_.Name -replace '^update-(\d+)-.*', '$1') }

if ($updateFolders.Count -eq 0) {
    Write-Host "No update folders found in: $updatesDir" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to close"
    exit 0
}

# Count how many are actually pending (version > appVersion)
$pending = $updateFolders | Where-Object {
    $vf = Join-Path $_.FullName 'version.txt'
    (Test-Path $vf) -and ([int](Get-Content $vf | Select-Object -First 1) -gt $appVersion)
}

if ($pending.Count -eq 0) {
    Write-Host "Already up to date! No new updates to apply." -ForegroundColor Green
    Write-Host ""
    Read-Host "Press Enter to close"
    exit 0
}

Write-Host "Found $($pending.Count) pending update(s) to apply:" -ForegroundColor Cyan
$pending | ForEach-Object { Write-Host "   -> $($_.Name)" -ForegroundColor White }
Write-Host ""

# Apply each update in order
$applied  = 0
$failed   = 0

foreach ($folder in $updateFolders) {
    $pkgScriptPath = Join-Path $folder.FullName 'apply-update.ps1'
    $pkgVerFile    = Join-Path $folder.FullName 'version.txt'

    # Skip folders without the expected structure
    if (-not (Test-Path $pkgScriptPath) -or -not (Test-Path $pkgVerFile)) {
        continue
    }

    $pkgVersion = [int](Get-Content $pkgVerFile | Select-Object -First 1)

    # Skip already-applied updates
    if ($pkgVersion -le $appVersion) {
        Write-Host "[SKIP] $($folder.Name)  (already at version $appVersion)" -ForegroundColor DarkGray
        continue
    }

    Write-Host "---------------------------------------" -ForegroundColor DarkCyan
    Write-Host "[APPLY] $($folder.Name)  (v$appVersion -> v$pkgVersion)" -ForegroundColor Cyan
    Write-Host ""

    try {
        # Call the individual update script, passing the app path
        & powershell -ExecutionPolicy Bypass -File $pkgScriptPath -AppPath $AppPath

        # Re-read updated version (the update script writes it)
        if (Test-Path $verFile) {
            $appVersion = [int](Get-Content $verFile | Select-Object -First 1)
        }

        Write-Host ""
        Write-Host "[OK] $($folder.Name) applied successfully." -ForegroundColor Green
        $applied++
    }
    catch {
        Write-Host ""
        Write-Host "[FAIL] $($folder.Name) failed: $_" -ForegroundColor Red
        Write-Host "       Stopping here. Fix the error above and run this script again." -ForegroundColor Red
        $failed++
        break
    }

    Write-Host ""
}

Write-Host "========================================"
if ($failed -eq 0) {
    Write-Host "  All done!  Applied $applied update(s)." -ForegroundColor Green
    Write-Host "  Current version: $appVersion" -ForegroundColor Green
    Write-Host "  Start the app:   php artisan serve" -ForegroundColor Cyan
} else {
    Write-Host "  Stopped after error. $applied update(s) were applied before the failure." -ForegroundColor Yellow
}
Write-Host "========================================"
Write-Host ""
Read-Host "Press Enter to close"
