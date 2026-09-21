-- Gives every employee an explicit weekly rest-day pattern.
--
-- BACKGROUND
--   Absences were counted in home/index.php as "weekdays elapsed this month with no
--   timesheet entry row", with Saturday and Sunday hardcoded as the only non-working
--   days. That assumption does not survive contact with a fleet: a driver's rest day
--   is whatever day the schedule gives them.
--
--   With no stored pattern there is no way to tell a rest day apart from an absence,
--   so today a weekday off is counted against the employee and a Saturday worked is
--   counted as nothing at all. The client asked to be able to mark a date as a rest
--   day and to see when someone came in on one anyway; this column is what makes that
--   distinction expressible.
--
-- FORMAT
--   ISO-8601 weekday numbers, 1 = Monday through 7 = Sunday, comma separated, no
--   spaces. '6,7' is Saturday and Sunday. An empty string means no rest day at all.
--   Deliberately text rather than a MySQL SET: the values stay readable in a dump,
--   and an unusual schedule needs no schema change.
--
-- WHY THE DEFAULT IS '6,7'
--   That is exactly what the old hardcoded rule assumed, so every existing row keeps
--   behaving precisely as it does today. Nobody's absence count moves until an admin
--   changes their pattern on purpose. This matters on a live database that the client
--   is already running payroll against.

ALTER TABLE employee_profiles
  ADD COLUMN rest_days VARCHAR(20) NOT NULL DEFAULT '6,7' AFTER status;
