-- Backfills a table that payroll/index.php already depends on (INSERT on Run Payroll,
-- SELECT for the "View" deductions modal on the Payroll Breakdown table) but that had
-- no earlier migration file anywhere in this repo's history — confirmed via grep across
-- database/migrations/*.sql before writing this.
-- CONFIRMED 2026-09-12: the table already exists on the live local DB (created out of
-- migration tracking at some point, with 3 real rows already in it — AUTO_INCREMENT was
-- already at 4) and its structure matches this file exactly (verified via SHOW CREATE
-- TABLE). Written as IF NOT EXISTS so this is a safe no-op there, while still giving a
-- fresh database (a new clone, a teammate's machine) the table it needs.
CREATE TABLE IF NOT EXISTS deductions (
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
