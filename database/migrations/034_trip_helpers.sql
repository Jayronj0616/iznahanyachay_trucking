-- Panel feedback (round 2, pptx slide 8): a trip should support more than one helper, not just
-- the single `trips_new.helper_id` column. Real deliveries sometimes need two people helping the
-- driver, and the schema had no way to express that.
--
-- New junction table, one row per helper per trip, so a trip can have zero, one, or several.
-- Existing helper_id assignments are backfilled here so no trip loses its helper the moment this
-- ships. Going forward, application code reads/writes trip_helpers only -- trips_new.helper_id is
-- left in place (not dropped, matching this codebase's habit of leaving a superseded column alone
-- rather than risking a drop -- see migration 015's note about the old `trips` table) but is no
-- longer written to by new trip assignments, so it will read as stale/legacy for anything created
-- after this migration.
CREATE TABLE trip_helpers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  helper_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips_new(id),
  FOREIGN KEY (helper_id) REFERENCES users(id),
  UNIQUE KEY uniq_trip_helper (trip_id, helper_id)
);

INSERT INTO trip_helpers (trip_id, helper_id)
SELECT id, helper_id FROM trips_new WHERE helper_id IS NOT NULL;
