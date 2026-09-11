# SandPackage failed-upgrade recovery — Gate A/B staging verification

Scope: only `.staging/sandpackage-failed-upgrade-recovery-v1-20260901T121000Z`.
No database was created or queried, and no service, host, authority source,
registry, or runtime deployment was changed. The scoped staging recovery UI
fixture was updated; no authoritative frontend source was changed.

## Revalidated 2026-09-07

### Runtime-restore response increment (v1.2.1)

The scoped staging implementation now separates verified backup self-consistency
from mutable deployed-runtime comparison. Only the frozen descriptor-less
SandIAM legacy failure tuple may expose `restore_runtime_from_backup`; its
relative diagnostic is capped at ten changed paths per backend/frontend target.
The explicit controller route requires POST, super-admin authentication and
`RESTORE RUNTIME sand-iam@0.6.0`. The portable public-controller behavior
fixture proves a one-file post-backup drift returns no PDO connection, exposes
only this action, preserves candidate/backup on bad confirmation, restores the
stored manifest on success, and resumes a journalled interruption. It also
proves the public Controller's real SaiAdmin envelope contains only durable
restore evidence plus fresh `verification_required` presentation, and that a
second confirmed call returns the same evidence. It does not exercise a real
host, database, service or browser.

### PSR-4 runtime-loader repair (v1.2.2)

The observed `FailedUpgradeIdentityBinding` loader failure was a class-to-file
delivery defect, not a registry, candidate, runtime, database, or Composer
prefix defect. The immutable production FQCN is now declared only in
`app/logic/FailedUpgradeIdentityBinding.php` under its exact
`plugin\\sandpackage\\app\\logic` namespace. The verifier retains only its own
FQCN, and `InstallLogic` contains no `require` or `class_alias` workaround.

`tests/failed_upgrade_recovery_psr4_autoload_contract.php` starts a new PHP
subprocess. It reads the configured demo host's Composer autoload file, prepends
the staging SandPackage PSR-4 prefix, initializes an empty in-process Webman
route collector, loads the staging route/controller, autoloads the controller,
logic, binding, and verifier FQCNs, then invokes `verify` with an invalid
descriptor and asserts its existing fail-closed result. It creates no database
connection and does not instantiate `InstallLogic`, so it does not alter host
runtime state. This proves the original autoload path without relying on a
prior include in the parent process.

`tests/failed_upgrade_recovery_gate_a_contract.php` is an executable behavior
fixture, not a source-contract test. It invokes the real route registration,
`InstallController`, `InstallLogic`, `Db::connect('pgsql')->getPdo()`, and
`FailedUpgradeRecoveryVerifier`. Its PDO double implements only the fixed
PostgreSQL catalog protocol used by the production verifier; it never opens,
creates, or queries a real database. The fixture creates its temporary package
and host state only below the system temporary directory and removes it on exit.

The fixture first uses public upload, register, discard, and `getInfo` methods
to obtain the production registration manifest and descriptor-excluded payload
digest. It then verifies a canonical failed candidate through the real public
controller endpoint in three outcomes: exact baseline pass, unreviewed catalog
relation block, and failed rollback/closed Think connection. A deliberately
malformed candidate transaction journal remains unreadable but byte-identical,
which proves the verification lock did not invoke candidate recovery. In every
outcome it compares registry `info.ini`, candidate-root tree, and journal hashes.

The failed-state parser accepts only registry integers or their exact canonical
string forms (`8` for state and `1` for update). Values such as `8junk` and
`1junk` do not produce recovery presentation fields, are rejected by the public
verification endpoint before PDO acquisition, and remain fail-closed for normal
lifecycle operations. Every normal operation now proves the exact HTTP/business
error (`400` and the stable Gate A message) and an unchanged failed candidate.

The v3 and v7 portable tests label their intentional frozen-source/architecture
assertions with `behavior-test-gate: static-rule`; v7 reads controller and route
files from this staging tree, never the clean authority host.

## Delivered

- Exact failed database-upgrade presentation uses a human recovery message and
  blocks ordinary install, uninstall, register, discard, and upload paths after
  the app is identified.
- `POST /app/sandpackage/install/verifyFailedUpgradeRecovery` is explicit,
  default-route-disabled, method-guarded, and restricted to `adminId === 1`.
- Verification uses a dedicated shared, read-only view of the pre-existing app
  operation lock. It neither creates lock/journal state nor calls candidate
  transaction recovery; ordinary lifecycle operations retain their existing
  exclusive lock and recovery behavior. It rechecks failed
  registry shape, candidate/root identity, private archive SHA-256, normalized
  candidate payload, recovery descriptor, `update.sql`, backup package,
  registration/deployment/runtime manifests, SemVer lineage, and host support.
- The verifier receives PDO only from `Db::connect('pgsql')->getPdo()`. A
  rollback-failed result closes the Think ORM connection and returns no retry
  authorization.
- The API returns only app/version/verdict/state/fingerprint/actions/message;
  assertion detail and `connection_reusable` stay internal. The verification
  audit contains exactly action, app, from/to versions, actor, failed stage,
  profile, verdict, evidence fingerprint, archive/payload/descriptor digests,
  and backup id; it contains no recovery state, update-script digest, catalog
  rows, query text, or paths.

## Gate B delivered

- A narrow legacy bridge accepts only the compiled host tuple `sand-iam / 0.6.0 -> 0.7.0 / sand_iam_060_to_070_v1` when no descriptor and no modern digest/replacement fields exist. Its synthetic verifier descriptor names exactly the two frozen states `baseline_060` and `prefix_033_034`; the verifier still selects a state only after the complete fixed catalog fingerprint, never from a relation count. It binds the exact failed registry tuple, complete old candidate tree (including `update.sql`), and verified backup registration/package/deployment/runtime evidence; mixed partial-modern state is rejected. The bridge offers only prepare, records `legacy_bridge=true` without fabricated archive/descriptor audit values, and is permanently unavailable after replacement writes modern markers.

- `prepareFailedUpgradeReplacement` accepts only a <=5 MB ZIP through the private upload store, runs complete preflight, binds exact app/from/to/profile/version plus canonical descriptor, normalized payload, archive, and update digests, and writes an opaque 0600 record without changing active candidate, backup, registry, or runtime.
- `replaceFailedUpgradeCandidate` is explicit POST/admin-only with server-derived `REPLACE {app}@{to}`. Under the app lock it recomputes the retained private ZIP SHA-256 and extracted payload/descriptor/update bindings before quarantine or move, repeats Gate A bindings, journals/fsyncs every rename, preserves the old candidate in quarantine, and leaves a retry-only failed candidate; generic lifecycle calls stay blocked.
- Replacement recovery converges a fresh production instance at transaction write/fsync/rename, old-quarantine, replacement-move, post-move info/check, and journal-unlink interruption boundaries; ambiguous evidence remains fail closed.
- `retryFailedUpgrade` first requires the exact durable replacement-ready registry shape without normalization or writes. Installed/update=0/wrong-failed-stage/missing-marker shapes reject with the fixed 400 before PDO or importer. It then repeats all bindings immediately before database work, imports only verified root `update.sql`, preserves failed state with a fresh diagnostic on SQL failure, deploys/registers only after success, and never reloads services automatically.
- The shared exact-ready predicate now requires all six modern markers: four
  lowercase 64-character SHA-256 values plus an opaque replacement id and
  `ready` state. List presentation and retry both additionally use the same
  read-only retained-record/package/descriptor evidence check. Missing or
  malformed digest markers, a missing descriptor, or a descriptor digest
  mismatch therefore present only `verification_required`/prepare and reject
  retry before PDO or importer.
- Prepare/replace/retry default routes are disabled and explicit POST routes are registered. Replace/retry audit records have tested exact allowlists: app/from/to/actor/failed stage/profile/verdict/fingerprint/archive+payload+descriptor digests/backup/confirmation result, plus replacement quarantine and candidate identity where applicable; they contain no local paths or raw catalog rows.

## Static and portable verification

All passed on 2026-09-07:

```text
php -l server/plugin/sandpackage/app/logic/InstallLogic.php
php -l server/plugin/sandpackage/app/logic/FailedUpgradeIdentityBinding.php
php -l server/plugin/sandpackage/app/logic/FailedUpgradeRecoveryVerifier.php
php -l server/plugin/sandpackage/app/controller/InstallController.php
php -l server/plugin/sandpackage/config/route.php
php -l tests/failed_upgrade_recovery_gate_a_contract.php
php -l tests/failed_upgrade_recovery_verifier_non_db_test.php
php -l tests/failed_upgrade_recovery_psr4_autoload_contract.php
php -l tests/fixtures/fresh_psr4_verify_autoload.php

php tests/failed_upgrade_recovery_gate_a_contract.php
php tests/failed_upgrade_recovery_legacy_bridge_contract.php
php tests/failed_upgrade_recovery_verifier_non_db_test.php
php tests/failed_upgrade_recovery_psr4_autoload_contract.php
php tests/backup-info-contract.php
php tests/backup-recovery-identity-contract.php
php tests/candidate-manifest-contract.php
php tests/database-failure-diagnostic-contract.php
php tests/discard-candidate-contract-v7.php
php tests/upload-diagnostic-crash-contract.php
php tests/upload-diagnostic-sanitize-contract.php
shasum -a 256 -c report/GATE_A_SHA256SUMS
```

The displayed lint evidence is the nine named current acceptance files above:
five SandPackage runtime/config files, three contract runners, and the fresh
subprocess fixture. It is not a claim that twenty PHP files were linted.

### Scoped staged frontend validation

The staged behavior harness now adds runtime drift -> real Controller-envelope
shape -> mounted `index.vue` refresh -> `verification_required` behavior,
including same-confirmation stable evidence and missing/contradictory envelope
rejection. It contains no client-only success/status aliases or fabricated
production fields.

This staging-only pass did not run the existing Vite build, behavior runner,
ESLint, or `vue-tsc`: the checked-in harness configuration imports
`/Users/code/project/sandadmin/sandadmin-artd` source/node_modules and writes
outside this staging scope. Installing dependencies or reading that authority
tree was out of scope. Frontend compilation/execution is therefore unverified,
not reported as passed.

The executable Gate A/B fixture instantiates the real `InstallLogic`, real
`InstallController`, real route file, and real `FailedUpgradeRecoveryVerifier`
under temporary filesystem/ORM/router/catalog adapters. It covers POST and
super-admin guards, exact failed-shape list fields, the exact response
whitelist, success/blocked/connection-unusable mappings, `pgsql` PDO
acquisition and Think connection close, all ordinary operation guards after app
identification, exact audit payload keys, and byte-identical
registry/candidate/journal evidence in all three verification outcomes. Its
malformed candidate journal proves that the verification lock does not invoke
transaction recovery.

The same fixture exercises Gate B through the real controller and production
public methods using the fixed PostgreSQL catalog adapter. It proves private
prepare record/rename/fsync interruption containment; replacement journal
write/fsync/rename plus old-quarantine/move/post-move-info/check/unlink
new-instance convergence; non-POST/nonmatching confirmations; prepared-payload
and retained-ZIP digest mismatch rejection with unchanged active evidence; a
separate-process operation lock; exact audit allowlists; retry preflight before
the importer; retry commit-boundary failure before deployment; exact malformed
replacement-state rejection before PDO/importer; and only `update.sql` in both
retry outcomes. It never opens, creates, or queries a real database.

It additionally covers ten modern retry-ready regressions through public
`InstallLogic::presentInfo` and the real retry controller: each of the four
digest markers missing, each malformed, a missing active descriptor, and an
inconsistent descriptor. Every case presents verification-required with no
retry action, returns the fixed retry rejection, and leaves registry, backup,
runtime, importer, and PDO acquisition unchanged.

`failed_upgrade_recovery_legacy_bridge_contract.php` starts independent fresh
temporary hosts after the shared fixture for both `baseline_060` and the
frozen 83-relation `prefix_033_034` catalog fixtures. Each creates a real
descriptor-less, marker-free v4 failed candidate and a complete v6 replacement
descriptor that declares both states, then drives controller list -> verify ->
prepare -> replace -> refreshed list -> retry. Each flow proves verify returns
only prepare, the refreshed list is exactly retry-safe/retry-only, and retry
installs 0.7 through root `update.sql` only. It also proves the six modern
marker fields independently reject through verify, prepare, and replace with
unchanged registry/candidate/backup/runtime hashes; prepare and replacement
durable interruptions converge under a new production instance; replacement
preserves quarantine and 0.6 backup evidence; and verify/replace/retry audit
payloads have exact path-free allowlists. The bridge becomes unavailable once
modern ready markers are written.

The staged `index.vue` behavior harness uses the same marker shape as a
defensive UI gate. A list row claiming `retry_safe` cannot render retry unless
it has the exact retry-only action, tuple, all four lowercase digest markers,
opaque replacement id, and `ready` state. The mounted component fixture
refreshes from both a partial-modern and an uppercase-digest row and observes
that stale retry actions are removed and verification is shown instead.

`report/GATE_A_SHA256SUMS` records the hashes of the reviewed Gate A contract,
production implementation (including every new runtime FQCN), every portable
executable test and its fresh-process fixture, the production sync checklist, the three
helper/mock/behavior frontend files named above, and staged `index.vue`; the report itself is excluded to avoid a
self-referential digest. The digest entries use the standard two-space
`shasum` format; `shasum -c` also emits the known three
format warnings for the human-readable comment lines at the top of the
manifest.

The non-DB verifier v3 fixture exercises exact `baseline_060` and
`prefix_033_034`, mutation/hybrid blocking, immutable binding mismatch,
fixed-query read-only transaction behavior, and rollback-failed connection
handling. The final portable v22 suite passed against the current Gate A code:
`backup-info-contract.php`, `backup-recovery-identity-contract.php`,
`candidate-manifest-contract.php`, `database-failure-diagnostic-contract.php`,
`discard-candidate-contract-v7.php`, `upload-diagnostic-crash-contract.php`,
and `upload-diagnostic-sanitize-contract.php`. Their retained v7/v12-v17 file
labels are historical; there are no separately named v18-v22 test files.

## Remaining Gate B / FLOW gates

`ACT-RECOVERY-035` is the single read-only cross-boundary contract for the
catalog admission boundary: it fails unless all `retry_safe` verifier outcomes
also have the actual migration 035 predicate at `30/30`.

1. No real PostgreSQL integration was run: the adapter proves the fixed
   verifier protocol, not a disposable database execution. Database creation
   is not authorized in this task.
2. No authority host, demo host, registry/service, browser, frontend build, or deployment
   acceptance was run or changed. Real post-retry runtime/UI validation remains
   a later authorized business-loop step.
