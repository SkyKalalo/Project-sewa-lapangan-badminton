# ============================================================
# GOR Smash Arena - Start Laravel Dev Server
# ============================================================
$phpExe = "C:\xampp\php\php.exe"
$apiDir  = "$PSScriptRoot\api"

Set-Location $apiDir
Write-Host "Starting GOR Smash Arena on http://localhost:8000 ..." -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop." -ForegroundColor Yellow
Write-Host ""
& $phpExe artisan serve --host=0.0.0.0 --port=8000

