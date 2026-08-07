ALTER TABLE timesheet_entries
  ADD COLUMN deleted_at DATETIME NULL AFTER rejection_reason;
