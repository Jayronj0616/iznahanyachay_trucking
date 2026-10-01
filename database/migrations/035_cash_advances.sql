-- Panel feedback (round 2): employees who take a cash advance ("vale") need it
-- recovered through payroll -- a one-time flat deduction from their next run, not an
-- installment plan. Separate concept from the statutory SSS/PhilHealth/Pag-IBIG
-- deductions (migration 030) -- this is the company recovering money it already
-- handed over, not a government remittance, so it gets its own table rather than a
-- fourth row in `deductions` (whose type enum is specifically the three statutory
-- kinds payroll/reports/index.php's remittance summary reads).
--
-- One row per advance given. `status` tracks whether it has been recovered yet;
-- `payroll_run_id`/`deducted_at` record which run recovered it, once applied.
CREATE TABLE cash_advances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NULL,
  given_by INT NOT NULL,
  given_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('outstanding', 'deducted', 'cancelled') NOT NULL DEFAULT 'outstanding',
  payroll_run_id INT NULL,
  deducted_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (given_by) REFERENCES users(id),
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id)
);

-- Defaults to 0 so every existing payroll_runs/payslips row keeps reading exactly as
-- it does today -- net_pay on old rows is unaffected, this only applies to runs
-- created from here on.
ALTER TABLE payroll_runs
  ADD COLUMN cash_advance_deduction DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_deductions;

ALTER TABLE payslips
  ADD COLUMN cash_advance_deduction DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_deductions;
