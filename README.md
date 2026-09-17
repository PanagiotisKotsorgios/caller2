# Simple Sales Team CRM — PHP + MySQL

A lightweight CRM for an outbound sales/cold-calling team. It uses plain PHP, Apache and MySQL — no framework, no Composer and no Node.js.

## What is included

- Admin and Caller accounts
- Default commission percentage per caller
- Leads for Website / E-shop / Other
- Pipeline statuses: New, No Answer, Contacted, Demo Sent, Follow Up, Interested, Won, Lost, Not Interested
- Colored lead rows: green for Won, red for Lost/Not Interested, yellow for Demo Sent, blue for Interested
- Demo-sent tracking
- Follow-up dates and notes
- Estimated and final sale values
- Per-sale commission percentage snapshot and admin override
- Admin dashboard with caller statistics
- Caller dashboard limited to that caller's assigned leads
- Monthly commission report and CSV export
- Filtered lead CSV export
- Password hashing, sessions, role checks and CSRF protection
- Persistent MySQL storage
- Automatic database/table creation
- Docker Compose stack designed for Coolify

## Coolify: zero database setup / zero manual environment variables

The repository contains a complete `docker-compose.yml` with **two containers**:

1. `app` — PHP 8.3 + Apache CRM
2. `mysql` — MySQL 8.4

You do **not** need to create a separate MySQL resource in Coolify and you do **not** need to type database environment variables yourself.

The Compose file uses Coolify generated stack variables:

- `SERVICE_PASSWORD_64_MYSQL`
- `SERVICE_PASSWORD_64_MYSQLROOT`

Coolify generates and persists these values automatically. The same generated MySQL password is passed to both the application and MySQL containers.

The MySQL data is stored in the persistent `mysql_data` Docker volume.

### Deploy

1. Unzip this project and push all files to the root of your GitHub repository.
2. In Coolify choose **New Resource → Public/Private Repository** and select your repository.
3. Set **Build Pack = Docker Compose**.
4. Use `docker-compose.yml` as the Compose file. If the project is at repository root, Base Directory is `/`.
5. Click **Deploy**.

That is all that is required for the application and database containers.

The Compose file also declares `SERVICE_URL_APP_80`, so on a Coolify installation with a wildcard domain configured, Coolify can generate a URL and route it to the CRM container's internal port 80. You can instead set your own domain on the `app` service in Coolify whenever you want.

MySQL is **not published to the public server interface**; it is reachable only over the internal Compose network by the PHP application.

## First browser visit

When the stack is running:

1. Open the CRM URL/domain.
2. If no users exist, the application redirects to `/setup.php`.
3. Create the first administrator account.
4. Log in.
5. Go to **Team** and create caller accounts and commission percentages.

No SQL import is required. MySQL creates the `crm` database automatically and the PHP application creates its tables automatically.

## Startup behavior

- MySQL starts first.
- Docker waits for the MySQL health check to pass.
- The PHP/Apache container then starts.
- The CRM connects using the internal hostname `mysql`.
- Missing application tables are created automatically.
- Both containers restart automatically unless intentionally stopped.

## Commission logic

Each caller has a default percentage, for example 10%.

When a lead is changed to **Won**, the caller's current percentage is copied onto that lead. This keeps the commission for that sale unchanged even if the caller's default percentage is changed later.

An administrator may also set a custom commission percentage on an individual lead.

Monthly reports use the sale date:

```text
commission owed = final sale value × commission percentage / 100
```

## Local Docker deployment

Coolify generates the two `SERVICE_PASSWORD_...` variables automatically. If you want to run the same Compose file manually outside Coolify, provide them before running Compose, for example:

```bash
export SERVICE_PASSWORD_64_MYSQL='local-crm-password'
export SERVICE_PASSWORD_64_MYSQLROOT='local-root-password'
docker compose up -d --build
```

For Coolify, these manual exports are not needed.
