<?php
declare(strict_types=1);

/**
 * Regression contract for PostgreSQL role-menu permission writes.
 */

function roleMenuPermissionExpect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[PASS] {$message}\n";
}

$logicFile = dirname(__DIR__, 2) . '/plugin/sandadmin/app/logic/system/SystemRoleLogic.php';
$logic = file_get_contents($logicFile);

roleMenuPermissionExpect($logic !== false, 'reads SystemRoleLogic source');
roleMenuPermissionExpect(
    str_contains($logic, "\$role->menus()->attach(array_map('intval', \$menu_ids));"),
    'writes role-menu rows through the typed pivot relation',
);
roleMenuPermissionExpect(
    !str_contains($logic, "Db::name('sand_system_role_menu')->limit(100)->insertAll"),
    'does not use PostgreSQL-incompatible UNION ALL bulk parameters',
);
roleMenuPermissionExpect(
    str_contains($logic, 'UserAuthCache::cacheConfig()'),
    'uses the shared cache fallback instead of a missing legacy config key',
);

echo "SandAdmin role menu permission contract passed\n";
