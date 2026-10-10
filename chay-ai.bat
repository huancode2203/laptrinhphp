@echo off
chcp 65001 >nul
cd /d "%~dp0"
set "HUB_OLLAMA="
where ollama >nul 2>nul
if not errorlevel 1 set "HUB_OLLAMA=ollama"
if not defined HUB_OLLAMA if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" set "HUB_OLLAMA=%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
if not defined HUB_OLLAMA if exist "%~dp0..\..\work\ollama\runtime\ollama.exe" (
 set "HUB_OLLAMA=%~dp0..\..\work\ollama\runtime\ollama.exe"
 set "OLLAMA_MODELS=%~dp0..\..\work\ollama\models"
)
if not defined HUB_OLLAMA (
 echo Cài Ollama từ https://ollama.com/download/windows rồi mở lại file này.
 pause
 exit /b 1
)
set "OLLAMA_HOST=127.0.0.1:11434"
set "OLLAMA_NO_CLOUD=true"
set "OLLAMA_MAX_LOADED_MODELS=1"
set "OLLAMA_CONTEXT_LENGTH=4096"
powershell -NoProfile -Command "try { $null=Invoke-RestMethod -Uri 'http://127.0.0.1:11434/api/version' -TimeoutSec 3; exit 0 } catch { exit 1 }"
if errorlevel 1 (
 powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath $env:HUB_OLLAMA -ArgumentList 'serve' -WorkingDirectory (Get-Location).Path -WindowStyle Hidden"
 powershell -NoProfile -Command "for($hubAttempt=0;$hubAttempt -lt 20;$hubAttempt++) { try { $null=Invoke-RestMethod -Uri 'http://127.0.0.1:11434/api/version' -TimeoutSec 1; exit 0 } catch { Start-Sleep -Milliseconds 500 } }; exit 1"
 if errorlevel 1 (
  echo Chưa bật được Ollama. Mở ứng dụng Ollama rồi thử lại.
  pause
  exit /b 1
 )
)
echo Tải hoặc kiểm tra hai model. Cần mạng và khoảng 4 GB dung lượng cho model.
call "%HUB_OLLAMA%" pull qwen3:4b
if errorlevel 1 goto pull_failed
call "%HUB_OLLAMA%" pull qwen3:1.7b
if errorlevel 1 goto pull_failed
echo Hai model đã tải. Cấu hình provider ollama trong .env theo README.md.
echo Sau đó chạy php artisan optimize:clear và php artisan hub:integrations --probe.
pause
exit /b 0
:pull_failed
echo Tải model chưa hoàn tất. Kiểm tra mạng rồi mở lại file này để tiếp tục.
pause
exit /b 1
