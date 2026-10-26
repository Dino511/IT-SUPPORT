# IT Inventory

IT support device inventory built with Laravel 13 and Microsoft SQL Server.

## Roles

| | Super Admin | Admin |
|---|---|---|
| Log and edit devices and employees | ✓ | ✓ |
| Assign, return and change device status | ✓ | ✓ |
| File requests for employees (new device, repair, damage, lost, return) | ✓ | ✓ |
| Approve, reject or complete requests | ✓ any request | ✓ except requests for their own devices |
| Audit logs and CSV export | ✓ | ✓ |
| Master data (companies, dropdown lists, device types and asset-tag prefixes) | ✓ | - |
| Add a new dropdown option while filling in a form | ✓ | - (pick from the list only) |
| User accounts | ✓ | - |

There is no employee login. Employees are records (employee ID, department, company, contact, email) that devices are assigned to, and admins file requests on their behalf.

## Features

- **Multiple companies:** for a group with several companies, possibly in different countries. Each company has a code, name, country and address. Every employee and device belongs to one company. The **Viewing company** switcher in the sidebar narrows the dashboard, lists and audit log to one company, or shows all of them with a per-company summary. A device assigned to an employee of another company is shown and logged as a loan.
- **Devices:** owning company, type, brand, model (filtered by brand), serial/IMEI, processor, memory, storage, OS, color, supplier, warranty, purchase details, plus any number of extra specs. The asset tag is generated automatically from the type's prefix (LT-0001, MB-0001...).
- **Purchase order (PO):** upload the PO document (PDF, image, Word or Excel, up to 10 MB) in the device's Purchase section. It opens from the device page, can be replaced or removed, and every upload and removal is audited. Files are stored privately in `storage/app/private/po` and are only served to signed-in users.
- **Master data:** the Super Admin maintains every dropdown list. Renaming an option also renames it on every record that uses it. Options in use can be deactivated but not deleted.
- **History:** per device, who held it, when, condition out and in, and every related request. Per employee, devices held, device history and requests.
- **Requests:** pending → approved → completed, or rejected/cancelled. Approving a repair, damage or lost report updates the device immediately. Completing applies the outcome (return to the employee, back to stock, retire, assign a new or replacement unit).
- **Audit log:** every action, filterable by action, user, date range and asset tag, serial or employee ID, and exportable to CSV.

## Setup

Requirements: PHP 8.2+ with the `sqlsrv` and `pdo_sqlsrv` extensions, Composer, and SQL Server (Express works).

1. Create the database: `CREATE DATABASE it_inventory;`
2. Set the connection in `.env`:
   ```
   DB_CONNECTION=sqlsrv
   DB_HOST='localhost\SQLEXPRESS'   # single quotes, because of the backslash
   DB_PORT=
   DB_DATABASE=it_inventory
   DB_USERNAME=                     # blank = Windows authentication
   DB_PASSWORD=
   ```
3. Install and migrate:
   ```
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```
4. Open http://localhost:8000

Demo logins (password `password`, change them before going live):

| Role | Email |
|---|---|
| Super Admin | superadmin@company.test |
| Admin | admin@company.test |

## Documentation

- **Developer guide:** [docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md) covers setup, architecture, the data model, business rules, how-to recipes, SQL Server notes and deployment.
- **Deployment:** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), step by step installation on a new server PC.
- **User guide (slides):** `docs/IT-Inventory-User-Guide.pptx`, a step-by-step guide for Super Admin and Admin users.
- **Backups:** `scripts/backup.ps1` backs up the database, PO files and settings; see "Backups" in the developer guide for scheduling and restoring.
- **Office network:** `scripts/start-server.ps1` / `stop-server.ps1` run the app for other PCs on port 8000; see "Running on the office network" in the developer guide.

## Notes

- Records are never hard-deleted. Devices are retired and employees deactivated, so the audit trail stays complete.
- Device lifecycle logic is in `app/Support/Inventory.php`. Dropdown validation and saving are in `app/Support/Lookups.php`. Asset tag numbering is in `DeviceType::generateAssetTag()`.
- The UI uses Bootstrap 5, Bootstrap Icons and Tom Select, with local copies in `public/vendor` (fonts included), so there is no npm build step and the app works without internet. The look is defined in `public/css/theme.css`: colour tokens for light and dark (the theme follows the system setting, and the moon/sun button in the sidebar switches it), the Plus Jakarta Sans and JetBrains Mono fonts, and component styles. Change the colours there, not in the views.
