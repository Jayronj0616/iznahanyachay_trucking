-- Panel revision: driver/helper attendance should derive from completed trips, not manual clock-in.
-- One row per completed trip per person involved (driver + helper each get their own row) — NOT
-- one row per user per day, since a driver can complete multiple trips in a single day.
-- Presence-only: no time_in/time_out/hours. Existence of the row IS the record (auto-approved,
-- no status/approval column — trip completion is the verification, unlike manual timesheet_entries
-- which start 'pending' and need admin approval).
-- Does NOT feed payroll gross-pay calc — payroll/index.php reads trips_new directly for commission.
-- This table is attendance/presence tracking and reporting only.
CREATE TABLE trip_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('driver','helper') NOT NULL,
  date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips_new(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_date (user_id, date)
);
