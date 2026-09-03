# Quinos Reservations (Slim 4 + MySQL)

Internal **staff-only** reservation app for the Quinos POS database. Runs under
XAMPP at `http://localhost/reservation`. Light mode only.

Built on [Slim 4](https://www.slimframework.com/) with plain PDO. It uses your
**existing** POS tables — `tbl_reservation`, `tbl_customers`, `tbl_tables`,
`tbl_sections`, `tbl_employees` — and changes none of them. The one table it
adds, `tbl_customer_cloud_sync`, is purely additive and only needed for cloud
sync.

> `tbl_sections` must exist: `tbl_tables.section_id` has a foreign key to it, so
> without it MySQL refuses every table insert with error 1452. A partial POS
> dump that omits it will let you browse and rename tables but not add them.

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
- **Resto or spa** — set `type` in `config/config.php`. In *spa* mode every booking
  takes a room **and** a therapist, and both are locked for that slot: if room 101
  and therapist A are booked at 07:00, nobody else gets either one at 07:00. The
  therapist is stored on `tbl_reservation.servedBy_id`, the room on `tableName`.

- **Search on every list** — Customers, Tables/Rooms and Staff each filter as
  you type, client-side over the rows already on the page.
- **Cloud sync** — new and edited customers are pushed to Quinos Cloud, with a
  manual **Sync** button for everyone else. See [Cloud sync](#cloud-sync).
- **Multi-slot bookings** — a booking can run for one block or several (two
  hours, three hours, …). Conflict checks are interval-based, so a 14:00–17:00
  booking blocks a new 15:00 one on the same table or therapist.
- **Manage staff** (owner) — add, edit and retire staff; set their PIN, job
  title (which decides both their role and whether they're a bookable
  therapist), and active/resigned flags.
- **Manage tables/rooms** (owner) — add, rename, re-capacity, move between
  sections, delete. Renaming carries existing bookings across; deleting is
  refused while a table still has upcoming bookings. Section names come from
  `tbl_sections`.

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
Type: type      'resto' | 'spa'
DB:   host 127.0.0.1  db db_reservation  user root  pass (from DB_PASS)
Grid: 07:00 – 21:00, 30-minute slots
```

Everything you're likely to change lives in **`config/local.php`** — app type
(`resto`/`spa`), name, opening hours, DB and cloud credentials. Copy
`config/local.example.php` to start. It is read on every request, so a change
takes effect on the next page load.

Rename the app by setting `app_name` — it drives the header, login page, search
page, and browser titles. `APP_NAME` works as an env var too.

**`type` decides what a booking occupies** (`APP_TYPE` env var also works):

| | `resto` | `spa` |
|---|---|---|
| A booking takes | a table | a **room and a therapist** |
| Locked for the slot | that table | **both** the room and the therapist |
| Grid rows | tables by section | rooms by section, then a `Therapists` group |
| Booking form | customer + table | customer + room + therapist (all required) |

In spa mode a booking is drawn in **two** cells — its room and its therapist —
so both locks are visible on one board. Any row in `tbl_tables` is bookable as a
room; therapists are employees with `jobTitle = 'THERAPIST'`.

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
| GET | `/grid?date=&section=` | staff | Resource × time board |
| GET | `/calendar?month=` | staff | Month overview |
| GET/POST | `/reservations/new`, `/reservations` | staff | Create booking |
| GET | `/reservations/{id}` | staff | Booking detail |
| GET/POST | `/reservations/{id}/edit`, `/reservations/{id}` | **owner** | Edit |
| POST | `/reservations/{id}/cancel`, `/{id}/delete` | **owner** | Cancel / delete |
| GET | `/customers`, `/customers/{id}` | staff | Customers (+counts for owner) |
| GET/POST | `/customers/new`, `/customers` | staff | Add customer |
| GET/POST | `/customers/{id}/edit`, `/customers/{id}` | staff | Edit customer |
| POST | `/customers/{id}/sync` | staff | Push one customer to Quinos Cloud |
| GET | `/reports` | **owner** | Daily reports |
| GET | `/employees` | **owner** | Staff list |
| GET/POST | `/employees/new`, `/employees` | **owner** | Add staff |
| GET/POST | `/employees/{id}/edit`, `/employees/{id}` | **owner** | Edit staff |
| POST | `/employees/{id}/delete` | **owner** | Delete staff |
| GET | `/tables` | **owner** | Manage tables/rooms |
| GET/POST | `/tables/new`, `/tables` | **owner** | Add table |
| GET/POST | `/tables/{id}/edit`, `/tables/{id}` | **owner** | Edit table |
| POST | `/tables/{id}/delete` | **owner** | Delete table |

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
│   ├── CustomerSync.php       # decides create vs update, records the outcome
│   ├── CustomerSyncRepository.php # tbl_customer_cloud_sync
│   ├── CloudClient.php        # Quinos Cloud HTTP (ext-curl)
│   ├── Flash.php              # one-shot message across a redirect
│   ├── TableRepository.php    # tbl_tables + tbl_sections (CRUD, grouping)
│   ├── ReservationRepository.php # tbl_reservation
│   ├── TimeGrid.php           # time-column model
│   └── routes.php             # all routes
├── templates/                # login, grid, calendar, forms, owner pages
├── sql/schema.sql            # notes + the cloud-sync table (run once)
└── vendor/                   # Composer deps (bundled for XAMPP)
```




## Managing staff

`/employees` (owner only), with a search box over name, code and job title.
Sign-in is **name or code** plus **PIN**, straight against the POS
`tbl_employees` table — there are no separate app accounts.

Job title does double duty, and both checks are substring matches:

| Job title contains | Effect |
|---|---|
| `manager`, `owner`, `admin` | full access (edit/delete bookings, reports, staff, tables) |
| `therapist` | bookable on the grid in spa mode |

The role is read from **name + job title**, matching how login computes it —
the generic POS accounts (`Manager`, `QUINOS`) carry the label in `name` with
no job title at all.

- **PIN** — 4–10 digits, or blank for someone who should not sign in (therapists
  usually don't need to). On the edit form, blank means *keep the current PIN*.
- **Active / Resigned** — either one off blocks sign-in and removes the person
  from the bookable therapist list. This is how to retire someone: most POS
  tables (attendances, shifts, payments) hold a foreign key to `tbl_employees`,
  so the database refuses to delete anyone with history. Delete is offered, and
  when the database rejects it the app says so and points at Resigned instead.
- Deleting the account you are signed in with is refused, as is deleting a
  therapist who still has upcoming bookings.

Names and codes must be unique across both columns, because login matches
`name = :u OR code = :u` — a duplicate would make it ambiguous which account
signs in.

## Managing tables / rooms

`/tables` (owner only) lists every bookable resource grouped by its POS section,
with how many bookings each one has. A search box filters the list as you type,
hiding section cards that end up empty.

- **Add** — name, capacity, section. The section dropdown ends in
  **+ New section…**, which reveals a name field and creates the section
  (in `tbl_sections`) as part of saving the table; duplicate names are refused.
  New rows get the standard 50×50 floor-plan square parked at the bottom of
  their section; drag them into place in the POS
  if the physical layout matters, since nothing here reads `x`/`y`.
- **Edit** — rename, change capacity, move between sections. **A rename also
  rewrites `tbl_reservation.tableName`**, because a booking stores the table's
  name rather than its id — without that every existing booking for the table
  would disappear from the grid. Both writes share one transaction.
- **Delete** — refused while the table still has upcoming bookings; move or
  cancel those first. Past bookings keep the name as plain text, so history
  stays readable after the table is gone.

Names must be unique: the grid keys its rows by name, so duplicates would
collapse into one row.

## Cloud sync

New and edited customers are pushed to the Quinos Cloud Customer API
(`https://quinosbackend.com/api/v1`). Everything else stays put.

**What triggers a push**

| Action | Sends |
|---|---|
| Add a customer (Customers page) | create |
| Add a customer inline while booking | create |
| Edit a customer | update — or a create, if it has never been up there |
| Pick an existing customer for a booking | nothing |
| **Sync** button on a customer | create or update, as needed |

Customers that were already in the database when this feature arrived are left
alone. The **Sync** button on a customer's page is how they get pushed, and how
you retry anything that failed. The Customers list shows *Synced*, *Failed* or
*Not synced* for every row; hover a badge for the code, timestamp or error.

**Turning sync off**

`cloud.enabled` in `config/local.php` is the master switch:

```php
'cloud' => [
    'enabled' => false,   // everything stays on this server
    ...
],
```

`false` blocks every outbound call regardless of credentials — no automatic
push on create or edit, and the manual **Sync** button disappears (the route
refuses too, so it can't be reached by URL). The Customers page says why. Handy
on a development machine: the API has no delete endpoint, so a stray test
customer pushed to the cloud cannot be removed. `CLOUD_SYNC=0` does the same as
an env var.

**Setup — both credentials are required**

The API rejects a request that is missing either header, with two different
messages: `Unauthorized` for the API-KEY, `Unauthorized token` for the TOKEN.
The API-KEY is the *application* key ("must match server configuration") and is
not derived from your company token — ask whoever runs the backend for it.

1. Create the tracking table once:
   ```bash
   mysql -u root -p db_reservation < sql/schema.sql
   ```
2. Put both credentials in `config/local.php` (gitignored):
   ```php
   <?php return [
       'db'    => ['pass' => 'your-password'],
       'cloud' => ['api_key' => 'the-application-key', 'token' => 'your-company-token'],
   ];
   ```
   `CLOUD_API_KEY`, `CLOUD_TOKEN`, `CLOUD_BASE_URL`, `CLOUD_CODE_PREFIX` and
   `CLOUD_TIMEOUT` work as env vars too.

Until both are set, sync reports itself as off on the Customers page and nothing
is sent — the app behaves exactly as it did before.

**How customers are matched**

The cloud needs a `code` that is unique per company. It is derived from the local
customer id: `RSV` + the zero-padded id, so customer 42 is always `RSV000042`.
That makes a push repeatable — a retry updates the same cloud record instead of
creating a second one, and a create that comes back *"The code has already been
taken."* is automatically retried as an update. Set `code_prefix` per outlet if
several installs share one cloud company.

**What gets sent**

`name` and `code` always; `phone` (digits only), `email`, `gender`, `dob`
(from `birthday`), `address` and `expired` when they're present and pass the
API's limits. An optional field that doesn't fit — an address over 128
characters, say — is simply left out rather than blocking the sync. `notes` has
no counterpart in the API and never leaves this server.

A name shorter than 3 characters is the one thing that fails outright, because
the API requires it; fix the name and press **Sync**.

**When the cloud is unreachable**

The local save always wins. A booking or a customer is never lost to a slow or
offline connection — the request gives up after `CLOUD_TIMEOUT` seconds (8 by
default), the customer is marked *Failed* with the reason, and the **Sync**
button retries it later.

Wallet top-up and charge (`/wallet/topup`, `/wallet/charge`) are part of the same
API but are **not** used by this app.



## Booking length

A booking picks a **Duration** in grid blocks — one block by default, up to
however many remain before closing. It is stored as
`tbl_reservation.duration_minutes`; **NULL means one block**, so every booking
made before this feature keeps working untouched, and the POS (which never
selects the column) is unaffected.

The grid draws a long booking as a single cell with a `colspan`, labelled with
its range (`14:00–17:00`), and skips the columns underneath so rows stay aligned.

**Conflict checking is interval overlap, not equal start times.** Comparing
start times was enough when every booking was one block; it is not now — a
14:00 booking running three hours has to block a new 15:00 one even though the
start times differ. Two bookings clash when each starts before the other ends:

```sql
reservationTime < :newEnd
AND DATE_ADD(reservationTime, INTERVAL COALESCE(duration_minutes, :slot) MINUTE) > :newStart
```

Touching at the boundary is not a clash: with 14:00–17:00 booked, a new booking
at 17:00 is accepted, and so is one ending at 14:00. The same test guards the
therapist in spa mode, so both locked resources honour the full range.

A booking that would run past closing time is rejected with the number of blocks
that would actually fit.

## Deploying the database to cPanel

A ready-to-import dump is produced with:

```bash
mysqldump -h 127.0.0.1 -u root -p \
  --single-transaction --skip-tz-utc --skip-set-charset \
  --set-gtid-purged=OFF --no-tablespaces \
  --default-character-set=utf8mb4 --routines --events --triggers \
  db_reservation > quinos-cpanel.sql
```

Then wrap it with `SET FOREIGN_KEY_CHECKS=0;` at the top and `SET
FOREIGN_KEY_CHECKS=1;` at the bottom. Each flag earns its place:

| Flag / step | Why it matters |
|---|---|
| `FOREIGN_KEY_CHECKS=0` wrapper | 87 tables import alphabetically, but the FKs criss-cross — without this the first table referencing a not-yet-created one aborts the import |
| `--set-gtid-purged=OFF` | MySQL 8+/9 otherwise writes `SET @@GLOBAL.GTID_PURGED`, which fails on the target with error 3546 |
| `--no-tablespaces` | shared hosting rarely grants the `PROCESS` privilege the default needs |
| no `CREATE DATABASE` / `USE` | cPanel database names are prefixed (`cpaneluser_quinos`), so the dump must import into whatever database is already selected |
| `utf8mb4_general_ci` on new tables | the MySQL 8+ default `utf8mb4_0900_ai_ci` does not exist on MariaDB or MySQL 5.7 and is rejected outright |

### Steps

1. **cPanel → MySQL® Databases** — create a database (e.g. `quinos`, which
   becomes `cpaneluser_quinos`), create a user, and grant it **ALL PRIVILEGES**.
2. **Import** — phpMyAdmin → select the new database → *Import* → upload the
   `.sql`. If it exceeds the upload limit, gzip it (`gzip quinos-cpanel.sql`) —
   phpMyAdmin reads `.sql.gz` directly. Over SSH:
   ```bash
   mysql -u cpaneluser_user -p cpaneluser_quinos < quinos-cpanel.sql
   ```
3. **Point the app at it** in `config/local.php` on the server:
   ```php
   <?php return [
       'db' => [
           'host' => 'localhost',            // cPanel MySQL is local to the account
           'name' => 'cpaneluser_quinos',
           'user' => 'cpaneluser_user',
           'pass' => 'the-password',
       ],
   ];
   ```
4. **Verify** — sign in, open `/tables`, and add a table. That single action
   exercises the part most likely to break: `tbl_sections` present and the
   `section_id` foreign key satisfiable.

Connecting from your own machine to the cPanel MySQL instead needs the host's
**Remote MySQL** page to whitelist your IP, and `host` set to the server's
hostname rather than `localhost`.

## Notes / status codes

`tbl_reservation.status`: `0` Booked · `1` Arrived · `2` Cancelled.
A reservation is point-in-time (single `reservationTime`); it appears in the
30-minute grid cell that contains its time.
