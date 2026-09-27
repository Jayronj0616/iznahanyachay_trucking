-- Panel feedback: statutory deductions (SSS/PhilHealth/Pag-IBIG) were hardcoded PHP
-- formulas in includes/payroll.php, with no way to see or change a bracket without
-- editing code. This table is the single source of truth those functions now read
-- from -- editable via more/contribution-brackets/ (admin only).
--
-- Two representations, because that is how the two real government tables actually
-- work:
--   - SSS publishes a genuine step table: a salary range maps to a fixed peso amount
--     (the MSC-based contribution), not a formula. employee_share carries that fixed
--     amount directly, and rate/base_min/base_max are unused (NULL).
--   - PhilHealth and Pag-IBIG are a flat rate applied to a base that is clamped
--     between a floor and a cap. For these, employee_share is NULL and the amount is
--     computed as clamp(monthlyEquiv, base_min, base_max) * rate.
--
-- min_monthly is inclusive, max_monthly is exclusive; NULL max_monthly means "and
-- above", so a single query ("min_monthly <= ? AND (max_monthly IS NULL OR
-- max_monthly > ?)") always finds exactly one bracket for a given monthly-equivalent
-- salary.
--
-- SEEDED VALUES REPRODUCE THE PRE-MIGRATION HARDCODED FORMULAS EXACTLY (verified by
-- recomputing every row against calculateSSS()/calculatePhilHealth()/calculatePagibig()
-- in includes/payroll.php before this file was written), so no existing payroll figure
-- changes the moment this ships -- only future bracket edits can change a number.
CREATE TABLE contribution_brackets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('sss', 'philhealth', 'pagibig') NOT NULL,
  min_monthly DECIMAL(10,2) NOT NULL,
  max_monthly DECIMAL(10,2) NULL,
  employee_share DECIMAL(10,2) NULL,
  rate DECIMAL(6,4) NULL,
  base_min DECIMAL(10,2) NULL,
  base_max DECIMAL(10,2) NULL,
  notes VARCHAR(255) NULL,
  UNIQUE KEY uniq_type_min (type, min_monthly)
);

-- SSS: 61 half-thousand-peso brackets from the MSC floor (5,000) to the MSC cap
-- (35,000), employee_share = MSC * 5%. Below 5,000 and above 35,000 both clamp into
-- the first/last row via the open-ended NULL boundaries.
INSERT INTO contribution_brackets (type, min_monthly, max_monthly, employee_share, rate, base_min, base_max, notes) VALUES
  ('sss', 0.00, 5500.00, 250.00, NULL, NULL, NULL, 'MSC ₱5000 (monthly), 5% employee share'),
  ('sss', 5500.00, 6000.00, 275.00, NULL, NULL, NULL, 'MSC ₱5500 (monthly), 5% employee share'),
  ('sss', 6000.00, 6500.00, 300.00, NULL, NULL, NULL, 'MSC ₱6000 (monthly), 5% employee share'),
  ('sss', 6500.00, 7000.00, 325.00, NULL, NULL, NULL, 'MSC ₱6500 (monthly), 5% employee share'),
  ('sss', 7000.00, 7500.00, 350.00, NULL, NULL, NULL, 'MSC ₱7000 (monthly), 5% employee share'),
  ('sss', 7500.00, 8000.00, 375.00, NULL, NULL, NULL, 'MSC ₱7500 (monthly), 5% employee share'),
  ('sss', 8000.00, 8500.00, 400.00, NULL, NULL, NULL, 'MSC ₱8000 (monthly), 5% employee share'),
  ('sss', 8500.00, 9000.00, 425.00, NULL, NULL, NULL, 'MSC ₱8500 (monthly), 5% employee share'),
  ('sss', 9000.00, 9500.00, 450.00, NULL, NULL, NULL, 'MSC ₱9000 (monthly), 5% employee share'),
  ('sss', 9500.00, 10000.00, 475.00, NULL, NULL, NULL, 'MSC ₱9500 (monthly), 5% employee share'),
  ('sss', 10000.00, 10500.00, 500.00, NULL, NULL, NULL, 'MSC ₱10000 (monthly), 5% employee share'),
  ('sss', 10500.00, 11000.00, 525.00, NULL, NULL, NULL, 'MSC ₱10500 (monthly), 5% employee share'),
  ('sss', 11000.00, 11500.00, 550.00, NULL, NULL, NULL, 'MSC ₱11000 (monthly), 5% employee share'),
  ('sss', 11500.00, 12000.00, 575.00, NULL, NULL, NULL, 'MSC ₱11500 (monthly), 5% employee share'),
  ('sss', 12000.00, 12500.00, 600.00, NULL, NULL, NULL, 'MSC ₱12000 (monthly), 5% employee share'),
  ('sss', 12500.00, 13000.00, 625.00, NULL, NULL, NULL, 'MSC ₱12500 (monthly), 5% employee share'),
  ('sss', 13000.00, 13500.00, 650.00, NULL, NULL, NULL, 'MSC ₱13000 (monthly), 5% employee share'),
  ('sss', 13500.00, 14000.00, 675.00, NULL, NULL, NULL, 'MSC ₱13500 (monthly), 5% employee share'),
  ('sss', 14000.00, 14500.00, 700.00, NULL, NULL, NULL, 'MSC ₱14000 (monthly), 5% employee share'),
  ('sss', 14500.00, 15000.00, 725.00, NULL, NULL, NULL, 'MSC ₱14500 (monthly), 5% employee share'),
  ('sss', 15000.00, 15500.00, 750.00, NULL, NULL, NULL, 'MSC ₱15000 (monthly), 5% employee share'),
  ('sss', 15500.00, 16000.00, 775.00, NULL, NULL, NULL, 'MSC ₱15500 (monthly), 5% employee share'),
  ('sss', 16000.00, 16500.00, 800.00, NULL, NULL, NULL, 'MSC ₱16000 (monthly), 5% employee share'),
  ('sss', 16500.00, 17000.00, 825.00, NULL, NULL, NULL, 'MSC ₱16500 (monthly), 5% employee share'),
  ('sss', 17000.00, 17500.00, 850.00, NULL, NULL, NULL, 'MSC ₱17000 (monthly), 5% employee share'),
  ('sss', 17500.00, 18000.00, 875.00, NULL, NULL, NULL, 'MSC ₱17500 (monthly), 5% employee share'),
  ('sss', 18000.00, 18500.00, 900.00, NULL, NULL, NULL, 'MSC ₱18000 (monthly), 5% employee share'),
  ('sss', 18500.00, 19000.00, 925.00, NULL, NULL, NULL, 'MSC ₱18500 (monthly), 5% employee share'),
  ('sss', 19000.00, 19500.00, 950.00, NULL, NULL, NULL, 'MSC ₱19000 (monthly), 5% employee share'),
  ('sss', 19500.00, 20000.00, 975.00, NULL, NULL, NULL, 'MSC ₱19500 (monthly), 5% employee share'),
  ('sss', 20000.00, 20500.00, 1000.00, NULL, NULL, NULL, 'MSC ₱20000 (monthly), 5% employee share'),
  ('sss', 20500.00, 21000.00, 1025.00, NULL, NULL, NULL, 'MSC ₱20500 (monthly), 5% employee share'),
  ('sss', 21000.00, 21500.00, 1050.00, NULL, NULL, NULL, 'MSC ₱21000 (monthly), 5% employee share'),
  ('sss', 21500.00, 22000.00, 1075.00, NULL, NULL, NULL, 'MSC ₱21500 (monthly), 5% employee share'),
  ('sss', 22000.00, 22500.00, 1100.00, NULL, NULL, NULL, 'MSC ₱22000 (monthly), 5% employee share'),
  ('sss', 22500.00, 23000.00, 1125.00, NULL, NULL, NULL, 'MSC ₱22500 (monthly), 5% employee share'),
  ('sss', 23000.00, 23500.00, 1150.00, NULL, NULL, NULL, 'MSC ₱23000 (monthly), 5% employee share'),
  ('sss', 23500.00, 24000.00, 1175.00, NULL, NULL, NULL, 'MSC ₱23500 (monthly), 5% employee share'),
  ('sss', 24000.00, 24500.00, 1200.00, NULL, NULL, NULL, 'MSC ₱24000 (monthly), 5% employee share'),
  ('sss', 24500.00, 25000.00, 1225.00, NULL, NULL, NULL, 'MSC ₱24500 (monthly), 5% employee share'),
  ('sss', 25000.00, 25500.00, 1250.00, NULL, NULL, NULL, 'MSC ₱25000 (monthly), 5% employee share'),
  ('sss', 25500.00, 26000.00, 1275.00, NULL, NULL, NULL, 'MSC ₱25500 (monthly), 5% employee share'),
  ('sss', 26000.00, 26500.00, 1300.00, NULL, NULL, NULL, 'MSC ₱26000 (monthly), 5% employee share'),
  ('sss', 26500.00, 27000.00, 1325.00, NULL, NULL, NULL, 'MSC ₱26500 (monthly), 5% employee share'),
  ('sss', 27000.00, 27500.00, 1350.00, NULL, NULL, NULL, 'MSC ₱27000 (monthly), 5% employee share'),
  ('sss', 27500.00, 28000.00, 1375.00, NULL, NULL, NULL, 'MSC ₱27500 (monthly), 5% employee share'),
  ('sss', 28000.00, 28500.00, 1400.00, NULL, NULL, NULL, 'MSC ₱28000 (monthly), 5% employee share'),
  ('sss', 28500.00, 29000.00, 1425.00, NULL, NULL, NULL, 'MSC ₱28500 (monthly), 5% employee share'),
  ('sss', 29000.00, 29500.00, 1450.00, NULL, NULL, NULL, 'MSC ₱29000 (monthly), 5% employee share'),
  ('sss', 29500.00, 30000.00, 1475.00, NULL, NULL, NULL, 'MSC ₱29500 (monthly), 5% employee share'),
  ('sss', 30000.00, 30500.00, 1500.00, NULL, NULL, NULL, 'MSC ₱30000 (monthly), 5% employee share'),
  ('sss', 30500.00, 31000.00, 1525.00, NULL, NULL, NULL, 'MSC ₱30500 (monthly), 5% employee share'),
  ('sss', 31000.00, 31500.00, 1550.00, NULL, NULL, NULL, 'MSC ₱31000 (monthly), 5% employee share'),
  ('sss', 31500.00, 32000.00, 1575.00, NULL, NULL, NULL, 'MSC ₱31500 (monthly), 5% employee share'),
  ('sss', 32000.00, 32500.00, 1600.00, NULL, NULL, NULL, 'MSC ₱32000 (monthly), 5% employee share'),
  ('sss', 32500.00, 33000.00, 1625.00, NULL, NULL, NULL, 'MSC ₱32500 (monthly), 5% employee share'),
  ('sss', 33000.00, 33500.00, 1650.00, NULL, NULL, NULL, 'MSC ₱33000 (monthly), 5% employee share'),
  ('sss', 33500.00, 34000.00, 1675.00, NULL, NULL, NULL, 'MSC ₱33500 (monthly), 5% employee share'),
  ('sss', 34000.00, 34500.00, 1700.00, NULL, NULL, NULL, 'MSC ₱34000 (monthly), 5% employee share'),
  ('sss', 34500.00, 35000.00, 1725.00, NULL, NULL, NULL, 'MSC ₱34500 (monthly), 5% employee share'),
  ('sss', 35000.00, NULL, 1750.00, NULL, NULL, NULL, 'MSC ₱35000 (monthly), 5% employee share');

-- PhilHealth: flat 2.5% of monthly salary, clamped between a 10,000 floor and a
-- 100,000 cap. One bracket covers every salary because the rate never changes -- only
-- the clamped base does.
INSERT INTO contribution_brackets (type, min_monthly, max_monthly, employee_share, rate, base_min, base_max, notes) VALUES
  ('philhealth', 0.00, NULL, NULL, 0.0250, 10000.00, 100000.00, 'Base clamped 10,000-100,000 (monthly), 2.5% employee share');

-- Pag-IBIG: 1% up to 1,500 monthly, 2% above that, base capped at 10,000 either way.
INSERT INTO contribution_brackets (type, min_monthly, max_monthly, employee_share, rate, base_min, base_max, notes) VALUES
  ('pagibig', 0.00, 1500.00, NULL, 0.0100, 0.00, 10000.00, 'Base capped at 10,000 (monthly), 1% employee share'),
  ('pagibig', 1500.00, NULL, NULL, 0.0200, 0.00, 10000.00, 'Base capped at 10,000 (monthly), 2% employee share');
