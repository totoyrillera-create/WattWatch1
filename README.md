# WattWatch — IoT Electricity Monitoring System


## Project Structure

```
WattWatch/
├── .htaccess                   ← blocks /config /src /private from web
├── config/
│   ├── app.php                 ← app constants, roles, permissions
│   ├── database.php            ← PDO singleton, DB credentials
│   └── schema.sql              ← normalized MySQL schema (run once)
├── src/
│   ├── Auth.php                ← session, login, logout, permission checks
│   └── ApiController.php       ← all AJAX endpoints (REST-style JSON API)
├── public/                     ← web root (point Apache/Nginx here)
│   ├── .htaccess
│   ├── index.php               ← PHP entry; injects session user → JS
│   ├── index.html              ← static fallback (no PHP)
│   └── assets/
│       ├── css/
│       │   └── style.css       ← complete stylesheet
│       ├── js/
│       │   └── app.js          ← SPA: routing, API calls, all page renders
│       └── img/
│           └── favicon.svg
└── private/
    └── login.php               ← server-rendered login (fallback / SSR)
```

---

## Setup

### 1. Requirements
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Apache with `mod_rewrite` enabled (or Nginx equivalent)

### 2. Database
```sql
-- Run schema.sql in MySQL:
    mysql -u root -p < config/schema.sql
```
This creates the `wattwatch_db` database with all normalized tables and seeds demo users.

### 3. Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'wattwatch_db');
```

### 4. Web Server
Point your Apache `DocumentRoot` (or Virtual Host) to the **`public/`** folder:
```apache
<VirtualHost *:80>
    ServerName wattwatch.local
    DocumentRoot /var/www/html/WattWatch/public
    <Directory /var/www/html/WattWatch/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Enable `mod_rewrite`: `sudo a2enmod rewrite && sudo systemctl restart apache2`

### 5. XAMPP (local dev)
Place the `WattWatch/` folder inside `htdocs/`.  
Visit: `http://localhost/WattWatch/public/`

---

## Demo Accounts

| Role               | Email                   | Password  | Access                                              |
|--------------------|-------------------------|-----------|-----------------------------------------------------|
| Administrator      | admin@wattwatch.com     | admin123  | Full access (all pages)                             |
| Facility Manager   | juan@wattwatch.com      | juan123   | Dashboard, Monitoring, Anomalies, Reports, Profile  |
| Technician         | maria@wattwatch.com     | maria123  | Dashboard, Monitoring, Anomalies, Profile           |
| Viewer             | carlos@wattwatch.com    | carlos123 | Dashboard, Profile                                  |

> **Change all passwords immediately after first login in production.**

---

## Database Schema (Normalized — 3NF)

| Table              | Description                                        |
|--------------------|----------------------------------------------------|
| `roles`            | Role lookup (admin, facility_manager, …)           |
| `users`            | System users — references `roles`                  |
| `buildings`        | Location master — eliminates string duplication    |
| `equipment_types`  | AC, Lights, Projector, HVAC … lookup               |
| `rooms`            | Monitored rooms/devices — refs buildings + types   |
| `readings`         | Time-series ESP32 data — refs rooms                |
| `anomaly_types`    | HIGH POWER, VOLTAGE SPIKE … lookup                 |
| `anomalies`        | Detected events — refs rooms, readings, users      |
| `activity_logs`    | Audit trail — refs users                           |
| `system_settings`  | Key-value app configuration                        |

---

## ESP32 Integration

The ESP32 POSTs sensor readings to:
```
POST http://your-server/WattWatch/src/ApiController.php?action=post_reading
Header: X-Api-Token: ESP32_SECRET_TOKEN_CHANGE_ME
Body (JSON): { "room_id": 1, "voltage": 220.5, "current": 12.9, "power": 2842.5, "energy": 42.1 }
```

Change the token in `ApiController.php` → `postReading()` and in your ESP32 firmware.

---

## User Roles & Permissions

```
Administrator      → dashboard, rooms, monitoring, anomalies, reports,
                     thresholds, users, logs, settings
Facility Manager   → dashboard, monitoring, anomalies, reports, profile
Technician         → dashboard, monitoring, anomalies, profile
Viewer             → dashboard, profile
```

---

## Features
- Real-time electricity monitoring (V, A, W, kWh)
- Threshold-based anomaly detection with auto-flagging
- Role-based access control (4 privilege levels)
- Web dashboard: stats, live chart, room cards, anomaly feed
- Rooms & Equipment management (Admin)
- Threshold editor with visual usage bars (Admin)
- User management with role assignment (Admin)
- Reports: daily / weekly / monthly with per-room breakdown
- System logs / audit trail (Admin)
- System settings with toggle alerts (Admin)
- Profile editor + password change (all roles)
- Session-based authentication with bcrypt password hashing
- ESP32 + PZEM-004T API endpoint for hardware integration
