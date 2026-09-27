-- Panel feedback: "forgot password" doesn't exist anywhere. There is no mailer in
-- this app, so a reset can't be emailed -- instead the account owner submits a
-- request here, and whoever outranks them reviews it and issues a temporary
-- password (more/staff/password-requests.php), reusing the existing forced-change
-- flow (users.must_change_password, includes/auth.php:20-23).
--
-- Admin/Owner is the top of the role hierarchy and has no one to route a request to,
-- so their own recovery goes through the security question on their account
-- (migration 028) instead of this table.
CREATE TABLE password_reset_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
);
