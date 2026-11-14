<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\Search\SearchService;

/** 全局搜索：用户组链接指到 /system/user?keyword=（UsersPage 读 keyword 参数）。 */
final class SearchServiceTest extends ToolsCase
{
    public function testUserResultLinkUsesKeywordParam(): void
    {
        $actor = $this->userActor(['system:user:index']);
        $this->conn->executeStatement('UPDATE sand_system_user SET username = ? WHERE id = ?', ['testadmin', $actor->userId]);

        $service = new SearchService($this->conn);
        $groups = $service->search($actor, 'testadmin', 'user')['groups'];
        self::assertNotEmpty($groups);
        $group = $groups[0];
        self::assertSame('user', $group['type']);
        $item = $group['items'][0];
        self::assertSame('/system/user?keyword=testadmin', $item['link']);
    }

    public function testUserResultLinkFallsBackToNickname(): void
    {
        // 没有 username 的用户按昵称生成 keyword 参数
        $actor = $this->userActor(['system:user:index']);
        $service = new SearchService($this->conn);
        $groups = $service->search($actor, '测试管理', 'user')['groups'];
        self::assertNotEmpty($groups);
        $item = $groups[0]['items'][0] ?? null;
        self::assertIsArray($item);
        self::assertSame('/system/user?keyword=' . rawurlencode('测试管理员'), $item['link']);
    }
}
