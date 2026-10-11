param([switch]$Stop)
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path $PSScriptRoot -Parent
. (Join-Path $PSScriptRoot 'local-env.ps1')
$statePath = Join-Path $repoPath '.local/dev-process.json'
if ($Stop) {
    if (Test-Path $statePath) {
        $state = Get-Content $statePath -Raw | ConvertFrom-Json
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($state.pid)" -ErrorAction SilentlyContinue
        if ($process) {
            if ($process.ExecutablePath -ne $state.executable -or $process.CommandLine -notmatch 'artisan serve --host=127.0.0.1 --port=5081') { throw 'Process identity changed; refusing to stop it.' }
            & taskkill.exe /PID $state.pid /T /F | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Could not stop the development server.' }
        }
        Remove-Item -LiteralPath $statePath
    }
    return
}
if (Test-Path $statePath) { throw 'Development process state exists; check it before starting another server.' }
$php = (Get-Command php).Source
$process = Start-Process -FilePath $php -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=5081') -WorkingDirectory $repoPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $repoPath '.local/dev.log') -RedirectStandardError (Join-Path $repoPath '.local/dev-error.log')
@{pid=$process.Id;executable=$php} | ConvertTo-Json | Set-Content $statePath -Encoding utf8
