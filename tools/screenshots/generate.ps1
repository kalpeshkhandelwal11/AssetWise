<#
  Regenerates the AssetWise user-handbook screenshots.

  It spins up the app against a throwaway SQLite database seeded with demo data
  (your real MySQL is never touched), serves it locally, drives it with Playwright
  to capture every documented screen, adds call-out boxes to the walked-through
  screens, then tears everything down.

  Usage (from anywhere):
      cd tools/screenshots
      npm install          # one time — installs Playwright into this folder
      ./generate.ps1       # regenerate docs/user-guide/screenshots/*.png

  Options:
      ./generate.ps1 -Port 8231
#>
param(
  [int]$Port = 8123
)

# 'Continue' (not 'Stop') so a native command writing to stderr isn't treated as a
# terminating error; the script guards the steps that matter with explicit throws.
$ErrorActionPreference = 'Continue'
$tool = $PSScriptRoot
$repo = (Get-Item $tool).Parent.Parent.FullName
$sqlite = Join-Path $repo 'database\screenshots.sqlite'
$base = "http://127.0.0.1:$Port"

# --- locate PHP (Laragon puts it outside PATH) ---
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
  $php = Get-ChildItem 'C:\laragon\bin\php' -Recurse -Filter php.exe -ErrorAction SilentlyContinue |
         Select-Object -First 1 -ExpandProperty FullName
}
if (-not $php) { throw "Could not find php.exe. Add PHP to PATH or edit this script." }
Write-Host "PHP:  $php"
Write-Host "Repo: $repo"

# --- point this process (and its children) at the throwaway SQLite DB ---
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE   = $sqlite
$env:APP_URL       = $base

Push-Location $repo
$server = $null
try {
  if (Test-Path $sqlite) { Remove-Item $sqlite -Force }
  New-Item -ItemType File $sqlite | Out-Null

  Write-Host "`n== migrating + seeding (SQLite) ==" -ForegroundColor Cyan
  & $php artisan migrate:fresh --force
  # Seed only the classes we need — SingareenipallyAssetSeeder uses MySQL-only SQL,
  # and its demo assets are replaced by seed-demo.php below.
  foreach ($c in 'RolePermissionSeeder','AdminUserSeeder','SharedMastersSeeder','WorkflowSeeder','SettingsSeeder','DepreciationMethodSeeder') {
    & $php artisan db:seed --class=$c --force
  }
  & $php artisan tinker --execute="require base_path('tools/screenshots/seed-demo.php');"

  Write-Host "`n== starting dev server on $base ==" -ForegroundColor Cyan
  $server = Start-Process -FilePath $php -ArgumentList 'artisan','serve','--host=127.0.0.1',"--port=$Port" -PassThru
  $env:SHOT_BASE = $base

  # wait for the server to answer
  $up = $false
  for ($i = 0; $i -lt 20; $i++) {
    try { Invoke-WebRequest "$base/login" -UseBasicParsing -TimeoutSec 3 | Out-Null; $up = $true; break }
    catch { Start-Sleep -Milliseconds 500 }
  }
  if (-not $up) { throw "Dev server did not come up on $base" }

  Write-Host "`n== capturing screenshots ==" -ForegroundColor Cyan
  & node (Join-Path $tool 'capture.cjs')
  Write-Host "`n== adding call-out boxes ==" -ForegroundColor Cyan
  & node (Join-Path $tool 'annotate.cjs')

  Write-Host "`nScreenshots written to docs/user-guide/screenshots/" -ForegroundColor Green
}
finally {
  Write-Host "`n== cleanup ==" -ForegroundColor Cyan
  if ($server) { Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue }
  # kill any lingering serve/tinker php that could hold the SQLite file
  Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -like '*artisan serve*' -or $_.CommandLine -like '*tinker*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
  Start-Sleep -Milliseconds 800
  if (Test-Path $sqlite) { Remove-Item $sqlite -Force -ErrorAction SilentlyContinue }
  Pop-Location
}
