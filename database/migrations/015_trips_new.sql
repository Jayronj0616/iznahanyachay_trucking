-- Replaces the old placeholder `trips` table (migration 004: free-text destination, flat ₱50 incentive, single user_id, never wired to any UI or payroll query — confirmed dead).
-- New table is named `trips_new` (not `trips`) — old `trips` table still exists alongside it, unused.
CREATE TABLE trips_new (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_id INT NOT NULL,
  driver_id INT NOT NULL,
  helper_id INT NULL,
  amount_per_trip DECIMAL(10,2) NOT NULL,   -- snapshotted from routes.amount_per_trip at creation
  status ENUM('assigned','completed') NOT NULL DEFAULT 'assigned',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (route_id) REFERENCES routes(id),
  FOREIGN KEY (driver_id) REFERENCES users(id),
  FOREIGN KEY (helper_id) REFERENCES users(id)
);
