-- Adds Hardcover's separate headline while preserving all existing descriptions.
ALTER TABLE books
    ADD COLUMN IF NOT EXISTS headline TEXT NULL AFTER title;
