-- job_run: background job status for polling.
-- Idempotent. Apply with: php bin/job-migrate.php
-- Progress lives in this table. Workers must not keep it in process memory.

CREATE TABLE IF NOT EXISTS job_run (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    type VARCHAR(64) NOT NULL,
    payload TEXT NOT NULL,
    status VARCHAR(16) NOT NULL CHECK (status IN ('pending', 'running', 'succeeded', 'failed')),
    stage VARCHAR(64) NULL,
    progress INTEGER NOT NULL DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
    result TEXT NULL,
    error TEXT NULL,
    locked_by VARCHAR(128) NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    started_at VARCHAR(19) NULL,
    finished_at VARCHAR(19) NULL
);

CREATE INDEX IF NOT EXISTS job_run_status_created_idx ON job_run (status, created_at);
