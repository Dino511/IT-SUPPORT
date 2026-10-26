<#
.SYNOPSIS
    Creates (or updates) IT Inventory's Windows scheduled tasks on this PC.

.DESCRIPTION
    -Backup   "IT Inventory backup": runs scripts\backup.ps1 daily at -BackupTime,
              or as soon as the PC is on after a missed run.
    -Server   "IT Inventory server": runs scripts\start-server.ps1 (PHP's built-in
              web server on port 8000) when this user signs in. Not needed when
              the site runs under IIS.

    Paths are taken from where this script is, so it works wherever the app is
    installed. Both tasks run as the current Windows user, with no window
    (conhost --headless: a "hidden" PowerShell still opens a Windows Terminal
    window on Windows 11, and closing it would stop the job).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\register-tasks.ps1 -Backup
    powershell -ExecutionPolicy Bypass -File scripts\register-tasks.ps1 -Server -Backup -BackupTime 5:30pm -BackupDestination "D:\Backups\IT-Inventory"

.NOTES
    These tasks run only while this user is signed in. On a dedicated server
    where nobody stays signed in: open Task Scheduler, open each task, choose
    "Run whether user is logged on or not" (Windows asks for the password),
    and for the server task change the trigger to "At startup".
#>
param(
    [switch]$Server,
    [switch]$Backup,
    [string]$BackupTime = '12:30pm',
    [string]$BackupDestination
)

if (-not ($Server -or $Backup)) {
    Write-Output 'Nothing to do: add -Backup and/or -Server (see the help at the top of this script).'
    exit 1
}

$root = Split-Path -Parent $PSScriptRoot
$me = "$env:USERDOMAIN\$env:USERNAME"
$principal = New-ScheduledTaskPrincipal -UserId $me -LogonType Interactive -RunLevel Limited

function New-HeadlessAction([string]$script, [string]$arguments = '') {
    New-ScheduledTaskAction -Execute 'conhost.exe' -WorkingDirectory $root `
        -Argument "--headless powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$root\scripts\$script`" $arguments".TrimEnd()
}

if ($Backup) {
    $arguments = if ($BackupDestination) { "-Destination `"$BackupDestination`"" } else { '' }
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -ExecutionTimeLimit (New-TimeSpan -Hours 1) -MultipleInstances IgnoreNew
    Register-ScheduledTask -TaskName 'IT Inventory backup' -Force -Principal $principal -Settings $settings `
        -Description 'Daily backup of IT Inventory (database, PO files, settings), keeping 30 days. Script: scripts\backup.ps1' `
        -Action (New-HeadlessAction 'backup.ps1' $arguments) -Trigger (New-ScheduledTaskTrigger -Daily -At $BackupTime) | Out-Null
    $next = (Get-ScheduledTaskInfo -TaskName 'IT Inventory backup').NextRunTime
    Write-Output "IT Inventory backup: daily at $BackupTime as $me (next run $next)"
}

if ($Server) {
    $settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -StartWhenAvailable -MultipleInstances IgnoreNew -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
    Register-ScheduledTask -TaskName 'IT Inventory server' -Force -Principal $principal -Settings $settings `
        -Description 'Runs IT Inventory on the office network (port 8000) when this user signs in. Script: scripts\start-server.ps1' `
        -Action (New-HeadlessAction 'start-server.ps1') -Trigger (New-ScheduledTaskTrigger -AtLogOn -User $me) | Out-Null
    Write-Output "IT Inventory server: starts when $me signs in. Start it now with: Start-ScheduledTask -TaskName 'IT Inventory server'"
}
