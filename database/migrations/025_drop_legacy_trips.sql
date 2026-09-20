-- Drops the dead `trips` table left behind by migration 015.
--
-- BACKGROUND
--   Migration 004 created `trips` as a placeholder: free-text destination, a flat
--   50.00 incentive and a single user_id. Migration 015 replaced it with `trips_new`
--   (route_id, driver_id, helper_id, snapshotted amount, status) but deliberately left
--   the old table in place rather than dropping it mid-rebuild.
--
-- WHY IT IS SAFE TO DROP NOW
--   Verified before writing:
--     * 0 references to the `trips` table anywhere in the PHP source. Every trip query
--       in home/, more/trips/, more/trip-attendance/ and payroll/ targets `trips_new`.
--     * No foreign key in any other table REFERENCES `trips`.
--   The only rows it holds are the original placeholder records from July.
--
--   The live table keeps the name `trips_new` for now. Renaming it to `trips` is the
--   correct end state but rewrites 16 query references across 5 files, which is not a
--   change to make immediately before a client review.

DROP TABLE IF EXISTS trips;
