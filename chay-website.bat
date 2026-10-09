@echo off
chcp 65001 >nul
cd /d "%~dp0"
set "HUB_PHP="
where php >nul 2>nul
if not errorlevel 1 set "HUB_PHP=php"
if not defined HUB_PHP (
 for /d %%P in ("C:\laragon\bin\php\php-*") do if exist "%%P\php.exe" set "HUB_PHP=%%P\php.exe"
)
if not defined HUB_PHP (
 echo Không tìm thấy PHP. Mở Terminal của Laragon và chạy theo README.md.
 pause
 exit /b 1
)
if not exist "vendor\autoload.php" (
 echo Chưa cài thư viện PHP. Hãy chạy composer install theo README.md.
 pause
 exit /b 1
)
if not exist ".env" (
 echo Chưa có .env. Hãy cấu hình database theo README.md.
 pause
 exit /b 1
)
if not exist "public\build\manifest.json" (
 echo Chưa biên dịch giao diện. Hãy chạy npm ci và npm run build.
 pause
 exit /b 1
)
echo Bật MySQL trong Laragon trước khi chạy.
echo Website: http://127.0.0.1:8088
set "HUB_TASK_DIR=%~dp0"
powershell -NoProfile -WindowStyle Hidden -Command "$taskDir=$env:HUB_TASK_DIR; $taskArtisan=Join-Path $taskDir 'artisan'; $taskWorker=Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -like ('*'+$taskArtisan+'*schedule:work*') }; if(-not $taskWorker) { Start-Process -FilePath $env:HUB_PHP -ArgumentList ([char]34+$taskArtisan+[char]34),'schedule:work' -WorkingDirectory $taskDir -WindowStyle Hidden }"
"%HUB_PHP%" artisan serve --host=127.0.0.1 --port=8088
pause
