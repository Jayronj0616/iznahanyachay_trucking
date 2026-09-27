-- Panel feedback: a route's trip rate could be set to "any amount ... no validation".
-- Payroll Master can now propose a new rate, but it must not take effect until
-- Admin/Owner confirms it -- Admin/Owner is the only role that can still edit
-- amount_per_trip directly (they are the top of the hierarchy, so their own edits
-- need no second sign-off).
--
-- pending_amount holds the proposed rate until approved or rejected; pending_by /
-- pending_at record who proposed it and when, for the approval screen in
-- more/routes/index.php. All three are cleared together on approve or reject.
ALTER TABLE routes
  ADD COLUMN pending_amount DECIMAL(10,2) NULL AFTER amount_per_trip,
  ADD COLUMN pending_by INT NULL AFTER pending_amount,
  ADD COLUMN pending_at TIMESTAMP NULL AFTER pending_by,
  ADD CONSTRAINT fk_routes_pending_by FOREIGN KEY (pending_by) REFERENCES users(id);
