# Unified demo migration evidence

## SandAdmin source

- Source revision: `f49f9e225f9eaebe9a7857a9038868f0cacee2e2`.
- A local clean clone installed `server/composer.lock`, discovered the
  `sand:*` and `sandpackage:*` commands, installed the frontend lockfile, and
  completed the production build.
- The source clean-host check found no SandAI, SandIAM, or SandWorkflow runtime
  copy.

## sand_demo

- The host was inventoried before synchronization. Its environment files,
  database/cache configuration, dependencies, runtime data, JWT configuration,
  candidate directory, and business-plugin paths were excluded from host
  synchronization.
- The initial dry-run was reviewed before applying the clean source revision.
- `sandadmin-host.lock` records the source revision and clean state.
- A second dry-run reports no remaining core drift.
- The pre-sync SHA-256 values of the three frontend environment files plus
  `server/config/database.php` and `server/config/think-cache.php` still match.
- Removing the historical Composer package triggered its uninstall hook and
  temporarily removed the core plugin directories. The already-unlocked
  dependency conversion was followed by a second authoritative source sync and
  a no-op Composer install. Command discovery then passed.
- The historical uninstall hook also rewrote the protected cache driver. Its
  exact pre-sync bytes were reconstructed from the inverse hook operation and
  verified against the recorded pre-sync SHA-256.
- Frontend production build passed. No database was created, no migration was
  executed, and no service was started or stopped.

## sand_plugins retirement

- Existing unrelated dirty plugin work was recorded and left untouched.
- The plugin-to-demo script dry-run exports only from the plugin authority to
  `/Users/code/project/sand_demo/plugins/<plugin>`.
- The old untracked `sandadmin-demo`, `sandadmin-demo-rebuilt`, and
  `sandadmin-demo.lock` were moved, not deleted, to
  `/Users/code/project/sand_plugins_demo_retired_20260922_0015`.
- SandAI, SandIAM, and SandWorkflow source directories remain present.

## Final source/build validation

- Composer strict validation passed.
- All non-database backend contract tests passed, including the SandPackage
  lifecycle contract and a reduced 10-round multiprocess cache regression.
- The database-authorized PostgreSQL recovery probe was intentionally not run.
- The frontend production build and post-build type check passed.
- OpenSpec strict validation and `git diff --check` passed.
- Database creation, migrations, service lifecycle, browser login, and real
  plugin install/upgrade/uninstall remain outside this source/build acceptance.
