# WattWatch

IoT-based electricity consumption monitoring & anomaly detection system.
ESP32 + PZEM-004T → Wi-Fi → PHP/MySQL web app → dashboard.

File system follows the same pattern as the BioLock project: flat root
pages, a small `ajax/` folder for session-authenticated UI calls, a
separate `api/` folder for device-facing endpoints, one `config/db.php`,
and `sql/` for the schema.

## Folder structure

```
wattwatch/
├── config/
│   └── db.php          # constants + PDO connection ($pdo), in one file
├── includes/            # shared code, required by every page
│   ├── auth.php         # session bootstrap, require_login(), RBAC (can()/require_permission())
│   ├── functions.php    # e(), log_activity(), csrf_field()/verify_csrf(), formatters
│   ├── header.php       # <head> + topbar (opens the layout)
│   ├── sidebar.php      # left nav, permission-aware
│   └── footer.php       # closes the layout
├── ajax/                 # session-authenticated calls from the UI
│   └── get-live-data.php # polled every 10s to refresh dashboard stat cards
├── api/                  # device-facing endpoints (per-device API key, not a session)
│   └── sensor-data.php   # POST target for ESP32 firmware
├── assets/
│   ├── css/style.css
│   ├── js/script.js
│   └── img/
├── sql/
│   ├── wattwatch.sql             # schema + seed data
│   └── least_privilege_user.sql  # dedicated MySQL app account
├── index.php             # redirects to login.php
├── login.php
├── logout.php
├── dashboard.php
├── rooms.php              # rooms + equipment CRUD
├── monitoring.php         # per-equipment live chart
├── anomalies.php
├── thresholds.php
├── users.php              # account + role management
├── logs.php
├── reports.php            # date-range summaries + CSV export
├── settings.php
└── 403.php
```

Every page starts the same way:
```php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('some_permission');   // or require_login();
$active_page = 'dashboard';               // highlights the sidebar entry
```
From `ajax/` or `api/`, the same three requires use `'../config/db.php'`, etc.

## Setup (XAMPP)

1. Copy this `wattwatch/` folder into `htdocs/`.
2. In phpMyAdmin (or the mysql CLI), run `sql/wattwatch.sql`, then
   `sql/least_privilege_user.sql`.
3. Edit `config/db.php` — set `DB_PASS` to match the password you used
   in `least_privilege_user.sql`.
4. Visit `http://localhost/wattwatch/` and log in with the seed admin
   account: **admin / admin123** — change this password immediately
   under Settings.

## Database design

Normalized to 3NF — no repeating groups, every non-key column depends
only on its table's primary key:

- **roles / permissions / role_permissions** — many-to-many junction so
  privileges can be regranted by editing rows, no code changes needed.
- **users** references `roles` (one role per user).
- **rooms → equipment → readings** — one-to-many chain; `readings` is
  kept separate from `equipment` because it's high-volume time-series
  data, not equipment metadata.
- **thresholds** is one-to-one with `equipment`, carrying its own
  `updated_by`/`updated_at` audit trail.
- **anomalies** references both `equipment` and the specific `reading`
  that triggered it.
- **devices** — one row per physical ESP32 unit, each with its own
  `api_key` (see Security below).
- **activity_logs** is an append-only audit trail referencing `users`.

## User privilege levels (RBAC)

Seeded roles and what they can do (see `role_permissions` in the SQL,
enforced via `includes/auth.php`'s `can()` / `require_permission()`):

| Permission | Administrator | Technician | Viewer |
|---|---|---|---|
| View dashboard / monitoring | ✅ | ✅ | ✅ |
| Manage rooms & equipment | ✅ | ✅ | — |
| Manage thresholds | ✅ | ✅ | — |
| Resolve anomalies | ✅ | ✅ | — |
| View / export reports | ✅ | ✅ | ✅ |
| Manage users | ✅ | — | — |
| View logs | ✅ | — | — |
| Manage settings | ✅ | — | — |

To add a role or change what one can do, edit the `roles` /
`role_permissions` tables — the sidebar and every page follow the new
privileges automatically since they check `can('permission_key')`
rather than hardcoding role names.

## Security notes

- Passwords hashed with bcrypt (`password_hash`/`password_verify`).
- All queries use PDO prepared statements (no string-built SQL).
- CSRF token required on every state-changing form (`csrf_field()` / `verify_csrf()`).
- Session-based auth for the web UI; each ESP32 authenticates with its
  own key from the `devices` table — revoke one device without
  affecting the others.
- Idle session timeout (30 min, `SESSION_TIMEOUT` in `config/db.php`).
- Basic login throttling (5 attempts / 60s).
- Every create/update/delete/login/export writes to `activity_logs`.
- The app connects as `wattwatch_app`, a MySQL user with only
  SELECT/INSERT/UPDATE/DELETE — never as root.

## ESP32 firmware contract

`POST /api/sensor-data.php`
Header: `X-API-KEY: <this device's key from the devices table>`
Body:
```json
{ "device_uid": "ESP32-R204-AC01", "voltage": 230.1, "current": 21.78, "power": 5012.0, "energy": 3.42 }
```
`device_uid` must already exist in the `equipment` table (add it under
Rooms/Equipment first), and the API key must match a row in `devices`.
The endpoint stores the reading and — if a threshold row exists for
that equipment — flags an anomaly the moment `power` falls outside
`[min_power, max_power]`. Wire the buzzer/LED trigger into the firmware
based on the `"anomaly": true` field in the response.
