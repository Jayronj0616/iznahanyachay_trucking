-- Backfills a table that payroll/index.php already depends on (INSERT on Run Payroll,
-- SELECT for the "View" deductions modal on the Payroll Breakdown table) but that has
-- no earlier migration file anywhere in this repo's history — confirmed via grep across
-- database/migrations/*.sql before writing this. Without this table, Run Payroll fails
-- outright on any database that hasn't had it created out-of-band.
-- One row per SSS/PhilHealth/Pag-IBIG deduction per payroll_run, so deductions are a real
-- tracked transaction log rather than only the 3 summary columns on payroll_runs.
-- NOT RUN by Claude — prepared for the user to review and run manually.
CREATE TABLE deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('sss', 'philhealth', 'pagibig') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  basis_note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
