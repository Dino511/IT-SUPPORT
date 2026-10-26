<#
.SYNOPSIS
    Runs IT Inventory on the office network with PHP's built-in web server.

.DESCRIPTION
    Serves the app at http://<this PC's name or IP>:<Port> for every PC on the
    network (Listen 0.0.0.0), and keeps running until stopped. Meant to be
    started by the "IT Inventory server" scheduled task when the Windows user
    signs in (see docs/DEVELOPER_GUIDE.md, "Running on the office network").

    Other PCs can only connect once Windows Firewall allows the port on the
    office (Private) network. Start/stop lines go to storage\logs\server.log;
    errors inside the app still go to storage\logs\laravel-<date>.log.

    PHP's built-in server answers one request at a time, which is fine for a
    small IT team. For many users, or to run without anyone signed in, move
    to IIS.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\start-server.ps1
    powershell -ExecutionPolicy Bypass -File scripts\start-server.ps1 -Port 8080 -Listen 127.0.0.1   # this PC only
#>
param(
    [int]$Port = 8000,
    [string]$Listen = '0.0.0.0'
)

$appRoot = Split-Path -Parent $PSScriptRoot
$log = Join-Path $appRoot 'storage\logs\server.log'
# the same router "php artisan serve" uses: files in public\ are served as-is,
# everything else goes through public\index.php
$router = Join-Path $appRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

function Write-Log([string]$message) {
    Add-Content -Path $log -Value ('{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message)
}

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) { Write-Log "NOT STARTED  php.exe is not on the PATH"; exit 1 }
$busy = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
if ($busy) {
    $owner = Get-CimInstance Win32_Process -Filter "ProcessId=$($busy.OwningProcess)"
    if ($owner -and $owner.Name -eq 'php.exe' -and $owner.CommandLine -like "*$router*") {
        # an earlier copy of this server: stopping the scheduled task does not
        # end php.exe, so starting the task again replaces it (= restart)
        Stop-Process -Id $owner.ProcessId -Force
        Start-Sleep -Seconds 1
        Write-Log "REPLACED  earlier server process $($owner.ProcessId)"
    }
    else {
        Write-Log "NOT STARTED  port $Port is in use by $($owner.Name) (pid $($busy.OwningProcess))"
        exit 1
    }
}

# the router finds public\index.php from the current folder, so start there
Set-Location (Join-Path $appRoot 'public')
Write-Log "STARTED  http://$($env:COMPUTERNAME):$Port  (listening on ${Listen}, PHP $php)"
# PHP prints one line per request; nobody reads them, so they are discarded
& $php -S "${Listen}:$Port" -t (Join-Path $appRoot 'public') $router *> $null
Write-Log "STOPPED  exit code $LASTEXITCODE"
exit $LASTEXITCODE
