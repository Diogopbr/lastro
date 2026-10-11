param()
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path $PSScriptRoot -Parent
. (Join-Path $PSScriptRoot 'local-env.ps1')
Push-Location $repoPath
try {
    foreach ($command in @('php','composer','node','npm')) {
        if (-not (Get-Command $command -ErrorAction SilentlyContinue)) { throw "Missing prerequisite: $command. See README for portable setup." }
    }
    if (-not (Test-Path '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
    composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
    composer check-platform-reqs
    if ($LASTEXITCODE -ne 0) { throw 'PHP platform check failed.' }
    npm ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Explicit migrations failed.' }
    npm run typecheck
    if ($LASTEXITCODE -ne 0) { throw 'Type check failed.' }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Asset build failed.' }
} finally { Pop-Location }
