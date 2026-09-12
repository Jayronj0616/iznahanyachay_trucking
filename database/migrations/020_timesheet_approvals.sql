-- Backfills a table that timesheet/review/index.php (Approve Period) and payroll/index.php
-- (eligible-periods dropdown, driver/helper period lookup) already depend on, but that had
-- no earlier migration file anywhere in this repo's history — confirmed via grep across
-- database/migrations/*.sql before writing this.
-- CONFIRMED 2026-09-12: the table already exists on the live local DB (created out of
-- migration tracking at some point, with 1 real row already in it — AUTO_INCREMENT was
-- already at 2) and its structure matches this file (verified via SHOW CREATE TABLE;
-- the live table has two single-column indexes instead of the composite one below, which
-- doesn't change correctness). Written as IF NOT EXISTS so this is a safe no-op there,
-- while still giving a fresh database (a new clone, a teammate's machine) the table it
-- needs. Append-only audit trail: one row per approval action (who approved which period,
-- when), not a status flag that gets overwritten — payroll disputes need that history
-- preserved, same rationale as the payroll_runs -> payslips snapshot pattern already in
-- this codebase.
CREATE TABLE IF NOT EXISTS timesheet_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  approved_by INT NOT NULL,
  approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  INDEX idx_user_period (user_id, period_start, period_end)
);
