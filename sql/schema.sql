-- =============================================================================
-- Quinos Reservations — DATABASE NOTES
-- =============================================================================
-- This app runs directly on the EXISTING Quinos POS database and requires
-- NO schema changes. Do NOT run CREATE/DROP here — your data already exists.
--
-- Import your data (once) into db_reservation, e.g. from the provided dump:
--     mysql -u root -p db_reservation < db_arna2.sql
--
-- Tables used (all pre-existing):
--   tbl_reservation  bookings (reservationTime, cover=pax, tableName, status,
--                    customer_id, reservedBy_id, voidReason, notes, phone, name)
--   tbl_customers    guests (name, phone1, email, notes, ...)
--   tbl_tables       bookable tables/rooms grouped by section_id (name, capacity)
--   tbl_employees    staff — used for login (name/code + pin) and role
--
-- Status codes used by this app (tbl_reservation.status):
--   0 = Booked        1 = Arrived        2 = Cancelled (voidReason set)
--
-- OPTIONAL performance index for the grid/calendar date lookups (safe, additive).
-- Uncomment to apply:
--   ALTER TABLE tbl_reservation ADD INDEX idx_res_time (reservationTime);
--
-- -----------------------------------------------------------------------------
-- Therapists (for the "Book Therapist" grid). These are bookable resources,
-- stored as employees with jobTitle='THERAPIST'. No pin => they cannot log in.
-- A therapy booking is stored on tbl_reservation.servedBy_id. Rename freely.
-- Only seeds when the database has NO therapists at all — once real therapist
-- staff exist (from a POS import, say) re-running this adds nothing.
INSERT INTO tbl_employees (active, driver, resigned, name, jobTitle, joined)
SELECT * FROM (
    SELECT b'1' AS active, b'0' AS driver, b'0' AS resigned,
           'THERAPIST 01' AS name, 'THERAPIST' AS jobTitle, CURDATE() AS joined
    UNION ALL SELECT b'1',b'0',b'0','THERAPIST 02','THERAPIST',CURDATE()
    UNION ALL SELECT b'1',b'0',b'0','THERAPIST 03','THERAPIST',CURDATE()
    UNION ALL SELECT b'1',b'0',b'0','THERAPIST 04','THERAPIST',CURDATE()
    UNION ALL SELECT b'1',b'0',b'0','THERAPIST 05','THERAPIST',CURDATE()
    UNION ALL SELECT b'1',b'0',b'0','THERAPIST 06','THERAPIST',CURDATE()
) t
WHERE NOT EXISTS (
    SELECT 1 FROM tbl_employees e WHERE e.jobTitle = 'THERAPIST'
);
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Cloud sync (Quinos Cloud Customer API) — REQUIRED before customer sync works.
--
-- Tracks which local customers have reached quinosbackend.com, so the Customers
-- list can show Synced / Failed / Not synced, and so a retry updates the cloud
-- record instead of creating a second one. Purely additive: no POS table is
-- touched. Without it the app runs exactly as before, with sync reported off.
-- Run once (safe to re-run).
CREATE TABLE IF NOT EXISTS tbl_customer_cloud_sync (
    customer_id BIGINT      NOT NULL,
    cloud_id    BIGINT      NULL,          -- id returned by the API
    code        VARCHAR(16) NULL,          -- code we sent (prefix + padded local id)
    status      VARCHAR(10) NOT NULL,      -- 'synced' | 'failed'
    last_error  VARCHAR(255) NULL,
    synced_at   DATETIME    NULL,          -- last successful push
    PRIMARY KEY (customer_id),
    KEY idx_ccs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Multi-slot bookings — REQUIRED for bookings longer than one time block.
--
-- A reservation was point-in-time: reservationTime placed it in exactly one grid
-- column. This column lets one booking occupy several consecutive slots (two
-- hours, three hours, …). NULL means "one slot", so every existing booking keeps
-- its current behaviour and the POS, which never selects this column, is
-- unaffected. Additive and nullable. Run once (safe to re-run).
-- `ADD COLUMN IF NOT EXISTS` is MariaDB-only, and this has to run on MySQL too,
-- so the existence check goes through information_schema instead.
SET @ddl := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'tbl_reservation'
        AND COLUMN_NAME  = 'duration_minutes') > 0,
    'DO 0',
    'ALTER TABLE tbl_reservation ADD COLUMN duration_minutes INT NULL'
));
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- =============================================================================
