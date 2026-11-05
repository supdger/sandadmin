-- file_usage: which subject still references a stored file.
-- Apply on PostgreSQL until bin/sand migrate exists.
-- Idempotent. file_id is the local storage key, not a foreign key.

CREATE TABLE IF NOT EXISTS file_usage (
    file_id VARCHAR(32) NOT NULL,
    subject_type VARCHAR(64) NOT NULL,
    subject_id VARCHAR(64) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (file_id, subject_type, subject_id)
);
