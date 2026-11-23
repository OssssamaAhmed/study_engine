# Start FreeLLMAPI in a separate background window
Start-Process powershell.exe -ArgumentList @(
    "-NoExit", "-Command",
    "Set-Location 'E:\Tools\freellmapi'; node server/dist/index.js"
)

# Wait 3 seconds for FreeLLMAPI to initialize
Start-Sleep -Seconds 3

# Start Study Engine (Laravel) in a separate background window
Start-Process powershell.exe -ArgumentList @(
    "-NoExit", "-Command",
    "Set-Location 'E:\Dev_Projects\study_engine'; php artisan serve --host=127.0.0.1 --port=8000"
)

# Wait 2 seconds for Laravel to start
Start-Sleep -Seconds 2

# Open Study Engine in your default web browser
Start-Process "http://127.0.0.1:8000"
