# Failed-upgrade recovery contract v2

> 兼容范围：本文描述旧恢复记录的 `LegacyInstallLogic` 路径。新 `saipackage-pg-v1`
> 普通安装不要求该恢复协议；新包不能调用旧恢复入口。参见
> [上游 PostgreSQL 适配](SAIPACKAGE_POSTGRESQL_ADAPTATION.md)。

SandPackage is a generic host capability. A recoverable candidate supplies its own canonical `recovery/failed-upgrade.v2.json`; the host contains no plugin-specific catalog, version, menu, migration, or path profile.

The descriptor binds `app`, `from_version`, `to_version`, normalized payload digest, `update.sql` digest and one profile. Algorithm `sandpackage-normalized-package-manifest/v1` hashes canonical JSON shaped as `{algorithm, files: {relative_path: sha256}, schema: "sandpackage.normalized-package-manifest/v1"}`. File sizes are not serialized; original candidate bytes, including `info.ini`, are hashed. The normalized payload excludes only generated root descriptor files and the exact mirror at `plugin/<descriptor app>/recovery/failed-upgrade.v2.json[.sha256]`; foreign-app, nested or lookalike descriptor paths remain part of the payload identity. Unknown algorithm IDs fail closed. Profile `app`, `from_version`, and `to_version` must exactly equal the descriptor tuple, and the target version must be greater than the source version. The profile is canonical JSON with only these assertion types: `relations_exact`, `relation_absent`, `column_exact`, `constraint_exact`, `index_exact`, `menu_rows_exact`, and `ledger_absent` (up to 256 assertions). Table identifiers use the candidate app prefix. Constraint and index identifiers may instead use a bounded conventional kind prefix (`ck_`, `fk_`, `pk_`, `uk_`, `uq_`, `ux_`, `idx_`, or `ix_` as applicable) immediately before that app prefix; arbitrary names remain invalid. Menu codes use `str_replace('-', '_', app) . ':'`; the verifier executes only fixed parameterized PostgreSQL catalog queries. `constraint_exact` binds constraint type, name, validation state and the complete `pg_get_constraintdef(..., true)` definition exactly after outer trimming. It does not erase casts, grouping, whitespace inside literals, or regular-expression syntax. SQL, PHP, shell commands, arbitrary paths, duplicate keys, non-canonical JSON, overlong values and unknown assertion words are rejected.

Recovery endpoints return only safe tuple fields. `inspectFailedUpgradeRecovery` is read-only and exposes `{app, from_version, to_version, recovery_mode, runtime_restore_required, runtime_drift, allowed_actions, message}`; `runtime_drift` is a boolean and never exposes runtime paths. Runtime drift or a retained recovery journal makes `restore_runtime_from_backup` the only action. Once runtime files exactly match the identity-bound pre-upgrade manifest, the only action is `prepare_failed_upgrade_replacement`. A historical `database_update` failure may enter this bootstrap only when all four v2 candidate digests are absent; a partial identity is always blocked. The prepared replacement is then bound to the verified backup without trusting the stale active candidate.

`verifyFailedUpgradeRecovery` requires `appName` and `replacementId`, then returns `{app, from_version, to_version, replacement_id, profile_hash, verdict, recovery_state, evidence_fingerprint, assertions_total, assertions_passed, failed_assertion_ids, audit_written, allowed_actions, message}`. Formal Gate A explicitly initializes the named `pgsql` Think ORM connection, passes the returned PDO to the verifier, runs a PostgreSQL `READ ONLY`, `REPEATABLE READ` transaction and does not write recovery audit state. A frontend may permit replacement only when the tuple and replacement id match, both hashes are lowercase SHA-256, every assertion passed, `failed_assertion_ids` is empty, `audit_written` is false, `verdict` is `retry_safe`, and `allowed_actions` is exactly `['replace_failed_upgrade_candidate']`.

`restoreRuntimeFromBackup` accepts only the exact confirmation `RESTORE RUNTIME <app>@<from_version>`. Its durable `result` is identity-bound and its `presentation` always declares `recovery_mode=verification_required` with the sole next action `prepare_failed_upgrade_replacement`. It revalidates backup, registration, deployment and runtime manifests; stages private copies; fsyncs files; journals exact paths; quarantines current runtime targets; atomically renames staged targets; and converges an interrupted transaction on the next invocation. It does not execute SQL, change the candidate, modify the failed registry shape, or reload a service. Success remains `verification_required`; repeating the same confirmed request is idempotent.

`prepare` only preflights and retains a candidate; it returns `{app, from_version, to_version, replacement_id, profile_hash, message}`. Its record freezes the failed attempt backup id, archive, payload, descriptor, profile and `update.sql` hashes. `replace` and `retry` revalidate runtime readiness, package evidence and catalog state under their own lock. Confirmation text is supplied by the caller and is never issued by the host. All business refusals use SaiAdmin `ApiException` with code 400.

## Component ownership

- `FailedUpgradePackageIdentity` owns descriptor, normalized payload, lifecycle-file, retained archive and digest calculations.
- `FailedUpgradeRecoveryInspector` owns read-only failed-shape and runtime-drift decisions.
- `FailedUpgradeRecoveryVerifier` owns the fixed, parameterized read-only PostgreSQL catalog vocabulary.
- `FailedUpgradeRecoveryCoordinator` owns the `inspect -> restore? -> prepare -> verify -> replace -> retry` ordering and exact confirmations.
- `FailedUpgradeRecoveryFileTransaction` owns private stage/quarantine roots, `0600` atomic journals, fsync, atomic rename and interruption convergence.
- `FailedUpgradeRecoveryAudit` owns the fixed audit field whitelist and verified Web/OS actor identities.
- `InstallLogic` remains a compatibility facade and supplies generic host lifecycle primitives; it does not select a plugin-specific recovery profile.

The same contract is available through authenticated management routes and `php webman sandpackage:recover`; `gate-a` is the explicit CLI alias for the read-only prepared-replacement verification. CLI mutation audit identity comes from the effective OS account; an optional display label never replaces that identity.

Every install, update, retry, and uninstall script goes through the PostgreSQL lifecycle executor. It tokenizes PostgreSQL single/double/E strings, line comments, nested block comments and dollar quotes; rejects malformed input and `COPY FROM STDIN`. A script with no explicit transaction is wrapped once; a script with explicit transactions preserves its own blocks, must close each block, and cannot mix explicit and implicit blocks. Any failure rolls back the active transaction.

## Interrupted pre-upgrade backup recovery

SandPackage 6.1.4 and earlier could leave an otherwise valid installed package
under `runtime/sandpackage/backups/<backup_id>` with the candidate journal frozen
at `backed_up` when the stored registration digest was stale. This is distinct
from a `database_update` failure: the candidate SQL and file deployment have not
started.

The supported repair is intentionally narrow:

1. `POST /app/sandpackage/install/inspectInterruptedPreUpgradeBackup` with
   `appName`. This is read-only. It verifies the app/version, the retained
   per-file package manifest, the stale registration digest, the actual
   deployment digest and both deployed runtime targets. It returns the exact
   `confirmation`.
2. Review the returned app, version, backup ID and both SHA-256 values.
3. `POST /app/sandpackage/install/restoreInterruptedPreUpgradeBackup` with
   `appName` and that unchanged `confirmation`.
4. Confirm the result has `state=restored` and `sql_executed=false`, then refresh
   the plugin list before starting a new upload.

The confirmation format is
`RESTORE PRE-UPGRADE <app>@<from_version> <backup_id> <previous_registration_manifest>:<deployment_manifest>`.
The write action acquires the app operation lock, restores only the retained
package directory, normalizes its registration digest to the already verified
deployment digest and durably removes the journal. It does not run lifecycle
SQL, deploy files, register services or reload a service. A wrong confirmation,
file drift, path conflict or incomplete journal leaves the backup and journal
in place for diagnosis. An interruption after the directory rename can be
retried with the same confirmation.

Do not hand-edit the journal or digest, manually rename the backup, or use this
entry for a database failure. A database-committed continuation requires a
separate receipt-backed lifecycle contract; this recovery does not infer
whether SQL committed and never skips or retries SQL.
