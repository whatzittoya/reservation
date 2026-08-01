# Quinos Reservations (Slim 4 + MySQL)

Internal **staff-only** reservation app for the Quinos POS database. Runs under
XAMPP at `http://localhost/reservation`. Light mode only.

Built on [Slim 4](https://www.slimframework.com/) with plain PDO. It uses your
**existing** POS tables — `tbl_reservation`, `tbl_customers`, `tbl_tables`,
`tbl_employees` — and requires **no schema changes**.

## Features

- **Login via `tbl_employees`** (name/code + PIN). No new accounts to manage.
- **Two roles**
  - *Employee* — create & view reservations, add customers.
  - *Owner* — everything above **plus** edit/delete/cancel any reservation,
    daily reports, per-customer booking counts, and read-only Tables/Staff lists.
  - Role is derived from the employee's name/job title: it's **owner** when that
    text contains `manager`, `owner`, or `admin` (configurable), else *employee*.
- **Table grid** — tables as vertical rows grouped by section, time across the
  top (07:00–21:00, 30-min slots). Click an empty cell to book it.
- **Month calendar** — per-day booking counts; click a day to open its grid.
- **Customer-first booking** — pick an existing customer or add one inline; the
  reservation stores `customer_id` and records who booked it (`reservedBy_id`).
- **Room / Therapist modes** — the grid has a `[Room] [Therapist]` toggle. Room view
  books a **table**; Therapist view books a **therapist** instead. A therapy booking is
  stored on `tbl_reservation.servedBy_id` (no table). Same-therapist/same-time/same-day
  is blocked, just like tables.

### Therapists

Therapists are employees with `jobTitle = 'THERAPIST'` (no PIN → they can't log in; they're
just bookable resources). Seed 6 of them (idempotent) — the block is in `sql/schema.sql`, or:

```sql
INSERT INTO tbl_employees (active, driver, resigned, name, jobTitle, joined)
SELECT * FROM (
  SELECT b'1',b'0',b'0','THERAPIST 01','THERAPIST',CURDATE()
  UNION ALL SELECT b'1',b'0',b'0','THERAPIST 02','THERAPIST',CURDATE()
  /* … 03–06 … */
) t WHERE NOT EXISTS (SELECT 1 FROM tbl_employees e WHERE e.name=t.name AND e.jobTitle='THERAPIST');
```

Rename them to your real therapist names anytime (`UPDATE tbl_employees SET name=… WHERE id=…`).

## Deploy to XAMPP (Windows)

1. **Copy** this folder into `C:\xampp\htdocs\reservation`.
2. Start **Apache** and **MySQL**.
3. **Load your data** into `db_reservation` (once), e.g. in phpMyAdmin import
   your dump, or: `mysql -u root -p db_reservation < db_arna2.sql`.
4. Visit **`http://localhost/reservation`** and sign in.

> `mod_rewrite` is on by default in XAMPP. If sub-pages 404, ensure
> `AllowOverride All` is set for `htdocs` in `httpd.conf`.

## Signing in

Use any employee's **name (or code)** + their **PIN** from `tbl_employees`.
Examples from the sample data:

| Username  | PIN  | Resulting role |
|-----------|------|----------------|
| `Manager` | `789`| owner          |
| `Admin`* / `Ayu Ratna` | `999` | owner |
| `Cashier` | `123`| employee       |
| `Server`  | `456`| employee       |

Owner detection keywords live in `config/config.php` → `owner_title_keywords`.

## Configuration (`config/config.php`)

```
Name: app_name  "Quinos Reservations"  (first word plain, rest in accent colour)
DB:   host 127.0.0.1  db db_reservation  user root  pass (from DB_PASS)
Grid: 07:00 – 21:00, 30-minute slots
```

Rename the app by setting `app_name` — it drives the header, login page, search
page, and browser titles. `APP_NAME` works as an env var too.

All DB values can be overridden with env vars (`DB_HOST`, `DB_NAME`, `DB_USER`,
`DB_PASS`, …) without editing code. **No password is committed.** Put machine
-specific values in **`config/local.php`** (gitignored) — it's merged over the
defaults, so only the keys you list change:

```php
<?php return ['db' => ['pass' => 'your-password']];
```

## Local development

```bash
composer install
php -S 127.0.0.1:8099 index.php   # http://127.0.0.1:8099
```

## Routes

| Method | Path | Role | Purpose |
|---|---|---|---|
| GET/POST | `/login`, `/logout` | any | Session auth |
| GET | `/search?q=&scope=today\|all` | staff | Landing page: search box + results |
| GET | `/grid?date=&view=room\|therapist&section=` | staff | Table/Therapist × time board |
| GET | `/calendar?month=` | staff | Month overview |
| GET/POST | `/reservations/new`, `/reservations` | staff | Create booking |
| GET | `/reservations/{id}` | staff | Booking detail |
| GET/POST | `/reservations/{id}/edit`, `/reservations/{id}` | **owner** | Edit |
| POST | `/reservations/{id}/cancel`, `/{id}/delete` | **owner** | Cancel / delete |
| GET | `/customers`, `/customers/{id}` | staff | Customers (+counts for owner) |
| GET/POST | `/customers/new`, `/customers` | staff | Add customer |
| GET | `/reports`, `/tables`, `/employees` | **owner** | Reports & master data |

## Project layout

```
reservation/
├── index.php                 # front controller (auto base-path, sessions, DI)
├── .htaccess                 # Apache rewrite + source protection
├── config/config.php         # DB, grid, role config
├── src/
│   ├── Database.php           # PDO connection
│   ├── Auth.php               # session auth + role
│   ├── AuthMiddleware.php     # login guard
│   ├── RoleMiddleware.php     # owner-only guard
│   ├── EmployeeRepository.php # login lookup
│   ├── CustomerRepository.php # tbl_customers
│   ├── TableRepository.php    # tbl_tables (grouped by section)
│   ├── ReservationRepository.php # tbl_reservation
│   ├── TimeGrid.php           # time-column model
│   └── routes.php             # all routes
├── templates/                # login, grid, calendar, forms, owner pages
├── sql/schema.sql            # NOTES only — no schema changes needed
└── vendor/                   # Composer deps (bundled for XAMPP)
```

## Notes / status codes

`tbl_reservation.status`: `0` Booked · `1` Arrived · `2` Cancelled.
A reservation is point-in-time (single `reservationTime`); it appears in the
30-minute grid cell that contains its time.
