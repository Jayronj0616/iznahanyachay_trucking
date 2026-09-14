-- Supports cancelling a mis-assigned trip and editing an in-progress (status = 'assigned')
-- trip's route/driver/helper before it's completed. Previously a trip could only ever move
-- assigned -> completed, so a mistake was permanent.
ALTER TABLE trips_new
  MODIFY COLUMN status ENUM('assigned','completed','cancelled') NOT NULL DEFAULT 'assigned',
  ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at;
