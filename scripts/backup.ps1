<#
.SYNOPSIS
    Backs up IT Inventory: the SQL Server database, the uploaded PO files and
    the .env settings, into one dated folder. Old backups are pruned.

.DESCRIPTION
    Each run creates  <Destination>\<yyyy-MM-dd_HHmmss>\  containing:
      it_inventory.bak   full database backup (verified after writing)
      po-files.zip       storage\app\private\po (purchase order uploads), if any
      env.txt            copy of .env (holds APP_KEY; keep backups private)
    A line is added to <Destination>\backup-log.txt, and backup folders older
    than -KeepDays are deleted. Exits with code 1 on failure so Task Scheduler
    shows the run as failed.

    SQL Server Express has no SQL Agent, so schedule this script with Windows
    Task Scheduler instead (see docs/DEVELOPER_GUIDE.md, "Backups").
    The Windows user running it needs access to the database and to SQL
    Server's default backup folder (the user who installed SQL Server has both).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\backup.ps1
    powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination "D:\Backups\IT-Inventory" -KeepDays 60

.NOTES
    Restore the database (stop the app first; this replaces the current data):
      sqlcmd -S "localhost\SQLEXPRESS" -E -Q "RESTORE DATABASE [it_inventory] FROM DISK = N'<folder>\it_inventory.bak' WITH REPLACE"
    Restore PO files: unzip po-files.zip into storage\app\private\po.
    Written for Windows PowerShell 5.1 (what Task Scheduler runs).
#>
param(
    [string]$Destination = 'C:\Backups\IT-Inventory',
    [string]$Server = 'localhost\SQLEXPRESS',
    [string]$Database = 'it_inventory',
    [int]$KeepDays = 30
)

$ErrorActionPreference = 'Stop'
$appRoot = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$folder = Join-Path $Destination $stamp
$log = Join-Path $Destination 'backup-log.txt'

function Write-Log([string]$message) {
    $line = '{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message
    Write-Output $line
    Add-Content -Path $log -Value $line
}

# Runs T-SQL with Windows authentication and returns the output lines; throws
# if sqlcmd reports an error.
function Invoke-Sql([string]$query) {
    $output = & sqlcmd -S $Server -E -b -h -1 -W -Q "SET NOCOUNT ON; $query" 2>&1
    if ($LASTEXITCODE -ne 0) { throw "sqlcmd failed: $($output -join ' ')" }
    return $output
}

New-Item -ItemType Directory -Force -Path $Destination | Out-Null
if (Test-Path $folder) {
    # another run in the same second; never touch (or clean up) its folder
    Write-Log "FAILED  $stamp  a backup folder with this name already exists"
    exit 1
}
New-Item -ItemType Directory -Path $folder | Out-Null
$staged = $null
try {
    # 1. database. The file is written by the SQL Server service account, which
    #    cannot write into user folders (Desktop, OneDrive) or network shares,
    #    so it goes to SQL Server's own backup folder first, is checked there,
    #    then moved into the dated folder with this user's permissions.
    $sqlFolder = "$(Invoke-Sql "SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS nvarchar(4000))" | Select-Object -First 1)".Trim()
    $staged = Join-Path $sqlFolder "$Database-$stamp-staging.bak"
    Invoke-Sql "BACKUP DATABASE [$Database] TO DISK = N'$staged' WITH INIT, CHECKSUM, NAME = N'$Database $stamp'" | Out-Null
    Invoke-Sql "RESTORE VERIFYONLY FROM DISK = N'$staged' WITH CHECKSUM" | Out-Null
    Move-Item $staged (Join-Path $folder "$Database.bak")
    $staged = $null

    # 2. uploaded purchase order files (the folder exists once a PO is uploaded)
    $po = Join-Path $appRoot 'storage\app\private\po'
    $poNote = 'no PO files yet'
    if ((Test-Path $po) -and (Get-ChildItem $po -Recurse -File | Select-Object -First 1)) {
        Compress-Archive -Path (Join-Path $po '*') -DestinationPath (Join-Path $folder 'po-files.zip')
        $poNote = 'PO files zipped'
    }

    # 3. settings (APP_KEY, database connection)
    Copy-Item (Join-Path $appRoot '.env') (Join-Path $folder 'env.txt')

    $sizeMb = [math]::Round(((Get-ChildItem $folder -File | Measure-Object Length -Sum).Sum / 1MB), 2)
    Write-Log "OK      $stamp  ($sizeMb MB, $poNote)"
}
catch {
    Write-Log "FAILED  $stamp  $($_.Exception.Message)"
    # don't leave a half-made backup that looks complete
    if ($staged -and (Test-Path $staged)) { Remove-Item $staged -Force -ErrorAction SilentlyContinue }
    Remove-Item $folder -Recurse -Force -ErrorAction SilentlyContinue
    exit 1
}

# 4. prune: only dated backup folders made by this script
$cutoff = (Get-Date).AddDays(-$KeepDays)
Get-ChildItem $Destination -Directory |
    Where-Object { $_.Name -match '^\d{4}-\d{2}-\d{2}_\d{4}(\d{2})?$' -and $_.CreationTime -lt $cutoff } |
    ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force
        Write-Log "PRUNED  $($_.Name) (older than $KeepDays days)"
    }
exit 0
