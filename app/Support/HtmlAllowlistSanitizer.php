<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist HTML sanitizer for markdown output (defense-in-depth after CommonMark).
 */
final class HtmlAllowlistSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup',
        'del', 'details', 'div', 'dl', 'dt', 'dd', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'ol', 'p', 'pre', 'q', 's', 'samp',
        'small', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot',
        'th', 'thead', 'tr', 'u', 'ul', 'var', 'input',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'rel', 'target', 'class', 'id', 'role'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
        'input' => ['type', 'checked', 'disabled', 'class'],
        'td' => ['align', 'colspan', 'rowspan', 'class'],
        'th' => ['align', 'colspan', 'rowspan', 'class', 'scope'],
        'col' => ['span', 'class'],
        'colgroup' => ['span', 'class'],
        'ol' => ['start', 'type', 'class'],
        'ul' => ['class'],
        'li' => ['class'],
        'code' => ['class'],
        'pre' => ['class'],
        'span' => ['class', 'title', 'role', 'tabindex', 'aria-label', 'data-ct-spoiler'],
        'div' => ['class', 'role', 'id'],
        'blockquote' => ['class'],
        'details' => ['class', 'open'],
        'summary' => ['class'],
        'table' => ['class'],
        'thead' => ['class'],
        'tbody' => ['class'],
        'tfoot' => ['class'],
        'tr' => ['class'],
        'p' => ['class'],
        'h1' => ['class', 'id'],
        'h2' => ['class', 'id'],
        'h3' => ['class', 'id'],
        'h4' => ['class', 'id'],
        'h5' => ['class', 'id'],
        'h6' => ['class', 'id'],
        'sup' => ['class', 'id', 'role'],
        'mark' => ['class'],
        'del' => ['class'],
        'u' => ['class'],
        'strong' => ['class'],
        'em' => ['class'],
        'br' => ['class'],
        'hr' => ['class'],
        'dl' => ['class'],
        'dt' => ['class'],
        'dd' => ['class'],
        '*' => ['class', 'id', 'role', 'aria-label', 'aria-hidden', 'title'],
    ];

    /** @var list<string> */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto'];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="ct-root">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('ct-root');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        $child = $parent->firstChild;
        while ($child) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $firstMoved = $child->firstChild;
                    while ($child->firstChild) {
                        $parent->insertBefore($child->firstChild, $child);
                    }
                    $parent->removeChild($child);
                    $child = $firstMoved ?: $next;

                    continue;
                }

                $this->sanitizeElement($child);
                $this->cleanChildren($child);

                if ($tag === 'input' && strtolower($child->getAttribute('type')) !== 'checkbox') {
                    $parent->removeChild($child);
                    $child = $next;

                    continue;
                }
            }

            $child = $next;
        }
    }

    private function sanitizeElement(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $allowed = array_values(array_unique(array_merge(
            self::ALLOWED_ATTRS['*'] ?? [],
            self::ALLOWED_ATTRS[$tag] ?? [],
        )));

        $toRemove = [];
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->name);
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $toRemove[] = $attr->name;

                continue;
            }

            $value = $attr->value;
            if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($value)) {
                $toRemove[] = $attr->name;

                continue;
            }

            if ($name === 'target' && $value !== '_blank' && $value !== '_self') {
                $toRemove[] = $attr->name;
            }
        }

        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $rel = trim($el->getAttribute('rel').' noopener noreferrer');
            $parts = array_unique(array_filter(preg_split('/\s+/', $rel) ?: []));
            $el->setAttribute('rel', implode(' ', $parts));
            if (! $el->hasAttribute('target')) {
                $el->setAttribute('target', '_blank');
            }
        }

        if ($tag === 'input') {
            $el->setAttribute('disabled', 'disabled');
            $el->setAttribute('type', 'checkbox');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        if (preg_match('#^(data|javascript|vbscript):#i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (! isset($parts['scheme'])) {
            return ! str_starts_with($url, '//');
        }

        return in_array(strtolower($parts['scheme']), self::SAFE_SCHEMES, true);
    }
}
