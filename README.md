# WattWatch — IoT Electricity Monitoring System
**IT 313 – System Integration and Architecture**
Isabela State University · College of Computing Studies, ICT

---

## Project Structure

```
WattWatch/
├── .htaccess                    ← blocks /config /src /private from web
├── README.md
├── config/
│   ├── app.php                  ← constants, 2 roles, permissions
│   ├── database.php             ← PDO singleton
│   ├── schema.sql               ← full 3NF schema + seed data (run once)
│   └── reseed_passwords.sql     ← migration/fix script for existing installs
├── src/
│   ├── Auth.php                 ← session, login, bcrypt, role checks
│   └── ApiController.php        ← all JSON API endpoints (ESP32 + frontend)
├── public/                      ← point your web root HERE
│   ├── .htaccess
│   ├── index.php                ← PHP entry (injects session user → JS)
│   ├── index.html               ← static fallback (no PHP required)
│   ├── api.php                  ← public API gateway → src/ApiController.php
│   └── assets/
│       ├── css/style.css        ← single stylesheet (light + dark mode)
│       ├── js/app.js            ← single-page app (all pages, routing, API)
│       └── img/favicon.svg
└── private/
    └── login.php                ← server-rendered login fallback
```

---

## Setup (XAMPP / Local)

### 1. Place files
Put `WattWatch/` inside `htdocs/`.

### 2. Import database
In phpMyAdmin → SQL tab, import `config/schema.sql`
Or via terminal:
```bash
mysql -u root -p < config/schema.sql
```

### 3. Configure database
Edit `config/database.php` with your credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wattwatch_db');
```

### 4. Open in browser
```
http://localhost/WattWatch/public/
```

---

## Demo Accounts

| Role               | Email                   | Password  |
|--------------------|-------------------------|-----------|
| Administrator      | admin@wattwatch.com     | admin123  |
| Staff / Technician | staff@wattwatch.com     | juan123   |

> Change passwords immediately after first login in production.

---

## Existing Install — Migration from 4 roles to 2

If you already have the database from an older version, run:
```sql
source config/reseed_passwords.sql
```
This converts `facility_manager`, `technician`, and `viewer` users to `staff` automatically.

---

## Role Permissions

| Feature             | Administrator | Staff / Technician |
|---------------------|:-------------:|:------------------:|
| Dashboard           | ✓             | ✓                  |
| Rooms / Equipment   | ✓             | —                  |
| Real-time Monitoring| ✓             | ✓                  |
| Anomalies (resolve) | ✓             | ✓                  |
| Analytics           | ✓             | ✓                  |
| Reports             | ✓             | ✓                  |
| Thresholds          | ✓             | —                  |
| User Management     | ✓             | —                  |
| System Logs         | ✓             | —                  |
| Settings            | ✓             | —                  |
| My Profile          | ✓             | ✓                  |

**Administrator** — Full control over user accounts, threshold configurations,
system maintenance, and security audit logs.

**Staff / Technician** — Restricted to real-time energy monitoring, viewing
historical trends/reports, and acknowledging or clearing anomaly alerts.

---

## ESP32 Integration

The ESP32 POSTs sensor data to `api.php`:
```
POST http://your-server/WattWatch/public/api.php?action=post_reading
Header: X-Api-Token: ESP32_SECRET_TOKEN_CHANGE_ME
Body:   {"room_id":1,"voltage":220.5,"current":12.9,"power":2842.5,"energy":0.0426}
```

Change the token in `src/ApiController.php` → `postReading()` and in your ESP32 firmware.

---

## Database Schema (3NF — 10 tables)

| Table              | Purpose                                    |
|--------------------|--------------------------------------------|
| `roles`            | Lookup: admin, staff                       |
| `users`            | Accounts — references roles                |
| `buildings`        | Location master (Building A/B/C)           |
| `equipment_types`  | AC, Lights, Projector, HVAC … lookup       |
| `rooms`            | Monitored locations — refs buildings+types |
| `readings`         | ESP32 time-series data — refs rooms        |
| `anomaly_types`    | HIGH POWER, VOLTAGE SPIKE … lookup         |
| `anomalies`        | Detected events — refs rooms+readings+users|
| `activity_logs`    | Audit trail — refs users                   |
| `system_settings`  | Key-value config (rate, alerts, etc.)      |
