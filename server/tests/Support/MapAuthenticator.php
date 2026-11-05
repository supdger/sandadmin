<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Auth\Authenticator;
use Sandadmin\Domain\Exception\UnauthenticatedException;

final class MapAuthenticator implements Authenticator
{
    /** @param array<string, AuthenticatedUser> $users */
    public function __construct(private readonly array $users)
    {
    }

    public function authenticate(string $credential): AuthenticatedUser
    {
        $user = $this->users[$credential] ?? null;
        if (!$user instanceof AuthenticatedUser) {
            throw new UnauthenticatedException();
        }

        return $user;
    }
}
