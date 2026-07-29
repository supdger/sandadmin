# SandWorkflow PostgreSQL package

This is the PostgreSQL-only adaptation of the upstream MySQL SandWorkflow
package. SaiPackage imports `install.sql`, `update.sql`, and `uninstall.sql`
by those exact names; all three use PostgreSQL syntax in this package.

- To regenerate the PostgreSQL installation schema after updating the upstream
  package, run `php tools/convert-install-sql.php /path/to/upstream/install.sql`.
- Runtime JSON recipient checks use PostgreSQL `jsonb` containment.
- This package targets a SaiAdmin host whose default database connection is
  named `pgsql`. It does not migrate an existing MySQL installation in place.
