# Study Engine startup — starts FreeLLMAPI, then the app, then opens both plus Obsidian.

$FreeLlmApi  = "C:\Users\HP\tools\freellmapi"
$StudyEngine = "E:\Dev_Projects\study_engine"
$VaultName   = "AI-Engineering-Vault"

if (-not (Test-Path "$FreeLlmApi\server\dist\index.js")) {
    Write-Error "FreeLLMAPI build not found at $FreeLlmApi\server\dist\index.js — run 'npm run build' there first."
    exit 1
}

if (-not (Test-Path "$StudyEngine\artisan")) {
    Write-Error "Study Engine not found at $StudyEngine — check the path."
    exit 1
}

Start-Process powershell.exe -ArgumentList @(
    "-NoExit", "-Command",
    "Set-Location '$FreeLlmApi'; node server/dist/index.js"
)

Start-Sleep -Seconds 4

Start-Process powershell.exe -ArgumentList @(
    "-NoExit", "-Command",
    "Set-Location '$StudyEngine'; php artisan serve --host=127.0.0.1 --port=8000"
)

Start-Sleep -Seconds 3

Start-Process "obsidian://open?vault=$VaultName"
Start-Process "http://127.0.0.1:8000"