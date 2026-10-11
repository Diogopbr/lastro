param([switch]$Test)
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path $PSScriptRoot -Parent
$commonDir = & git -C $repoPath rev-parse --git-common-dir 2>$null
if ($LASTEXITCODE -eq 0 -and $commonDir) {
    $commonPath = if ([IO.Path]::IsPathRooted($commonDir)) { $commonDir } else { Join-Path $repoPath $commonDir }
    $sourceRoot = Split-Path ([IO.Path]::GetFullPath($commonPath)) -Parent
} else { $sourceRoot = $repoPath }
$toolsRoot = Join-Path (Split-Path $sourceRoot -Parent) '.tools'
$env:PATH = (Join-Path $toolsRoot 'node') + ';' + (Join-Path $toolsRoot 'php') + ';' + (Join-Path $toolsRoot 'composer') + ';' + $env:PATH
$settingsPath = Join-Path $sourceRoot '.local/settings.json'
if (-not (Test-Path $settingsPath)) { throw 'Run scripts/bootstrap.ps1 or provide your own PHP/PostgreSQL environment.' }
$settings = Get-Content -LiteralPath $settingsPath -Raw | ConvertFrom-Json
$env:APP_KEY = $settings.appKey
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = $settings.host
$env:DB_PORT = [string]$settings.port
$env:DB_DATABASE = if ($Test) { $settings.testDatabase } else { $settings.database }
$env:DB_USERNAME = if ($Test) { $settings.testUsername } else { $settings.username }
$env:DB_PASSWORD = if ($Test) { $settings.testPassword } else { $settings.password }
$env:APP_ENV = if ($Test) { 'testing' } else { 'local' }

