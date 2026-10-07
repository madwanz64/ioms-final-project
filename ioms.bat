@echo off
rem Perintah harian IOMS lewat Docker Compose (Windows CMD / PowerShell).
rem Pakai: ioms <perintah>      Daftar perintah: ioms help
rem Padanan Linux/macOS/Git Bash: ./ioms.sh
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

set "CMD=%~1"
if "%CMD%"=="" set "CMD=help"

rem Nilai dari .env (default bila tidak ada)
set "APP_PORT=8080"
set "SONAR_PORT=9000"
set "SONAR_TOKEN="
if exist .env (
  for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do (
    if /i "%%a"=="APP_PORT" set "APP_PORT=%%b"
    if /i "%%a"=="SONAR_PORT" set "SONAR_PORT=%%b"
    if /i "%%a"=="SONAR_TOKEN" set "SONAR_TOKEN=%%b"
  )
)

if /i "%CMD%"=="up" goto up
if /i "%CMD%"=="down" goto down
if /i "%CMD%"=="reset" goto reset
if /i "%CMD%"=="status" goto status
if /i "%CMD%"=="logs" goto logs
if /i "%CMD%"=="shell" goto shell
if /i "%CMD%"=="test" goto test
if /i "%CMD%"=="test-unit" goto testunit
if /i "%CMD%"=="test-int" goto testint
if /i "%CMD%"=="analyse" goto analyse
if /i "%CMD%"=="low-stock" goto lowstock
if /i "%CMD%"=="sonar" goto sonar
if /i "%CMD%"=="sonar-scan" goto sonarscan
if /i "%CMD%"=="sonar-stop" goto sonarstop
if /i "%CMD%"=="help" goto usage
echo Perintah tidak dikenal: %CMD% 1>&2
call :usage
exit /b 1

:up
call :ensure_env
docker compose up --build -d || exit /b 1
call :wait_for "http://localhost:%APP_PORT%/login" "aplikasi" ""
exit /b %errorlevel%

:down
docker compose down
exit /b %errorlevel%

:reset
set "ANSWER="
set /p "ANSWER=Semua data database & gambar upload akan dihapus dan diganti seed. Lanjut? (y/N) "
if /i not "%ANSWER%"=="y" (
  echo Dibatalkan.
  exit /b 0
)
call :ensure_env
docker compose down -v || exit /b 1
docker compose up --build -d || exit /b 1
call :wait_for "http://localhost:%APP_PORT%/login" "aplikasi" ""
exit /b %errorlevel%

:status
docker compose --profile sonar ps
exit /b %errorlevel%

:logs
docker compose logs -f --tail=100 app db
exit /b %errorlevel%

:shell
docker compose exec app bash
exit /b %errorlevel%

:test
docker compose exec app composer test
exit /b %errorlevel%

:testunit
docker compose exec app composer test:unit
exit /b %errorlevel%

:testint
docker compose exec app composer test:integration
exit /b %errorlevel%

:analyse
docker compose exec app composer analyse
exit /b %errorlevel%

:lowstock
docker compose exec app composer low-stock
exit /b %errorlevel%

:sonar
docker compose --profile sonar up -d sonarqube || exit /b 1
call :wait_for "http://localhost:%SONAR_PORT%/api/system/status" "SonarQube" "\"UP\""
if errorlevel 1 exit /b 1
if "%SONAR_TOKEN%"=="" (
  echo ^>^> Pertama kali: login admin/admin, ganti password, buat token di
  echo    My Account ^> Security, lalu isi SONAR_TOKEN=... di .env
)
exit /b 0

:sonarscan
if "%SONAR_TOKEN%"=="" (
  echo !! SONAR_TOKEN belum diisi di .env. Jalankan "ioms sonar" lalu buat token. 1>&2
  exit /b 1
)
docker compose exec app composer test:coverage || exit /b 1
if exist build rmdir /s /q build
docker compose cp app:/var/www/html/build ./build || exit /b 1
docker compose --profile sonar run --rm sonar-scanner || exit /b 1
echo ^>^> Hasil: http://localhost:%SONAR_PORT%/dashboard?id=ioms
exit /b 0

:sonarstop
docker compose --profile sonar stop sonarqube
exit /b %errorlevel%

rem ---------------------------------------------------------------------------
:ensure_env
if not exist .env (
  copy /y .env.example .env >nul
  echo ^>^> .env dibuat dari .env.example ^(ubah DB_PASS bila perlu, sebelum database pertama kali dibuat^).
)
exit /b 0

:wait_for
rem %1 URL, %2 label, %3 teks yang ditunggu di isi respons (boleh kosong)
<nul set /p "=>> Menunggu %~2"
for /l %%i in (1,1,90) do (
  set "BODY="
  for /f "delims=" %%r in ('curl -fsS "%~1" 2^>nul') do set "BODY=%%r"
  if defined BODY (
    if "%~3"=="" (
      echo  siap: %~1
      exit /b 0
    )
    echo !BODY! | findstr /c:%3 >nul && (
      echo  siap: %~1
      exit /b 0
    )
  )
  <nul set /p "=."
  timeout /t 2 /nobreak >nul
)
echo.
echo !! %~2 belum siap setelah 3 menit. Cek: ioms logs 1>&2
exit /b 1

:usage
echo Pakai: ioms ^<perintah^>
echo.
echo Aplikasi
echo   up           Build ^& jalankan app + database (membuat .env bila belum ada)
echo   down         Hentikan container (data tetap)
echo   reset        Hapus database ^& upload lalu mulai ulang dari seed (minta konfirmasi)
echo   status       Status container
echo   logs         Log app ^& database (Ctrl+C untuk keluar)
echo   shell        Masuk shell container app
echo.
echo Kualitas
echo   test         Unit + integration test
echo   test-unit    Unit test saja
echo   test-int     Integration test saja
echo   analyse      PHPStan level 6
echo   low-stock    Script terjadwal JOB-01 (laporan stok di bawah reorder point)
echo.
echo SonarQube (opsional)
echo   sonar        Jalankan server SonarQube (http://localhost:9000)
echo   sonar-scan   Coverage + scan (butuh SONAR_TOKEN di .env)
echo   sonar-stop   Hentikan server SonarQube
exit /b 0
