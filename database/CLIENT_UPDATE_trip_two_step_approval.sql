-- =====================================================================
-- Iznahanyachay Trucking System — database update
-- Trip completion split into two steps: delivered, then admin-accepted
-- =====================================================================
--
-- WHAT THIS CHANGES
--   Previously a trip went straight from "assigned" to "completed" in one
--   action, and that single action also recorded attendance and made the
--   trip payable. This update adds an intermediate "delivered" status, so
--   a trip is first reported finished and only becomes payable once an
--   admin accepts it.
--
-- BEFORE YOU RUN IT
--   1. Back up the database. In phpMyAdmin: select the database, open the
--      Export tab, and save the .sql file.
--   2. Run this against the trucking system database only.
--
-- HOW TO RUN IT
--   phpMyAdmin : select the database -> SQL tab -> paste this whole file -> Go
--   Command line : mysql -u USERNAME -p DATABASE_NAME < this_file.sql
--
-- NOTES
--   - Safe to run more than once. If part of it was already applied, that
--     part is skipped instead of failing.
--   - Works on both MySQL and MariaDB.
--   - No existing data is deleted or modified. Trips already marked
--     completed stay completed and stay payable.
-- =====================================================================


-- ---------------------------------------------------------------------
-- Step 1 of 3 — add the delivered_at column, if it isn't there already
-- ---------------------------------------------------------------------
-- Records when the trip was reported finished. Stays NULL for trips that
-- were completed under the old one-step flow.

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE trips_new ADD COLUMN delivered_at DATETIME NULL AFTER started_at',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'trips_new'
    AND COLUMN_NAME  = 'delivered_at'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- Step 2 of 3 — add the cancelled_at column, if it isn't there already
-- ---------------------------------------------------------------------
-- Included in case this database has not had the earlier trip edit/cancel
-- update applied. If it already has it, this step does nothing.

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE trips_new ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'trips_new'
    AND COLUMN_NAME  = 'cancelled_at'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- Step 3 of 3 — allow the new "delivered" status
-- ---------------------------------------------------------------------
-- Every status already in use is kept in the list, so existing trip rows
-- are unaffected. Running this a second time changes nothing.

ALTER TABLE trips_new
  MODIFY COLUMN status ENUM('assigned','delivered','completed','cancelled')
  NOT NULL DEFAULT 'assigned';


-- ---------------------------------------------------------------------
-- Result — all three rows below should say OK
-- ---------------------------------------------------------------------

SELECT
  'delivered_at column' AS item,
  IF(COUNT(*) = 1, 'OK', 'MISSING — update did not apply') AS result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'trips_new'
  AND COLUMN_NAME  = 'delivered_at'

UNION ALL

SELECT
  'cancelled_at column',
  IF(COUNT(*) = 1, 'OK', 'MISSING — update did not apply')
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'trips_new'
  AND COLUMN_NAME  = 'cancelled_at'

UNION ALL

SELECT
  'delivered status',
  IF(COLUMN_TYPE LIKE '%delivered%', 'OK', 'MISSING — update did not apply')
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'trips_new'
  AND COLUMN_NAME  = 'status';


-- Existing trips, for reference. Nothing here should have changed.
SELECT id, status, started_at, delivered_at, completed_at, cancelled_at
FROM trips_new
ORDER BY id;
