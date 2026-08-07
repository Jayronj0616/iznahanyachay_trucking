ALTER TABLE employee_profiles
  ADD COLUMN position ENUM('driver','helper','dispatcher','secretary','maintenance','liaison','operator_manager') NULL AFTER status;
