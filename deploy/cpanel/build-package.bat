@echo off
REM ============================================================
REM Membuat paket deploy cPanel: ..\data-narasi-deploy.tar.gz
REM
REM Jalankan dari mana saja:  deploy\cpanel\build-package.bat
REM
REM PENTING — kenapa tidak memakai Compress-Archive seperti dulu:
REM
REM   Versi lama membuang SELURUH folder storage/. Akibatnya di cPanel
REM   setiap request menghasilkan HTTP 500 "Please provide a valid cache
REM   path", karena View\Compilers\Compiler::__construct menolak jalan
REM   kalau storage/framework/views tidak ada. Laravel hanya membuat
REM   sendiri sebagian folder (cache, logs) — folder sessions dan views
REM   TIDAK dibuat otomatis.
REM
REM   Skrip ini memakai bsdtar (bawaan Windows 10+) dan pola "[!.]*",
REM   yang membuang ISI folder runtime tanpa membuang foldernya, sehingga
REM   berkas .gitignore dan entri folder tetap ikut.
REM
REM Yang TIDAK ikut: .env, node_modules, .git, vendor.
REM   vendor sengaja dikeluarkan — di server jalankan
REM   "composer install --no-dev --optimize-autoloader" (jauh lebih cepat
REM   daripada mengunggah ~25 ribu berkas kecil lewat SFTP).
REM ============================================================

setlocal enabledelayedexpansion
cd /d "%~dp0\..\.."
set "ROOT=%CD%"
set "OUT=%ROOT%\..\data-narasi-deploy.tar.gz"

where tar.exe >nul 2>&1
if errorlevel 1 (
    echo GAGAL: tar.exe tidak ditemukan. Windows 10 1803+ seharusnya punya.
    exit /b 1
)

if exist "%OUT%" del /f /q "%OUT%"

echo [1/3] Build aset frontend...
REM "npm run build" gagal di mesin ini karena shim nvm di PATH menangkap
REM argumen tambahan ("too many arguments"). Memanggil vite langsung
REM menghindari shim itu dan hasilnya sama.
call node_modules\.bin\vite.cmd build
if errorlevel 1 goto :error

echo [2/3] Bersihkan cache Laravel...
php artisan optimize:clear

echo [3/3] Membuat paket...
pushd "%ROOT%"
REM Pola "[!.]*" = "semua kecuali yang diawali titik". Ini membuang ISI folder
REM runtime (log, session, cache, chart) tetapi MEMPERTAHANKAN folder itu
REM sendiri beserta berkas .gitignore penandanya. Memakai "*" biasa akan
REM menghapus entri foldernya juga — itulah yang menyebabkan HTTP 500.
REM ".env" dikecualikan tepat, BUKAN "./.env.*", karena pola terakhir ikut
REM membuang .env.example yang wajib ada di server.
tar.exe -czf "%OUT%" ^
  --exclude="./.env" ^
  --exclude="./.git" ^
  --exclude="./.github" ^
  --exclude="./.cursor" ^
  --exclude="./.claude" ^
  --exclude="./.fleet" ^
  --exclude="./.idea" ^
  --exclude="./.vscode" ^
  --exclude="./.phpunit.result.cache" ^
  --exclude="./.styleci.yml" ^
  --exclude="./node_modules" ^
  --exclude="./vendor" ^
  --exclude="./python-service" ^
  --exclude="./dummy-data" ^
  --exclude="./docs" ^
  --exclude="./tests" ^
  --exclude="./claude-result-perbandingan" ^
  --exclude="./public/hot" ^
  --exclude="./public/storage" ^
  --exclude="./storage/logs/[!.]*" ^
  --exclude="./storage/framework/sessions/[!.]*" ^
  --exclude="./storage/framework/views/[!.]*" ^
  --exclude="./storage/framework/cache/data/[!.]*" ^
  --exclude="./storage/framework/testing" ^
  --exclude="./storage/app/private/uploads/[!.]*" ^
  --exclude="./storage/app/private/cleans/[!.]*" ^
  --exclude="./storage/app/public/charts/[!.]*" ^
  --exclude="./storage/charts" ^
  --exclude="./*.mjs" ^
  --exclude="./Dockerfile" ^
  --exclude="./.dockerignore" ^
  --exclude="./Procfile" ^
  --exclude="./test_penjualan.csv" ^
  --exclude="./Data FMCG_BT Data Analyst - Clean Fix - Copy.csv" ^
  --exclude="./claude --resume*" ^
  --exclude="./deploy/pwsupersecretcpanel.txt" ^
  --exclude="./deploy/PANDUAN_AKSES_CPANEL_SSH.md" ^
  --exclude="./deploy/ssh_helper.py" ^
  .
popd
if errorlevel 1 goto :error

echo.
echo Memverifikasi isi paket...
tar.exe -tzf "%OUT%" > "%TEMP%\dn-manifest.txt" 2>&1
set "FAIL="

REM Wajib ADA
for %%F in (
  "./artisan"
  "./.env.example"
  "./composer.json"
  "./composer.lock"
  "./public/index.php"
  "./public/build/manifest.json"
  "./bootstrap/cache/.gitignore"
  "./storage/framework/views/.gitignore"
  "./storage/framework/sessions/.gitignore"
  "./storage/framework/cache/data/.gitignore"
  "./storage/logs/.gitignore"
  "./storage/app/public/.gitignore"
  "./storage/app/private/.gitignore"
  "./resources/fonts/DejaVuSans.ttf"
  "./resources/fonts/DejaVuSans-Bold.ttf"
  "./deploy/cpanel/queue-worker.sh"
  "./deploy/cpanel/.env.cpanel.example"
  "./deploy/cpanel/root.htaccess"
) do (
    findstr /x /l /c:"%%~F" "%TEMP%\dn-manifest.txt" >nul 2>&1
    if errorlevel 1 (
        echo   HILANG: %%~F
        set "FAIL=1"
    )
)

REM Wajib TIDAK ADA
for %%F in (
  "./.env"
  "./deploy/pwsupersecretcpanel.txt"
  "./vendor/autoload.php"
) do (
    findstr /x /l /c:"%%~F" "%TEMP%\dn-manifest.txt" >nul 2>&1
    if not errorlevel 1 (
        echo   SEHARUSNYA TIDAK IKUT: %%~F
        set "FAIL=1"
    )
)

del /f /q "%TEMP%\dn-manifest.txt" 2>nul

if defined FAIL (
    echo.
    echo GAGAL: isi paket tidak sesuai. Periksa daftar di atas.
    del /f /q "%OUT%"
    exit /b 1
)

echo   OK — berkas wajib lengkap, .env / kredensial / vendor tidak ikut.
echo.
echo Selesai: %OUT%
for %%A in ("%OUT%") do echo Ukuran : %%~zA bytes
echo.
echo Langkah di server:
echo   1. Upload ^& extract ke document root
echo   2. composer install --no-dev --optimize-autoloader
echo   3. Buat .env dari deploy/cpanel/.env.cpanel.example
echo   4. php artisan key:generate
echo   5. php artisan migrate --force
echo   6. php artisan db:seed --force
echo   7. php artisan storage:link
echo   8. php artisan datanarasi:doctor
echo   9. Pasang cron queue worker (lihat README)
goto :eof

:error
echo GAGAL membuat paket.
exit /b 1
