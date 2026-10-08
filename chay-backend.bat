@echo off
chcp 65001 >nul
cd /d "%~dp0"
set "BADMINTON_PHP=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
if not exist "%BADMINTON_PHP%" (
  echo Không tìm thấy PHP Laragon. Hãy xem hướng dẫn trong README.md.
  pause
  exit /b 1
)
if not exist "vendor\autoload.php" (
  echo Bản mã nguồn cần cài thư viện trước. Hãy xem README.md.
  pause
  exit /b 1
)
powershell -NoProfile -Command "if (Get-NetTCPConnection -State Listen -LocalPort 8088 -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
if not errorlevel 1 (
  echo Dịch vụ đã chạy tại http://127.0.0.1:8088
  echo Đây là phần backend. Giao diện theo Figma còn cần hoàn thiện.
  pause
  exit /b 0
)
echo Khởi động MySQL trong Laragon trước khi chạy.
echo Backend: http://127.0.0.1:8088
"%BADMINTON_PHP%" artisan serve --host=127.0.0.1 --port=8088
pause
