-- SandWorkflow PostgreSQL upgrade script (idempotent).
ALTER TABLE "sand_workflow_log" ADD COLUMN IF NOT EXISTS "form_change_snapshot" jsonb NULL;

-- The PostgreSQL package starts at NanoID runtime IDs. RuntimeSchemaGuard
-- performs the structural check after the plugin update hook runs.
SELECT 1;
