-- Backfill student photo_url to the canonical default avatar.
-- The default avatar lives at uploads/students/avatar.jpg (relative to the app root).
-- This makes every student (including pre-existing ones with NULL photo_url)
-- resolve to a real image so pages no longer hunt for a missing path.

UPDATE students
SET photo_url = 'uploads/students/avatar.jpg'
WHERE photo_url IS NULL OR photo_url = '' OR TRIM(photo_url) = 'NULL';

-- Guard the column so future inserts without a photo default to the avatar.
-- (No-op on MySQL if the column already matches; safe to re-run.)
ALTER TABLE students
    MODIFY COLUMN photo_url varchar(255) NOT NULL DEFAULT 'uploads/students/avatar.jpg';
