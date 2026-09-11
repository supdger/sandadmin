\set ON_ERROR_STOP on

-- One-way PostgreSQL migration for the independent SandAdmin core namespace.
-- It preserves rows, identities and dependencies while changing all core
-- relations, indexes and sequences from `sa_` to `sand_`. PostgreSQL updates
-- primary-key and unique constraint names together with their backing indexes.
DO $$
DECLARE
    item record;
    renamed text;
BEGIN
    FOR item IN
        SELECT c.relname, c.relkind
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND (c.relname LIKE 'sa_system_%' OR c.relname LIKE 'sa_tool_%')
          AND c.relkind IN ('r', 'p')
        ORDER BY c.relname
    LOOP
        renamed := regexp_replace(item.relname, '^sa_', 'sand_');
        EXECUTE format('ALTER TABLE public.%I RENAME TO %I', item.relname, renamed);
    END LOOP;

    FOR item IN
        SELECT c.relname, c.relkind
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND (c.relname LIKE 'sa_system_%' OR c.relname LIKE 'sa_tool_%')
          AND c.relkind IN ('S', 'i')
        ORDER BY c.relkind, c.relname
    LOOP
        renamed := regexp_replace(item.relname, '^sa_', 'sand_');
        IF item.relkind = 'S' THEN
            EXECUTE format('ALTER SEQUENCE public.%I RENAME TO %I', item.relname, renamed);
        ELSE
            EXECUTE format('ALTER INDEX public.%I RENAME TO %I', item.relname, renamed);
        END IF;
    END LOOP;

END
$$;
