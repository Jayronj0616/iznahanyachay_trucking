-- Panel feedback: not every employee has SSS/PhilHealth/Pag-IBIG withheld by the
-- employer -- some remit those government-mandated contributions themselves. This
-- flag lets payroll skip statutory deductions for that employee entirely instead of
-- always assuming the employer withholds them.
--
-- Defaults to 'employer_withholds', which is exactly what applyDeductions() already
-- does for every employee today, so no existing payroll figure changes until an
-- admin deliberately flips someone to self-remit.
ALTER TABLE employee_profiles
  ADD COLUMN government_contribution_mode ENUM('employer_withholds', 'self_remit') NOT NULL DEFAULT 'employer_withholds' AFTER rest_days;
