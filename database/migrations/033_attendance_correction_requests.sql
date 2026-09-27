-- Panel feedback (sample slides 4-6): photo-verified time-in only works for today
-- (timesheet/entry/index.php restricts both time-in and time-out to date = today,
-- and once written there is no edit path at all). If the camera fails or an
-- office-based employee simply misses a punch, they have no way to correct it. This
-- table is the request an employee files instead -- what the time in/out should have
-- been, why, and optionally a proof photo -- for Payroll Master/Admin to approve or
-- decline in timesheet/requests/index.php.
--
-- Deliberately no reviewer-remarks column: the panel's sample screen shows Approve/
-- Decline only, no remarks field.
CREATE TABLE attendance_correction_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  requested_time_in TIME NULL,
  requested_time_out TIME NULL,
  reason VARCHAR(255) NOT NULL,
  proof_photo_path VARCHAR(255) NULL,
  status ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
);
