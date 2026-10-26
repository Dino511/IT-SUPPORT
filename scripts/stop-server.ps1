<#
.SYNOPSIS
    Stops IT Inventory's web server (started by scripts\start-server.ps1 or the
    "IT Inventory server" scheduled task). Start it again with:
        Start-ScheduledTask -TaskName 'IT Inventory server'

.DESCRIPTION
    Stopping the scheduled task alone leaves php.exe running, so this also ends
    the php.exe that serves this app (matched by its command line; other PHP
    processes are left alone).
#>
$appRoot = Split-Path -Parent $PSScriptRoot
$router = Join-Path $appRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$log = Join-Path $appRoot 'storage\logs\server.log'

Stop-ScheduledTask -TaskName 'IT Inventory server' -ErrorAction SilentlyContinue
$servers = Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like "*$router*" }
$servers | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

$message = if ($servers) { "STOPPED  by stop-server.ps1 (pid $(@($servers.ProcessId) -join ', '))" } else { 'STOP REQUESTED  but no server was running' }
Add-Content -Path $log -Value ('{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message)
Write-Output $message
