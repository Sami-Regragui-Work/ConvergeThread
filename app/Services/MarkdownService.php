<?php

namespace App\Services;

use App\Support\HtmlAllowlistSanitizer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Chat markdown: CommonMark + GFM + popular extras, with safe HTML.
 *
 * Source stays markdown (needed for edits + E2EE). HTML is produced only for display.
 */
class MarkdownService
{
    private ?MarkdownConverter $converter = null;

    public function __construct(
        private readonly HtmlAllowlistSanitizer $sanitizer = new HtmlAllowlistSanitizer(),
    ) {
    }

    public function toHtml(string $markdown): string
    {
        $prepared = $this->preprocess($markdown);
        $html = (string) $this->converter()->convert($prepared);
        $html = $this->namespaceFootnotes($html);
        $html = $this->decorateStructure($html);

        return $this->sanitizer->sanitize($html);
    }

    /**
     * Cross-flavor extras CommonMark/GFM do not define.
     * Protects fenced/inline code first so spoilers inside code stay literal.
     */
    public function preprocess(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $slots = [];

        $protect = function (string $chunk) use (&$slots): string {
            $key = 'CTMDPROT'.count($slots).'ZZ';
            $slots[$key] = $chunk;

            return $key;
        };

        // Fenced code blocks
        $markdown = preg_replace_callback(
            '/^```[^\n]*\n[\s\S]*?^```/m',
            fn (array $m) => $protect($m[0]),
            $markdown,
        ) ?? $markdown;

        // Inline code
        $markdown = preg_replace_callback(
            '/`[^`\n]+`/',
            fn (array $m) => $protect($m[0]),
            $markdown,
        ) ?? $markdown;

        // GitHub-style alerts: > [!NOTE] followed by quoted lines
        $markdown = preg_replace_callback(
            '/^> \[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*\n((?:^>.*(?:\n|$))*)/mi',
            function (array $m) {
                $kind = strtolower($m[1]);
                $body = preg_replace('/^> ?/m', '', rtrim($m[2], "\n")) ?? '';

                return '<div class="ct-md-alert ct-md-alert-'.$kind.'">'."\n\n"
                    .'**'.ucfirst($kind)."**\n\n"
                    .$body
                    ."\n\n</div>\n\n";
            },
            $markdown,
        ) ?? $markdown;

        // Discord ||spoiler|| and Reddit >!spoiler!<
        $markdown = preg_replace_callback(
            '/\|\|(.+?)\|\|/s',
            fn (array $m) => $this->spoilerHtml($m[1]),
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/>!(.+?)!</s',
            fn (array $m) => $this->spoilerHtml($m[1]),
            $markdown,
        ) ?? $markdown;

        // Reddit-style superscript: ^(text) or ^word
        $markdown = preg_replace(
            '/\^\(([^)]+)\)/',
            '<sup>$1</sup>',
            $markdown,
        ) ?? $markdown;
        $markdown = preg_replace(
            '/\^([A-Za-z0-9_-]+)/',
            '<sup>$1</sup>',
            $markdown,
        ) ?? $markdown;

        // Pandoc-style underline ++text++ (CommonMark __ is bold)
        $markdown = preg_replace(
            '/\+\+([^+]+)\+\+/',
            '<u>$1</u>',
            $markdown,
        ) ?? $markdown;

        // CommonMark: nested OL under `1. ` needs ≥3 spaces; 2-space nests flatten.
        $markdown = $this->normalizeNestedListIndents($markdown);

        return strtr($markdown, $slots);
    }

    /**
     * Bump list lines that are indented under a parent but not deep enough for CommonMark nesting.
     * Example: "1. a\n  1. b" → "1. a\n   1. b" so the second item nests and restarts.
     */
    public function normalizeNestedListIndents(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $guard = 0;

        do {
            $changed = false;
            $stack = []; // [indent, contentCol]

            foreach ($lines as $i => $line) {
                if (! preg_match('/^(\s*)([-*+]|\d+\.)(\s+)(.*)$/', $line, $m)) {
                    if (! preg_match('/^\s*$/', $line)) {
                        $stack = [];
                    }
                    continue;
                }

                $indent = strlen(str_replace("\t", '  ', $m[1]));
                $markerWidth = strlen($m[2]) + strlen($m[3]);

                while ($stack !== [] && $indent <= $stack[array_key_last($stack)]['indent']) {
                    array_pop($stack);
                }

                if ($stack !== []) {
                    $need = $stack[array_key_last($stack)]['contentCol'];
                    $parentIndent = $stack[array_key_last($stack)]['indent'];
                    if ($indent > $parentIndent && $indent < $need) {
                        $lines[$i] = str_repeat(' ', $need).$m[2].$m[3].$m[4];
                        $indent = $need;
                        $changed = true;
                    }
                }

                $stack[] = [
                    'indent' => $indent,
                    'contentCol' => $indent + $markerWidth,
                ];
            }

            $guard++;
        } while ($changed && $guard < 20);

        return implode("\n", $lines);
    }

    private function spoilerHtml(string $inner): string
    {
        $inner = trim($inner);
        // Keep markdown inside spoilers by not escaping here — CommonMark will parse
        // the surrounding HTML block awkwardly; use a span with escaped text for safety
        // when content has no markdown intent. Prefer escaped plain text for spoilers.
        $safe = e($inner);

        return '<span class="ct-md-spoiler" data-ct-spoiler="1" role="button" tabindex="0" aria-label="Spoiler (click to reveal)" title="Click to reveal">'.$safe.'</span>';
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'external_link' => [
                'internal_hosts' => [],
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new DescriptionListExtension());
        $environment->addExtension(new HighlightExtension());
        $environment->addExtension(new ExternalLinkExtension());

        return $this->converter = new MarkdownConverter($environment);
    }

    private function namespaceFootnotes(string $html): string
    {
        $prefix = 'ctfn'.substr(hash('sha256', $html.microtime(true).random_int(0, PHP_INT_MAX)), 0, 10);

        return str_replace(
            ['id="fn:', 'id="fnref:', 'href="#fn:', 'href="#fnref:'],
            ['id="'.$prefix.'fn:', 'id="'.$prefix.'fnref:', 'href="#'.$prefix.'fn:', 'href="#'.$prefix.'fnref:'],
            $html,
        );
    }

    private function decorateStructure(string $html): string
    {
        $html = preg_replace('/<(h[1-6])(\s|>)/', '<$1 class="ct-md-h"$2', $html) ?? $html;
        $html = preg_replace('/<(ul|ol)(\s|>)/', '<$1 class="ct-md-list"$2', $html) ?? $html;
        $html = preg_replace('/<a(\s)/', '<a class="ct-md-link"$1', $html) ?? $html;
        $html = preg_replace('/<code(?![^>]*class=)/', '<code class="ct-md-code"', $html) ?? $html;
        $html = preg_replace(
            '/<pre>/',
            '<div class="ct-md-codeblock"><pre class="ct-md-pre">',
            $html,
        ) ?? $html;
        $html = preg_replace('/<\/pre>/', '</pre></div>', $html) ?? $html;
        $html = preg_replace(
            '/<table>/',
            '<div class="ct-md-table-wrap"><table class="ct-md-table">',
            $html,
        ) ?? $html;
        $html = preg_replace('/<\/table>/', '</table></div>', $html) ?? $html;

        return $html;
    }
}
