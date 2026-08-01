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
-- Run once (safe to re-run: it skips names that already exist).
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
    SELECT 1 FROM tbl_employees e WHERE e.name = t.name AND e.jobTitle = 'THERAPIST'
);
-- =============================================================================
