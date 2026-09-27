-- Adds a third role: Payroll Master. The panel's revision notes call this "Payroll
-- master/HR" — the account that encodes payroll figures and proposes route trip
-- rates, ranked below Admin (the Owner account), which must confirm anything the
-- Payroll Master proposes before it takes effect (see migration 031).
--
-- 'employee' stays the default so every existing account is completely unaffected;
-- this only adds a new value the app can now assign.
ALTER TABLE users
  MODIFY COLUMN role ENUM('admin', 'payroll_master', 'employee') NOT NULL DEFAULT 'employee';
