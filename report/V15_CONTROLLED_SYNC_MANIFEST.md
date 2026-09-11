# SandIAM 0.7.0 v15 controlled-sync manifest

Status: **proposal only / no synchronization authorized or performed**.
This is a review map, not an installer, migration, upload, registry action, or
runtime instruction. It does not authorize a database write, service reload,
candidate replacement, demo-host mutation, commit, push, or deployment.

## Immutable v15 evidence

- Review artifact: `.artifacts/sand-iam-0.7.0-v15-20260907T153247Z/`.
- ZIP: `sand-iam-0.7.0-v15-20260907T153247Z-candidate-dirty-not-release.zip`;
  SHA-256 `8b731b17c310c086b9af8115e8752f4b5e22ab2faca7b7785ff509f77c3a902b`;
  1,116,611 bytes; 594 entries; version remains `0.7.0`.
- Snapshot: 594 files; SHA-256
  `82cc4c26a1b3c87e45cc2b6b4e0a182028a5561024e7ae8e6a70c06304085a29`.
- Rebuild: same ZIP SHA-256, entry list and descriptor (bit-identical=true).
- Descriptor (root/plugin mirrored): SHA-256
  `5b4340e914e6339bbd2a94058eacafde98e5ee81ca3df008f98a0cc406f9159f`.
- Descriptor-excluded payload: 592 files; SHA-256
  `fcaec618c0a3d8bf95e93d9dc2a6d69f70c086e5bae3fe8df461612af69dd5d8`.
- `update.sql` (root/plugin mirrored): SHA-256
  `84f4380224725c9b0e5f2319f296ce2d39f07984e2611e8b31d7eecab995c06e`.
- `035_schema_migration_ledger.pgsql` (root/plugin mirrored): SHA-256
  `c8402554c787066fb17a77e0e6bbff6ab89cbd36550e61c000f893877bc99bb6`.
- Authority checker: `22/22`; ACT-RECOVERY-035: one `retry_safe` state and
  actual 035 adoption predicate `30/30` in PostgreSQL 18 READ ONLY mode.

## Exact proposed map and observed demo preimage

| Order | Source (reviewed) | Proposed target | Observed target preimage | Required post-copy hash |
| --- | --- | --- | --- | --- |
| 1 | `sand-iam/migrations/035_schema_migration_ledger.pgsql` | Candidate package `migrations/035_schema_migration_ledger.pgsql` | no standalone active demo source target; package extraction only | `c8402554…99bb6` |
| 2 | `sand-iam/plugin/sand-iam/migrations/035_schema_migration_ledger.pgsql` | Candidate package `plugin/sand-iam/migrations/035_schema_migration_ledger.pgsql` | active runtime copy is not a sync target | `c8402554…99bb6` |
| 3 | `sand-iam/update.sql` | Candidate package `update.sql` | `sandadmin-demo-host/plugins/sand-iam/update.sql`: `989c7d844458191622372bec1e9b6dc23a4141717e17ba9e7af7a6e188d6c0fb` | `84f43802…5c06e` |
| 4 | `sand-iam/plugin/sand-iam/update.sql` | Candidate package `plugin/sand-iam/update.sql` | no direct active demo path; verify archive entry | `84f43802…5c06e` |
| 5 | `sand-iam/recovery/failed-upgrade.v1.json` | Candidate package `recovery/failed-upgrade.v1.json` | absent as direct demo plugin file | `5b4340e9…9159f` |
| 6 | `sand-iam/plugin/sand-iam/recovery/failed-upgrade.v1.json` | Candidate package `plugin/sand-iam/recovery/failed-upgrade.v1.json` | absent as direct demo runtime file | `5b4340e9…9159f` |
| 7 | staging `FailedUpgradeRecoveryVerifier.php` | `sandadmin-demo-host/server/plugin/sandpackage/app/logic/FailedUpgradeRecoveryVerifier.php` | `9c9b8b38de254fd9ebde5eda952ab763cc4b6ea791f02f3dc9457d69bebe9561` | `85bda0e34f3a22c762f128815fb78e1f8d6920f789f6a2c1b3e1b767cdfbbc13` |
| 8 | staging `failed_upgrade_profiles.php` | `sandadmin-demo-host/server/plugin/sandpackage/config/failed_upgrade_profiles.php` | `e3500b55fabea9578ae757ec8d5960bb89ded0f966663e777ca16059b4113b5a` | `f15760167429cc52aba8f3202bcfdf6de9b79def1e730f7039b86bae49c07338` |

The v15 ZIP must be handled only through a separately authorized SandPackage
candidate workflow. This manifest intentionally does not invent its private
upload/store path or copy directly into `server/runtime`.

## Preconditions, sequence, and rollback anchors

1. Re-read every target above; stop if any preimage differs.
2. Verify v15 ZIP SHA, descriptor/payload/update binding, archive entries, and
   two-build reproducibility before any copy.
3. Under separate authorization, sync verifier and profile as one atomic
   review unit; verify their target hashes before any candidate operation.
4. Under separate authorization, use only the v15 ZIP candidate workflow;
   verify its internal root/plugin 035, update and descriptor entry hashes.
5. Run the read-only verifier and ACT-RECOVERY-035 again before any retry.
6. Rollback means restore only the captured, verified preimage hashes above
   under a new authorization. Never use v14 as a replacement candidate and
   never delete quarantine/backup evidence.

No current demo preimage was modified while collecting this manifest.
