# ============================================================
# GOR Smash Arena - Setup Script untuk Localhost (Windows)
# Jalankan sebagai: powershell -ExecutionPolicy Bypass -File setup-localhost.ps1
# ============================================================

$phpExe = "C:\xampp\php\php.exe"
$apiDir  = "$PSScriptRoot\api"
$composerUrl = "https://getcomposer.org/composer-stable.phar"
$composerPhar = "$apiDir\composer.phar"

Write-Host "============================================" -ForegroundColor Cyan
Write-Host " GOR Smash Arena - Setup Localhost" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan

# 1. Cek PHP
if (-not (Test-Path $phpExe)) {
    Write-Host "[ERROR] PHP tidak ditemukan di $phpExe" -ForegroundColor Red
    Write-Host "Pastikan XAMPP terinstall atau tambahkan PHP ke PATH" -ForegroundColor Yellow
    exit 1
}
$phpVer = & $phpExe --version | Select-Object -First 1
Write-Host "[OK] $phpVer" -ForegroundColor Green

# 2. Download Composer jika belum ada
if (-not (Test-Path $composerPhar)) {
    Write-Host "[INFO] Mengunduh Composer..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $composerUrl -OutFile $composerPhar
    Write-Host "[OK] Composer diunduh" -ForegroundColor Green
} else {
    Write-Host "[OK] Composer sudah ada" -ForegroundColor Green
}

Set-Location $apiDir

# 3. Install vendor dependencies
if (-not (Test-Path "$apiDir\vendor")) {
    Write-Host "[INFO] Menjalankan composer install (ini butuh beberapa menit)..." -ForegroundColor Yellow
    & $phpExe $composerPhar install --no-interaction --prefer-dist
    Write-Host "[OK] Vendor dependencies terinstall" -ForegroundColor Green
} else {
    Write-Host "[OK] vendor/ sudah ada, skip composer install" -ForegroundColor Green
}

# 4. Buat .env dari .env.example
if (-not (Test-Path "$apiDir\.env")) {
    Copy-Item "$apiDir\.env.example" "$apiDir\.env"
    Write-Host "[OK] .env dibuat dari .env.example" -ForegroundColor Green
} else {
    Write-Host "[OK] .env sudah ada" -ForegroundColor Green
}

# 5. Generate APP_KEY
Write-Host "[INFO] Generate APP_KEY..." -ForegroundColor Yellow
& $phpExe artisan key:generate
Write-Host "[OK] APP_KEY di-generate" -ForegroundColor Green

# 6. Buat SQLite database file
$dbFile = "$apiDir\database\database.sqlite"
if (-not (Test-Path $dbFile)) {
    New-Item -ItemType File -Path $dbFile -Force | Out-Null
    Write-Host "[OK] database.sqlite dibuat" -ForegroundColor Green
} else {
    Write-Host "[OK] database.sqlite sudah ada" -ForegroundColor Green
}

# 7. Jalankan migrasi
Write-Host "[INFO] Menjalankan database migrations..." -ForegroundColor Yellow
& $phpExe artisan migrate --force
Write-Host "[OK] Migrasi selesai" -ForegroundColor Green

# 8. Jalankan seeder
Write-Host "[INFO] Menjalankan database seeder..." -ForegroundColor Yellow
& $phpExe artisan db:seed --force
Write-Host "[OK] Seeder selesai" -ForegroundColor Green

# 9. Buat storage link
Write-Host "[INFO] Membuat storage link..." -ForegroundColor Yellow
& $phpExe artisan storage:link
Write-Host "[OK] Storage link dibuat" -ForegroundColor Green

# 10. Set permissions storage
Write-Host "[INFO] Set permissions storage..." -ForegroundColor Yellow
& $phpExe artisan optimize:clear

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " Setup SELESAI!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Jalankan backend dengan perintah:" -ForegroundColor Cyan
Write-Host "  cd api" -ForegroundColor White
Write-Host "  C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor White
Write-Host ""
Write-Host "Lalu buka browser ke:" -ForegroundColor Cyan
Write-Host "  http://localhost:8000  (HTML app frontend)" -ForegroundColor White
Write-Host "  http://localhost:8000/api/health  (cek backend)" -ForegroundColor White

