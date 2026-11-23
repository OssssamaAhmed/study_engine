# Study Engine offline updater - run from inside an update-NNN folder:
#   powershell -ExecutionPolicy Bypass -File .\apply-update.ps1
# Your data (database, .env, storage) is never touched; replaced files are backed up.
param([string]$AppPath)

$ErrorActionPreference = 'Stop'
$pkg = $PSScriptRoot
$pkgVersion = [int](Get-Content (Join-Path $pkg 'version.txt') | Select-Object -First 1)

if (-not $AppPath) {
    $AppPath = Read-Host "Full path of your Study Engine folder (it contains a file named 'artisan')"
}
$AppPath = $AppPath.Trim('"').Trim()
if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
    Write-Host "Could not find 'artisan' in '$AppPath' - that does not look like the Study Engine folder." -ForegroundColor Red
    exit 1
}

$verFile = Join-Path $AppPath 'version.txt'
$appVersion = 1
if (Test-Path $verFile) { $appVersion = [int](Get-Content $verFile | Select-Object -First 1) }

if ($appVersion -ge $pkgVersion) {
    Write-Host "This app is already at version $appVersion - nothing to do." -ForegroundColor Green
    exit 0
}
if ($appVersion -lt ($pkgVersion - 1)) {
    Write-Host "This app is at version $appVersion. Apply update $($appVersion + 1) first, then this one." -ForegroundColor Red
    exit 1
}

# Back up every file this update will replace, so it can be undone.
$filesDir = Join-Path $pkg 'files'
$backupDir = Join-Path $pkg "backup-before-update-$pkgVersion"
Get-ChildItem $filesDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($filesDir.Length + 1)
    $target = Join-Path $AppPath $rel
    if (Test-Path $target) {
        $dest = Join-Path $backupDir $rel
        New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
        Copy-Item $target $dest -Force
    }
}

Write-Host "Copying updated files..."
robocopy $filesDir $AppPath /E /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) {
    Write-Host "File copy failed (robocopy code $LASTEXITCODE)." -ForegroundColor Red
    exit 1
}

# Files the new version no longer uses.
$deletions = Join-Path $pkg 'deletions.txt'
if (Test-Path $deletions) {
    Get-Content $deletions | ForEach-Object {
        $line = $_.Trim()
        if ($line) {
            $t = Join-Path $AppPath $line
            if (Test-Path $t) { Remove-Item $t -Force }
        }
    }
}

# New settings introduced by the update: append any .env key this copy is missing.
$envExample = Join-Path $filesDir '.env.example'
$envFile = Join-Path $AppPath '.env'
if ((Test-Path $envExample) -and (Test-Path $envFile)) {
    $have = @()
    Get-Content $envFile | ForEach-Object { if ($_ -match '^([A-Za-z0-9_]+)=') { $have += $Matches[1] } }
    $missing = @()
    Get-Content $envExample | ForEach-Object { if ($_ -match '^([A-Za-z0-9_]+)=') { if ($have -notcontains $Matches[1]) { $missing += $_ } } }
    if ($missing.Count -gt 0) {
        Add-Content $envFile ""
        Add-Content $envFile "# Added by update $pkgVersion"
        $missing | ForEach-Object { Add-Content $envFile $_ }
        Write-Host ("Added new settings to .env: " + (($missing | ForEach-Object { ($_ -split '=', 2)[0] }) -join ', '))
    }
}

Write-Host "Updating the database structure (your data stays)..."
Push-Location $AppPath
php artisan migrate --force
php artisan view:clear
php artisan config:clear
php artisan cache:clear
Pop-Location

Set-Content $verFile $pkgVersion
Write-Host ""
Write-Host "Done - updated to version $pkgVersion. Start the app again with: php artisan serve" -ForegroundColor Green
