<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class OptionItem implements \JsonSerializable
{
    public string $value;
    public string $label;

    public function __construct(string $value, string $label)
    {
        $this->value = ExtensionRules::optionValue($value);
        $this->label = ExtensionRules::optionLabel($label);
    }

    /** @return array{value: string, label: string} */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'label' => $this->label];
    }
}
