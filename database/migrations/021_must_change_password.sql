-- Supports the forced-password-change flow: admin-created accounts start with
-- must_change_password = 1, and requireLogin() (includes/auth.php) redirects
-- anyone with the flag set to /more/change-password/ until they set their own
-- password, at which point the flag is cleared. Existing accounts default to 0
-- so nobody currently logged in gets unexpectedly locked into that flow.
ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password;
