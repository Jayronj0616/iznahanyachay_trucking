USE iznahanyachay_trucking;

CREATE TABLE employee_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  license_number VARCHAR(50) NULL,
  license_expiry DATE NULL,
  hire_date DATE NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
