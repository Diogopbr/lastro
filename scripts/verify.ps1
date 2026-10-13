param([switch]$SkipBrowser)
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path $PSScriptRoot -Parent
. (Join-Path $PSScriptRoot 'local-env.ps1') -Test
if ($env:DB_DATABASE -ne 'lastro_test' -or $env:DB_USERNAME -ne 'lastro_test') {
    throw 'Verification requires the isolated Lastro test database and role.'
}
Push-Location $repoPath
try {
    composer validate --strict
    if ($LASTEXITCODE -ne 0) { throw 'Composer validation failed.' }
    composer check-platform-reqs
    if ($LASTEXITCODE -ne 0) { throw 'PHP platform requirements failed.' }
    php vendor/bin/pint --test --format agent
    if ($LASTEXITCODE -ne 0) { throw 'PHP format check failed.' }
    php artisan test --compact
    if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL tests failed.' }
    npm run typecheck
    if ($LASTEXITCODE -ne 0) { throw 'Frontend type check failed.' }
    npm test -- --run
    if ($LASTEXITCODE -ne 0) { throw 'Frontend tests failed.' }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    if (-not $SkipBrowser) {
        if ($env:E2E_BASE_URL) { throw 'Local verification cannot use E2E_BASE_URL; invoke live tests explicitly.' }
        php artisan migrate --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Test database migrations failed.' }
        php artisan cache:clear --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Test cache preparation failed.' }
        npm run test:e2e
        if ($LASTEXITCODE -ne 0) { throw 'Browser tests failed.' }
    }
    git diff --check
    if ($LASTEXITCODE -ne 0) { throw 'Whitespace check failed.' }
} finally { Pop-Location }
