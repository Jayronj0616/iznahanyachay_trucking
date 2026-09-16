-- Splits trip completion into two admin steps, per client requirement: a trip must no
-- longer become payable from a single click.
--
-- Before: assigned -> completed, where that one action simultaneously (a) closed the trip,
-- (b) wrote trip_attendance rows, and (c) made the trip eligible for commission in the next
-- payroll run. There was no gap between "the trip is finished" and "the trip is approved
-- for payment", which is out of step with how timesheet_entries already work in this app
-- (pending -> approved, and payroll only ever counts approved).
--
-- After: assigned -> delivered -> completed. 'delivered' means the driver has reported the
-- run finished; it is NOT payable and writes no attendance. 'completed' is the admin's
-- explicit acceptance and remains the only status payroll/index.php and home/index.php
-- count, so neither of those queries needs to change.
--
-- 'delivered' is placed between 'assigned' and 'completed' for readability only — MySQL
-- stores ENUM members by the string written, so existing rows are unaffected by the
-- reordering and no data migration is needed.
ALTER TABLE trips_new
  MODIFY COLUMN status ENUM('assigned','delivered','completed','cancelled') NOT NULL DEFAULT 'assigned',
  ADD COLUMN delivered_at DATETIME NULL AFTER started_at;
