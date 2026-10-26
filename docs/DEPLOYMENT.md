# Deploying IT Inventory on a server PC

Step-by-step installation on the Windows PC that will run IT Inventory for the office. Everyone else opens it in a browser at `http://<server-name>`.

There are two ways to run the web part:

| | **IIS** (recommended) | **PHP built-in server** (simpler) |
|---|---|---|
| Runs with nobody signed in | Yes, as a Windows service | Only after a Task Scheduler change (step 8B) |
| Several people at once | Yes | One request at a time (fine for 2–5 IT staff) |
| Setup effort | About an hour | About 15 minutes |
| Address | `http://<server-name>` | `http://<server-name>:8000` |

Everything else (SQL Server, PHP, the app, the database, backups) is the same for both.

> **What was rehearsed on the development laptop:** the fresh-install steps (empty database, `migrate`, `MasterDataSeeder`, first login, every page, adding a company), `php artisan optimize`, the backup/restore script and `register-tasks.ps1` in its default "while signed in" mode.
> **Not tested there:** the IIS steps (the laptop has no IIS) and running the tasks "whether user is logged on or not". Follow those carefully and use the Troubleshooting table at the end.

Run every command in **PowerShell as administrator** (Start → type PowerShell → right-click → *Run as administrator*) unless it says otherwise. `<server-name>` means this PC's computer name.

---

## 1. Prepare the server PC

- **Windows:** Windows 10/11 Pro or Windows Server 2019 or later, 64-bit, signed in with an **administrator** account. That account installs everything, owns the database and runs the backups.
- **A name and a fixed address:**
  - Give the PC a short computer name, e.g. `ITINV-SERVER` (Settings → System → About → *Rename this PC*). Users will type this name.
  - Ask whoever manages the router to reserve its IP address (a "DHCP reservation"), so the address never changes.
- **Always on:**
  - Settings → System → Power → *When plugged in, put my device to sleep after*: **Never**.
  - On a laptop, set closing the lid to *Do nothing*.
  - A UPS (battery backup) is worth it.
- **Office network = Private.** Windows only lets other PCs in on networks marked Private (or a company domain):
  ```powershell
  Get-NetConnectionProfile                                    # note the InterfaceAlias, e.g. Ethernet
  Set-NetConnectionProfile -InterfaceAlias 'Ethernet' -NetworkCategory Private
  ```
- **Folder:** the app will live in `C:\ITInventory`. Don't put it on the Desktop or in Documents: IIS and SQL Server can't read folders inside a user's profile.

## 2. Install SQL Server

1. Install **SQL Server 2022 Express** (the *Basic* installation is fine). Keep the instance name `SQLEXPRESS` and Windows authentication. The administrator who installs it gets full rights to it.
   - If you will **move existing data** from the laptop (step 6B), the server must run SQL Server **2022 or newer**: the laptop runs SQL Server 2022, and a backup can't be restored on an older version.
2. Install **Microsoft ODBC Driver 17 for SQL Server** (x64), the same driver the laptop uses.
   - Driver 18 also works, but it encrypts connections by default and then refuses SQL Express's self-signed certificate. You would have to turn on `trust_server_certificate` in `config/database.php`. Driver 17 avoids that.
3. Create the empty database:
   ```powershell
   sqlcmd -S "localhost\SQLEXPRESS" -E -Q "CREATE DATABASE it_inventory"
   ```
   If `sqlcmd` is not found, install *Microsoft Command Line Utilities for SQL Server*, or create the database in SQL Server Management Studio.
   - Skip this when moving data (6B creates it by restoring).

## 3. Install PHP 8.5

1. Install the **Microsoft Visual C++ 2015–2022 Redistributable (x64)**.
2. Download **PHP 8.5 x64 Non Thread Safe (NTS)** from windows.php.net and unzip it to `C:\PHP`.
   - NTS is the build for IIS; the built-in server runs it fine too.
   - The laptop has the Thread Safe build, so don't copy its `C:\PHP` folder over.
3. Download **Microsoft Drivers for PHP for SQL Server 5.13**, the release the laptop uses.
   - Copy `php_sqlsrv_85_nts_x64.dll` and `php_pdo_sqlsrv_85_nts_x64.dll` into `C:\PHP\ext`.
   - Use the **nts** files; the laptop's are the `_ts_` ones.
4. Create `php.ini`: copy `C:\PHP\php.ini-production` to `C:\PHP\php.ini` and set or uncomment these lines:
   ```ini
   extension_dir = "ext"
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=zip
   extension=php_sqlsrv_85_nts_x64.dll
   extension=php_pdo_sqlsrv_85_nts_x64.dll

   upload_max_filesize = 50M
   post_max_size = 55M
   max_execution_time = 120
   memory_limit = 128M
   ```
   OPcache is built into PHP 8.5 and on by default for the web server; nothing to add.
5. Put PHP on the system PATH, then **open a new PowerShell window** and check:
   ```powershell
   $p = [Environment]::GetEnvironmentVariable('Path', 'Machine')
   [Environment]::SetEnvironmentVariable('Path', "$p;C:\PHP", 'Machine')
   ```
   ```powershell
   php -v                                                      # PHP 8.5.x (cli) ... NTS
   php -m | Select-String 'sqlsrv|mbstring|openssl|fileinfo|curl|zip|OPcache'
   ```
   All of `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip` and `Zend OPcache` must be listed.

## 4. Get the app

1. Install **Git for Windows** and **Composer** (Composer-Setup.exe; point it at `C:\PHP\php.exe`).
2. Download the code and its PHP libraries (needs internet this once):
   ```powershell
   git clone https://github.com/Dino511/IT-SUPPORT.git C:\ITInventory
   cd C:\ITInventory
   composer install --no-dev --optimize-autoloader
   ```
   `--no-dev` leaves out the testing tools; the server doesn't need them. Bootstrap, the icons and the fonts are already in `public\vendor`, so the app itself never needs the internet.

## 5. Configure `.env`

```powershell
cd C:\ITInventory
Copy-Item .env.example .env
notepad .env
```

Change these lines (and replace the `DB_CONNECTION=sqlite` line and the commented `# DB_...` lines with the `DB_` block below). Leave the rest as they are:

```dotenv
APP_NAME="IT Inventory"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://ITINV-SERVER            # with the built-in server: http://ITINV-SERVER:8000
APP_TIMEZONE=Asia/Manila

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30

DB_CONNECTION=sqlsrv
DB_HOST='localhost\SQLEXPRESS'         # single quotes: the backslash breaks double quotes
DB_PORT=
DB_DATABASE=it_inventory
DB_USERNAME=                           # empty = Windows authentication
DB_PASSWORD=
```

Then create the app's secret key:

```powershell
php artisan key:generate
```

- **The key:** a new key is fine even when moving data. Nothing in the database is encrypted with it; everyone just signs in again.
- **Keep `.env` private.** It's never uploaded to GitHub, and the backup script copies it into each backup.

## 6. Create the database tables and the first login

### 6A. Fresh start (recommended)

The laptop only holds demo data, so start clean:

```powershell
cd C:\ITInventory
php artisan migrate --force
php artisan db:seed --class=MasterDataSeeder --force
php artisan app:create-super-admin
```

What each command does:
- **`migrate`** creates the tables.
- **`MasterDataSeeder`** adds the starter master data:
  - the 8 device types with their asset tag prefixes (Laptop LT, Desktop DT, Mobile Phone MB, Tablet TB, Monitor MN, Peripheral PR, Gadget GD, Other OT)
  - starter dropdown lists: processors, memory, storage, operating systems, colours, spec names, and 7 brands with their models

  It adds no companies, people, devices or logins, and is safe to run again: it only adds what's missing.
- **`app:create-super-admin`** asks for a name, email and password and creates the first login. Typing is hidden for the password.

Then sign in to the app as that Super Admin and set it up, in this order:
1. **Master Data → Companies:** add your company or companies.
2. **Departments, positions, suppliers:** add them on Master Data, or by typing a new value in a form dropdown, which only the Super Admin can do.
3. **Employees:** add the people.
4. **User Accounts:** create logins for the other IT staff and link each to their employee record.

> ⚠️ **Never run** `php artisan db:seed` **without** `--class=MasterDataSeeder`: that loads the demo companies, people and the demo logins with the password `password`, which are listed publicly on GitHub.
> **Never run** `php artisan migrate:fresh`: it deletes every table and all your data.

### 6B. Or: move the existing data from the laptop

1. **On the laptop**, plug in a USB drive and run:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination E:\IT-Inventory
   ```
   It makes a dated folder with `it_inventory.bak`, `po-files.zip` (if there are PO uploads) and `env.txt`.
2. **On the server**, copy that dated folder to `C:\Backups\IT-Inventory\from-laptop`. Create `C:\Backups` at the root of C:, where SQL Server is allowed to read.
3. Restore it (skip the `CREATE DATABASE` in step 2):
   ```powershell
   $bak = 'C:\Backups\IT-Inventory\from-laptop\it_inventory.bak'
   sqlcmd -S "localhost\SQLEXPRESS" -E -Q "RESTORE FILELISTONLY FROM DISK = N'$bak'"   # shows the file names inside
   sqlcmd -S "localhost\SQLEXPRESS" -E -Q "RESTORE DATABASE [it_inventory] FROM DISK = N'$bak'"
   ```
   If the second command says a file path doesn't exist (the server keeps its databases somewhere else), restore with `MOVE`. Use the names `it_inventory` and `it_inventory_log` shown by `FILELISTONLY`, and the server's own data folder:
   ```powershell
   $data = 'C:\Program Files\Microsoft SQL Server\MSSQL16.SQLEXPRESS\MSSQL\DATA'
   sqlcmd -S "localhost\SQLEXPRESS" -E -Q "RESTORE DATABASE [it_inventory] FROM DISK = N'$bak' WITH MOVE N'it_inventory' TO N'$data\it_inventory.mdf', MOVE N'it_inventory_log' TO N'$data\it_inventory_log.ldf'"
   ```
4. Bring the tables up to date, and put the PO files back:
   ```powershell
   cd C:\ITInventory
   php artisan migrate --force
   Expand-Archive C:\Backups\IT-Inventory\from-laptop\po-files.zip -DestinationPath storage\app\private\po
   ```
5. The laptop's logins come along. **Change or disable the demo accounts** (User Accounts) if they still exist.

## 7. Make it fast

```powershell
php artisan optimize
```

This caches the settings, routes and pages.

> **Re-run `php artisan optimize` after every change to `.env` and after every update.** Until you do, the app keeps using the old cached settings.

## 8A. Web server: IIS (recommended)

**1. Turn on IIS with CGI** (CGI is what lets IIS run PHP).

Windows 10/11:
```powershell
Enable-WindowsOptionalFeature -Online -All -FeatureName IIS-WebServerRole, IIS-WebServer, IIS-CGI, IIS-DefaultDocument, IIS-StaticContent, IIS-HttpErrors, IIS-RequestFiltering, IIS-ManagementConsole
```
Windows Server:
```powershell
Install-WindowsFeature Web-Server, Web-CGI, Web-Mgmt-Console
```

**2. Install URL Rewrite 2.1** (x64) from Microsoft's IIS downloads page. The app's `public\web.config` needs it; without it IIS shows *HTTP Error 500.19*.

**3. Tell IIS how to run PHP:**
```powershell
$appcmd = "$env:windir\system32\inetsrv\appcmd.exe"
& $appcmd set config /section:system.webServer/fastCgi /+"[fullPath='C:\PHP\php-cgi.exe',activityTimeout='120',requestTimeout='120']"
& $appcmd set config /section:system.webServer/fastCgi /+"[fullPath='C:\PHP\php-cgi.exe'].environmentVariables.[name='PHPRC',value='C:\PHP']"
& $appcmd set config /section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\PHP\php-cgi.exe',resourceType='Either']"
```
The two timeouts let long tasks such as the audit-log CSV export finish (120 s, same as `max_execution_time`). `PHPRC` makes IIS use `C:\PHP\php.ini`.

**4. Create the site.** Give it its own app pool, and stop the empty *Default Web Site* so port 80 is free:
```powershell
Import-Module WebAdministration
Stop-Website -Name 'Default Web Site'
Set-ItemProperty 'IIS:\Sites\Default Web Site' -Name serverAutoStart -Value $false
New-WebAppPool -Name ITInventory
Set-ItemProperty IIS:\AppPools\ITInventory -Name managedRuntimeVersion -Value ''      # "No Managed Code"
New-Website -Name 'IT Inventory' -Port 80 -PhysicalPath 'C:\ITInventory\public' -ApplicationPool ITInventory
Start-Website -Name 'IT Inventory'
```
The site must point at the **`public`** folder, not `C:\ITInventory`.

**5. Folder permissions.** The app pool may read the app, and write only to `storage` and `bootstrap\cache`:
```powershell
icacls C:\ITInventory /grant "IIS AppPool\ITInventory:(OI)(CI)RX"
icacls C:\ITInventory\storage /grant "IIS AppPool\ITInventory:(OI)(CI)M"
icacls C:\ITInventory\bootstrap\cache /grant "IIS AppPool\ITInventory:(OI)(CI)M"
```

**6. Database access for the app pool.**

With `DB_USERNAME` empty, IIS connects to SQL Server as the Windows account `IIS APPPOOL\ITInventory`. Give it read/write on the app's database only:
```powershell
sqlcmd -S "localhost\SQLEXPRESS" -E -Q "CREATE LOGIN [IIS APPPOOL\ITInventory] FROM WINDOWS; USE it_inventory; CREATE USER [IIS APPPOOL\ITInventory] FOR LOGIN [IIS APPPOOL\ITInventory]; ALTER ROLE db_datareader ADD MEMBER [IIS APPPOOL\ITInventory]; ALTER ROLE db_datawriter ADD MEMBER [IIS APPPOOL\ITInventory];"
```
Table changes (`php artisan migrate`) and backups run from your administrator PowerShell with your own Windows account, which already has full rights.

**7. Firewall:** allow web traffic from the office network only, never from public networks.
```powershell
New-NetFirewallRule -DisplayName 'IT Inventory (HTTP)' -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private,Domain
Get-NetFirewallRule -DisplayGroup 'World Wide Web Services (HTTP)' -ErrorAction SilentlyContinue | Set-NetFirewallRule -Profile Private,Domain
```

**8. Try it.** Open `http://localhost` on the server, then `http://<server-name>` from another PC.

After any later change to `.env`, run `php artisan optimize` and then `Restart-WebAppPool ITInventory`.

## 8B. Or: web server with PHP's built-in server

The same setup as on the development laptop, using the scripts in `scripts\`:

1. Create the scheduled tasks (run in `C:\ITInventory`):
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts\register-tasks.ps1 -Server -Backup
   ```
   This makes **IT Inventory server** (starts the app on port 8000 at sign-in) and **IT Inventory backup** (daily, 12:30 PM). Both run with no window.
2. So it runs with **nobody signed in**, open *Task Scheduler* → *Task Scheduler Library*:
   - **IT Inventory server:** on the *General* tab choose **Run whether user is logged on or not** (Windows asks for the account password). On the *Triggers* tab, edit the trigger to **At startup**.
   - **IT Inventory backup:** choose **Run whether user is logged on or not** too.
3. Firewall, office network only:
   ```powershell
   New-NetFirewallRule -DisplayName 'IT Inventory (port 8000)' -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Private,Domain
   ```
4. Start it now and try `http://<server-name>:8000` from another PC:
   ```powershell
   Start-ScheduledTask -TaskName 'IT Inventory server'                     # start / restart
   powershell -ExecutionPolicy Bypass -File scripts\stop-server.ps1        # stop
   ```
   Don't run `php artisan serve` on the server: it takes port 8000 and the real server can't start. If that happens, `storage\logs\server.log` says so.

## 9. Backups

The backup script is described in the developer guide (*Backups*).

1. **With IIS**, create only the backup task. Pick a time when the PC is on, and optionally a second drive or network folder:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts\register-tasks.ps1 -Backup -BackupTime 12:30pm -BackupDestination 'D:\Backups\IT-Inventory'
   ```
   Then set it to **Run whether user is logged on or not**, as in 8B step 2.
2. **Run it once now** and check it worked:
   ```powershell
   Start-ScheduledTask -TaskName 'IT Inventory backup'
   Get-Content C:\Backups\IT-Inventory\backup-log.txt -Tail 3      # or your -BackupDestination
   ```
3. **Keep a copy off the server** (second drive, NAS, USB drive swapped weekly). A backup on the same disk doesn't survive a dead disk or a stolen PC. Backups contain all records and the `.env`, so keep them private.
4. **Test a restore** once in a while into a spare database name (see *Backups* in the developer guide), so you know they work.

## 10. First checks

- From another PC, open the address and sign in.
- Log a test device and upload a small PDF as its PO. This checks file permissions and upload limits.
- Assign it to someone and return it. Then check **Audit Logs** shows the steps, and retire the test device.
- The next day, check `backup-log.txt` has a new `OK` line.

## 11. Updating to a newer version later

Back up first (`Start-ScheduledTask -TaskName 'IT Inventory backup'`), then in `C:\ITInventory`:

```powershell
php artisan down                                       # users see the maintenance page
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
Restart-WebAppPool ITInventory                         # IIS. Built-in server: Start-ScheduledTask -TaskName 'IT Inventory server'
php artisan up
```

## 12. Troubleshooting

| What you see | Cause and fix |
|---|---|
| "Something went wrong" page | The details are in `C:\ITInventory\storage\logs\laravel-<date>.log`. The page shows the time; look for that time in the log. |
| `could not find driver` | The SQL Server extensions aren't loaded. Check `php -m` (step 3). Under IIS, check `PHPRC` points at `C:\PHP` (8A step 3). |
| `Login failed for user 'IIS APPPOOL\ITInventory'` | The app pool has no database access: 8A step 6. |
| An SSL / certificate error when connecting to the database | ODBC Driver 18 is installed. Install Driver 17 (step 2). |
| IIS *HTTP Error 500.19* | URL Rewrite isn't installed (8A step 2). |
| The first page works but every other page is 404 | `public\web.config` is missing, or the site points at `C:\ITInventory` instead of `C:\ITInventory\public`. |
| Icons or fonts missing | The site must point at `public`. `web.config` already adds the `.woff2` file type. |
| Uploading a PO fails | IIS allows up to 55 MB (`web.config`). PHP's limits are in `php.ini` (step 3). Restart the app pool after editing `php.ini`. |
| *Permission denied* on `storage\...` | Re-run the `icacls` lines (8A step 5). |
| Changes to `.env` do nothing | Run `php artisan optimize` again; under IIS also `Restart-WebAppPool ITInventory`. |
| Other PCs can't connect | The network isn't *Private* (step 1), or the firewall rule is missing. From another PC run `Test-NetConnection <server-name> -Port 80` (or `-Port 8000`). |
| Built-in server doesn't start | Read `storage\logs\server.log`. Usually something else is using port 8000. |

**Later, optional:** HTTPS. Add a certificate to the IIS site (an `https` binding), then set `APP_URL=https://...` and `SESSION_SECURE_COOKIE=true` in `.env` and run `php artisan optimize`. Until then, passwords cross the office network unencrypted.
