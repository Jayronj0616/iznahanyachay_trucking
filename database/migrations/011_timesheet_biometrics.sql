USE iznahanyachay_trucking;

ALTER TABLE timesheet_entries
  ADD COLUMN time_in_photo VARCHAR(255) NULL AFTER time_out;
