<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/**
 * 公告富文本白名单净化：与前端展示侧同一白名单。
 * 未白名单的标签解包保留文本子节点；script/style/iframe 等连内容一起丢弃。
 * a 仅保留 href/target/rel，img 仅保留 src/alt；style/class 一律剥离；
 * 链接协议只允许 http/https/mailto，无协议（相对地址）视为允许。
 */
final class ContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'table', 'thead', 'tbody',
        'tr', 'td', 'th', 'span', 'a', 'img',
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'select'];

    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt'],
    ];

    private const ALLOWED_PROTOCOLS = ['http', 'https', 'mailto'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"?><body>' . $html . '</body>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return '';
        }
        self::walk($body);
        $html = $document->saveHTML($body);

        return is_string($html) ? trim($html) : '';
    }

    private static function walk(\DOMNode $node): void
    {
        if (!$node instanceof \DOMElement) {
            return;
        }
        // DOMNodeList 是动态视图，遍历时删节点要先快照
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                self::filter($child);
            }
        }
    }

    private static function filter(\DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($element);

            return;
        }
        self::filterAttributes($element, $tag);
        self::walk($element);
    }

    /** 解包：把子节点上提到父节点，标签本身丢弃。 */
    private static function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
        // 解包后新位置的子节点仍要过白名单
        self::walk($parent);
    }

    private static function filterAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
        $remove = [];
        foreach ($element->attributes as $attribute) {
            /** @var \DOMAttr $attribute */
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
            }
        }
        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
        if ($tag === 'a') {
            self::guardLink($element);
            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
        if ($tag === 'img') {
            self::guardImage($element);
        }
    }

    private static function guardLink(\DOMElement $element): void
    {
        $href = trim($element->getAttribute('href'));
        if ($href !== '' && !self::protocolAllowed($href, false)) {
            $element->removeAttribute('href');
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
        }
    }

    private static function guardImage(\DOMElement $element): void
    {
        $src = trim($element->getAttribute('src'));
        if ($src === '' || !self::protocolAllowed($src, true)) {
            $element->parentNode?->removeChild($element);
        }
    }

    /**
     * 无协议（站内相对路径）允许；mailto 只对 a 合法，img 只放 http/https。
     */
    private static function protocolAllowed(string $url, bool $isImage): bool
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $url) !== 1) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($isImage) {
            return in_array($scheme, ['http', 'https'], true);
        }

        return in_array($scheme, self::ALLOWED_PROTOCOLS, true);
    }
}
