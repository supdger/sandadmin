# SandPackage failed-upgrade recovery v1.2.3 production sync checklist

This staging-only checklist authorizes no synchronization. It identifies the
exact files that a separately authorized production sync must copy together.
The target host must already retain Composer's PSR-4 mapping
`plugin\\sandpackage\\ => plugin/sandpackage`; no Composer metadata change is
required for this repair.

## Runtime files (all required)

- `server/plugin/sandpackage/app/logic/InstallLogic.php`
- `server/plugin/sandpackage/app/logic/FailedUpgradeRecoveryVerifier.php`
- `server/plugin/sandpackage/app/logic/FailedUpgradeIdentityBinding.php` **new**
- `server/plugin/sandpackage/app/controller/InstallController.php`
- `server/plugin/sandpackage/config/failed_upgrade_profiles.php`
- `server/plugin/sandpackage/config/route.php`

The new `FailedUpgradeIdentityBinding.php` file is mandatory. Copying only the
updated verifier or logic recreates the PSR-4 failure because the host will
look for the class-named file.

## Staged management UI payload

- `sandadmin-artd/src/views/plugin/sandpackage/install/index.vue`
- `sandadmin-artd/src/views/plugin/sandpackage/install/failed-upgrade-recovery.ts`
- `sandadmin-artd/src/views/plugin/sandpackage/install/failed-upgrade-recovery.http-mock.ts`
- `sandadmin-artd/src/views/plugin/sandpackage/install/failed-upgrade-recovery.behavior.ts`

## Contract, verification, and retained test evidence

- `docs/FAILED_UPGRADE_RECOVERY_CONTRACT.md`
- `report/GATE_A_VERIFICATION.md`
- `report/GATE_A_SHA256SUMS`
- this checklist
- `tests/failed_upgrade_recovery_gate_a_contract.php`
- `tests/failed_upgrade_recovery_legacy_bridge_contract.php`
- `tests/failed_upgrade_recovery_verifier_non_db_test.php`
- `tests/failed_upgrade_recovery_035_boundary_read_only.php`
- `tests/failed_upgrade_recovery_psr4_autoload_contract.php`
- `tests/fixtures/fresh_psr4_verify_autoload.php`
- the seven retained portable contract fixtures already listed in
  `report/GATE_A_SHA256SUMS`

Before a host sync, confirm the target host's Composer autoload mapping and run
the new fresh-process contract against that host's `vendor/autoload.php`. After
sync, a separately authorized runtime acceptance must invoke the official
verify endpoint once and preserve the stop rules for candidate, registry,
database, service, and browser actions.

P0/P1 catalog changes are one controlled sync unit: verifier/profile plus the
rebuilt SandIAM root/plugin 035 migration, lifecycle payloads and mirrored
descriptor from the reviewed candidate. Recompute target hashes before any
separately authorized copy; this checklist grants no sync authorization.
