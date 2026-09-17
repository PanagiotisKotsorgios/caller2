# Sales Team CRM — PHP + MySQL

A deliberately simple CRM for a cold-calling team. No Laravel, Node, React, Composer or external PHP packages.

## Main features

- Admin and Caller roles
- **Only Admin controls caller commission % and standard selling price**
- Admin creates/imports leads and assigns them to callers
- Callers can see **only leads assigned to their own account**
- Callers cannot assign leads to themselves or see another caller's leads
- Website / E-shop / Other services
- Pipeline states: New, No Answer, Contacted, Demo Sent, Follow Up, Interested, Won, Lost, Not Interested
- Green rows for Won, red for Lost/Not Interested, yellow for Demo Sent, blue for Interested
- Follow-up date, demo tracking and notes
- Final sale value and monthly commission calculation
- When a lead is Won, the caller commission rate is snapshotted onto that sale
- If a Won lead has no final sale price, the caller's admin-defined standard price is used automatically
- Admin dashboard with per-caller leads, sales, revenue, standard price and commission
- Monthly payout report + CSV export
- Lead CSV export

## XLSX lead importing

Admin has an **Import XLSX** page. It is designed to directly accept sheets such as `Mix_Kladoi_Auto_Ydraulika_2000.xlsx` with columns like:

- Α/Α
- Όνομα Επιχείρησης
- Διεύθυνση
- Περιοχή / Πόλη
- Νομός / Π.Ε.
- Τηλέφωνο
- Κατηγορία Κλάδου
- Υποκατηγορία
- Πηγή / Σχόλια
- Κατάσταση

The importer automatically finds the worksheet containing `Όνομα Επιχείρησης`, so a workbook may also contain a summary sheet before the data sheet.

During import Admin can:

- leave all imported leads Unassigned, or assign the whole import to one caller
- choose Website / E-shop / Other as the default service
- skip duplicates based on business + phone + city, or import everything

After import, the Leads page has checkboxes. Admin can select specific leads and bulk:

- assign them to a caller
- unassign them
- change status
- change service

This makes it easy to import thousands of leads first, then distribute selected groups among callers. Callers only see their assigned subset.

## Coolify deployment

The repository includes a full `docker-compose.yml` with:

1. `app` — PHP 8.3 + Apache
2. `mysql` — MySQL 8.4

The PHP image includes the ZIP/XML support needed for XLSX imports.

No separate MySQL resource is needed. Coolify-generated stack secrets are used automatically:

- `SERVICE_PASSWORD_64_MYSQL`
- `SERVICE_PASSWORD_64_MYSQLROOT`

### Deploy / update

1. Replace your GitHub repository files with this project.
2. Keep the resource as **Docker Compose** in Coolify.
3. Click **Redeploy**.

On an existing installation, the application automatically adds the new database columns (`standard_price`, address, region, category, subcategory and source comments). Existing users, leads and MySQL data stay in the persistent `mysql_data` volume.

Do **not** delete the MySQL volume when updating.

## First deployment only

Open the CRM URL. If there are no users, `/setup.php` creates the first administrator account. Then use **Team** to create caller accounts and set each caller's commission percentage and standard price.

## Commission behavior

Example caller settings:

- Commission: 15%
- Standard price: €400

If a lead is sold for €500, commission = €75.

If that lead is marked Won without a final sale value, €400 is used as the final value and commission = €60.

The commission percentage is snapshotted when the sale becomes Won, so changing the caller's default percentage later does not rewrite old completed sales.
