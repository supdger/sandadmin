<?php

declare(strict_types=1);

namespace Sandadmin\Http;

// 演示探针：模拟漏进生产包的免登录入口，供 check-no-dev-login 断言判红
final class DevLoginProbe
{
    public static function entryPath(): string
    {
        return '/dev-login';
    }
}
