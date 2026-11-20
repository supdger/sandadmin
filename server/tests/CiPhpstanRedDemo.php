<?php

declare(strict_types=1);

// CI 门禁演示用，故意引入 PHPStan 错误，PR 关闭即弃。
// 未定义函数调用在 level max 下必报 function.notFound。

/** @return int */
function ciPhpstanRedDemo11a(): int
{
    return ciUndefinedFunction11a('x');
}
