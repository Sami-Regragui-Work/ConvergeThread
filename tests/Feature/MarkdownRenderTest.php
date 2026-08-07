<?php

namespace Tests\Feature;

use App\Services\MarkdownService;
use App\Support\HtmlAllowlistSanitizer;
use Tests\TestCase;

class MarkdownRenderTest extends TestCase
{
    public function test_renders_gfm_and_cross_platform_extras(): void
    {
        $md = <<<'MD'
**bold** *italic* ~~strike~~ ==mark== ++under++ ^sup

>!reddit spoiler!< and ||discord spoiler||

- [x] done
- [ ] todo

| a | b |
| - | - |
| 1 | 2 |

Term
: Definition

Note[^1]

[^1]: Footnote body

> [!WARNING]
> Be careful

<details><summary>More</summary>Hidden</details>
MD;

        $html = app(MarkdownService::class)->toHtml($md);

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<del>strike</del>', $html);
        $this->assertStringContainsString('<mark>mark</mark>', $html);
        $this->assertStringContainsString('<u>under</u>', $html);
        $this->assertStringContainsString('<sup>sup</sup>', $html);
        $this->assertStringContainsString('ct-md-spoiler', $html);
        $this->assertStringContainsString('reddit spoiler', $html);
        $this->assertStringContainsString('discord spoiler', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<dt>Term</dt>', $html);
        $this->assertStringContainsString('footnote', strtolower($html));
        $this->assertStringContainsString('ct-md-alert-warning', $html);
        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<summary>More</summary>', $html);
    }

    public function test_strips_dangerous_html(): void
    {
        $html = app(MarkdownService::class)->toHtml(
            '<img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><div onclick="alert(1)">y</div><script>alert(1)</script>'
        );

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_sanitizer_allows_safe_tags_only(): void
    {
        $out = (new HtmlAllowlistSanitizer())->sanitize(
            '<p>ok</p><iframe src="https://evil.test"></iframe><a href="https://example.com">link</a>'
        );

        $this->assertStringContainsString('<p>ok</p>', $out);
        $this->assertStringContainsString('https://example.com', $out);
        $this->assertStringNotContainsString('iframe', $out);
    }

    public function test_nested_ordered_list_with_two_space_indent_still_nests(): void
    {
        $html = app(MarkdownService::class)->toHtml("1. parent\n  1. nested\n  2. nested2\n2. parent2");

        $this->assertMatchesRegularExpression('/<ol[^>]*>\s*<li>parent\s*<ol/s', $html);
        $this->assertStringContainsString('<li>nested</li>', $html);
        $this->assertStringContainsString('<li>nested2</li>', $html);
        $this->assertStringContainsString('<li>parent2</li>', $html);
        // Must not flatten all four into one list level
        $this->assertSame(2, substr_count($html, '<ol'));
    }

    public function test_spoilers_ignored_inside_code(): void
    {
        $html = app(MarkdownService::class)->toHtml("`||not a spoiler||`\n\n```\n>!code!<\n```");

        $this->assertStringNotContainsString('data-ct-spoiler', $html);
    }
}
