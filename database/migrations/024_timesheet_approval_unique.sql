-- Enforces one approval row per employee per period at the database level.
--
-- timesheet/review/index.php now checks for an existing row before inserting (row-locked,
-- inside the same transaction), but the application check alone is not enough: payroll
-- reads this table to build its list of payable periods, so a duplicate row here silently
-- duplicates a period in that list. The constraint is the thing that actually guarantees
-- it, independent of which code path does the insert.
--
-- The table is an append-only audit trail (who approved which period, when) — see
-- 020_timesheet_approvals.sql. This index does not change that; it only prevents the same
-- (employee, period) being recorded twice.
--
-- SAFE TO RUN: verified against the live DB 2026-09-19 before writing. The table held 2
-- rows, (user 3, 2026-07-01..07-31) and (user 5, 2026-09-01..09-30) — already distinct on
-- these three columns, so the index builds without a duplicate-key failure. If a future
-- database DOES contain duplicates this will error rather than silently dropping rows,
-- which is the correct behaviour for a payroll audit table: find out which approval is
-- real before deleting either.
--
-- Note the redundant single-column idx_user_period from 020 is deliberately left in place.
-- Dropping it is cosmetic and would need an IF EXISTS dance across MySQL versions.

ALTER TABLE timesheet_approvals
  ADD UNIQUE KEY uniq_user_period (user_id, period_start, period_end);
