<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

/**
 * 工作台读接口在还没有待办、动态和提示时返回空列表。
 * 列表字段必须是数组：前端对空对象调用 filter 会把整页打崩。
 */
final class DashboardRead
{
    /**
     * @return array{done: bool, steps: list<array{key: string, done: bool}>}
     */
    public static function onboarding(): array
    {
        return ['done' => true, 'steps' => []];
    }

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    public static function summary(): array
    {
        return ['cards' => []];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function todos(): array
    {
        return ['items' => [], 'total' => 0];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public static function allTodos(int $page, int $pageSize): array
    {
        return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * @return array{charts: list<array<string, mixed>>}
     */
    public static function charts(): array
    {
        return ['charts' => []];
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public static function activities(): array
    {
        return ['items' => []];
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public static function notices(): array
    {
        return ['items' => []];
    }

    /**
     * @return array{mode: string, items: list<array<string, mixed>>}
     */
    public static function shortcuts(): array
    {
        return ['mode' => 'auto', 'items' => []];
    }
}
