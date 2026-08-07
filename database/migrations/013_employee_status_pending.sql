ALTER TABLE employee_profiles
  MODIFY COLUMN status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'active';
