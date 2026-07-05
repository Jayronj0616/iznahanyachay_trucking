ALTER TABLE timesheet_entries
  ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER type,
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER status;
