<?php

declare(strict_types=1);

// v2 replaces the retired candidate-specific v1 bridge contracts. The two
// neutral behavior suites exercise the public parser/executor contracts.
require dirname(__DIR__) . '/server/tests/SandPackage/PostgresLifecycleSqlExecutorTest.php';
require dirname(__DIR__) . '/server/tests/SandPackage/FailedUpgradeRecoveryV2Test.php';
