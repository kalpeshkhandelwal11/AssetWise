$ErrorActionPreference = 'Continue'
$src      = 'G:\AssetWise'
$build    = 'G:\_awbuild'
$php      = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$composer = 'C:\laragon\bin\composer\composer.phar'

Write-Output "== Clean build dir =="
Remove-Item -Recurse -Force $build -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force $build | Out-Null

Write-Output "== Export tracked source (git archive) =="
git -C $src archive --format=tar -o "$build\src.tar" HEAD
tar -xf "$build\src.tar" -C $build
Remove-Item "$build\src.tar"

Write-Output "== Copy vendor + node_modules to speed build =="
robocopy "$src\vendor" "$build\vendor" /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
robocopy "$src\node_modules" "$build\node_modules" /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

Set-Location $build

Write-Output "== composer install --no-dev =="
& $php $composer install --no-dev --optimize-autoloader --no-interaction
Write-Output "composer exit: $LASTEXITCODE"

Write-Output "== npm run build =="
npm run build
Write-Output "npm exit: $LASTEXITCODE"

Write-Output "== BUILD COMPLETE =="
