# Retired failed-upgrade v1 tests

The former failed-upgrade tests embedded a named plugin's versions, tables, menu codes and migration sequence. They are retired with the host-side v1 bridge and profile file.

Replacement coverage is `failed_upgrade_recovery_v2_contract.php` and the neutral SandPackage behavior tests it invokes. They prove parser, SQL transaction, rollback, descriptor canonicalization, profile-vocabulary rejection and fixed catalog verification without reading production source or asserting a business plugin's catalog.
