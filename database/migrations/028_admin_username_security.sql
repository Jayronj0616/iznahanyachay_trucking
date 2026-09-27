-- Panel feedback: the Admin/Owner sign-in should not look like the employee sign-in
-- and should use a distinct credential, not the shared email. This adds a username
-- Admin/Owner logs in with (nullable/unique so it only matters once an admin sets
-- one via more/staff/) plus a security question/answer pair for Admin/Owner's own
-- password recovery -- Admin/Owner is the top of the role hierarchy, so unlike an
-- employee or Payroll Master (who route a forgot-password request to whoever
-- outranks them), there is nobody above Admin/Owner to approve a reset request.
--
-- The answer is stored hashed (password_hash), same as the login password, and is
-- never displayed back once set.
ALTER TABLE users
  ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER email,
  ADD COLUMN security_question VARCHAR(255) NULL AFTER username,
  ADD COLUMN security_answer_hash VARCHAR(255) NULL AFTER security_question;
