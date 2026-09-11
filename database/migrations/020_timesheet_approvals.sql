-- Backfills a table that timesheet/review/index.php (Approve Period) and payroll/index.php
-- (eligible-periods dropdown, driver/helper period lookup) already depend on, but that has
-- no earlier migration file anywhere in this repo's history — confirmed via grep across
-- database/migrations/*.sql before writing this. Without this table, "Approve Period" on
-- the Review Timesheet screen fails outright, and Run Payroll has no eligible periods to
-- select from at all.
-- Append-only audit trail: one row per approval action (who approved which period, when),
-- not a status flag that gets overwritten — payroll disputes need that history preserved,
-- same rationale as the payroll_runs -> payslips snapshot pattern already in this codebase.
-- NOT RUN by Claude — prepared for the user to review and run manually.
CREATE TABLE timesheet_approvals (
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
