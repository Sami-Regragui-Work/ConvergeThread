{{-- Chat markdown: CommonMark/GFM + extras; DOMPurify sanitize; highlight.js for ```lang --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/styles/github-dark.min.css">
<script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.5/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js"></script>
<script>
(function () {
    const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup',
        'del', 'details', 'div', 'dl', 'dt', 'dd', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'ol', 'p', 'pre', 'q', 's', 'samp',
        'small', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot',
        'th', 'thead', 'tr', 'u', 'ul', 'var', 'input',
    ];
    const ALLOWED_ATTR = [
        'href', 'title', 'rel', 'target', 'class', 'id', 'role', 'src', 'alt', 'width', 'height',
        'type', 'checked', 'disabled', 'align', 'colspan', 'rowspan', 'scope', 'span', 'start',
        'tabindex', 'aria-label', 'aria-hidden', 'data-ct-spoiler', 'data-highlighted', 'open',
    ];

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const spoilerHtml = (inner) =>
        '<span class="ct-md-spoiler" data-ct-spoiler="1" role="button" tabindex="0" aria-label="Spoiler (click to reveal)" title="Click to reveal">'
        + escapeHtml(String(inner).trim())
        + '</span>';

    function preprocess(markdown) {
        let text = String(markdown ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const slots = [];
        const protect = (chunk) => {
            const key = 'CTMDPROT' + slots.length + 'ZZ';
            slots.push({ key, chunk });
            return key;
        };

        text = text.replace(/^```[^\n]*\n[\s\S]*?^```/gm, (m) => protect(m));
        text = text.replace(/`[^`\n]+`/g, (m) => protect(m));

        text = text.replace(
            /^> \[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*\n((?:^>.*(?:\n|$))*)/gim,
            (_, kind, body) => {
                const k = String(kind).toLowerCase();
                const cleaned = String(body).replace(/^> ?/gm, '').replace(/\n$/, '');
                return '<div class="ct-md-alert ct-md-alert-' + k + '">\n\n**'
                    + k.charAt(0).toUpperCase() + k.slice(1)
                    + '**\n\n' + cleaned + '\n\n</div>\n\n';
            }
        );

        text = text.replace(/\|\|([\s\S]+?)\|\|/g, (_, inner) => spoilerHtml(inner));
        text = text.replace(/>!([\s\S]+?)!</g, (_, inner) => spoilerHtml(inner));
        text = text.replace(/\^\(([^)]+)\)/g, '<sup>$1</sup>');
        text = text.replace(/\^([A-Za-z0-9_-]+)/g, '<sup>$1</sup>');
        text = text.replace(/\+\+([^+]+)\+\+/g, '<u>$1</u>');
        text = text.replace(/==([^=]+)==/g, '<mark>$1</mark>');

        const defs = {};
        text = text.replace(/^\[\^([^\]]+)\]:\s*(.*)$/gm, (_, id, body) => {
            defs[id] = body;
            return '';
        });
        const fnPrefix = 'ctfn' + Math.random().toString(36).slice(2, 8);
        text = text.replace(/\[\^([^\]]+)\]/g, (_, id) => {
            if (!Object.prototype.hasOwnProperty.call(defs, id)) return '[^' + id + ']';
            return '<sup class="footnote-ref" id="' + fnPrefix + 'fnref:' + id
                + '"><a href="#' + fnPrefix + 'fn:' + id + '">' + escapeHtml(id) + '</a></sup>';
        });
        const fnIds = Object.keys(defs);
        if (fnIds.length) {
            text += '\n\n<div class="footnotes"><hr><ol>';
            fnIds.forEach((id) => {
                text += '<li class="footnote" id="' + fnPrefix + 'fn:' + id + '">'
                    + escapeHtml(defs[id])
                    + ' <a href="#' + fnPrefix + 'fnref:' + id + '">↩</a></li>';
            });
            text += '</ol></div>\n';
        }

        text = text.replace(
            /^([^\n:]+)\n: (.+)$/gm,
            '<dl><dt>$1</dt><dd>$2</dd></dl>'
        );

        text = normalizeNestedListIndents(text);

        slots.forEach(({ key, chunk }) => {
            text = text.split(key).join(chunk);
        });
        return text;
    }

    function normalizeNestedListIndents(markdown) {
        const lines = String(markdown).split('\n');
        let guard = 0;
        let changed = false;
        do {
            changed = false;
            const stack = [];
            for (let i = 0; i < lines.length; i++) {
                const m = lines[i].match(/^(\s*)([-*+]|\d+\.)(\s+)(.*)$/);
                if (!m) {
                    if (!/^\s*$/.test(lines[i])) stack.length = 0;
                    continue;
                }
                let indent = m[1].replace(/\t/g, '  ').length;
                const markerWidth = m[2].length + m[3].length;
                while (stack.length && indent <= stack[stack.length - 1].indent) stack.pop();
                if (stack.length) {
                    const need = stack[stack.length - 1].contentCol;
                    const parentIndent = stack[stack.length - 1].indent;
                    if (indent > parentIndent && indent < need) {
                        lines[i] = ' '.repeat(need) + m[2] + m[3] + m[4];
                        indent = need;
                        changed = true;
                    }
                }
                stack.push({ indent, contentCol: indent + markerWidth });
            }
            guard += 1;
        } while (changed && guard < 20);
        return lines.join('\n');
    }

    function decorate(html) {
        let out = String(html);
        out = out.replace(/<(h[1-6])(\s|>)/g, '<$1 class="ct-md-h"$2');
        out = out.replace(/<(ul|ol)(\s|>)/g, '<$1 class="ct-md-list"$2');
        out = out.replace(/<a(\s)/g, '<a class="ct-md-link"$1');
        out = out.replace(/<code(?![^>]*class=)/g, '<code class="ct-md-code"');
        out = out.replace(/<pre>/g, '<div class="ct-md-codeblock"><pre class="ct-md-pre">');
        out = out.replace(/<\/pre>/g, '</pre></div>');
        out = out.replace(/<table>/g, '<div class="ct-md-table-wrap"><table class="ct-md-table">');
        out = out.replace(/<\/table>/g, '</table></div>');
        return out;
    }

    function namespaceFootnotes(html) {
        const prefix = 'ctfn' + Math.random().toString(36).slice(2, 10);
        return String(html)
            .replace(/id="fn:/g, 'id="' + prefix + 'fn:')
            .replace(/id="fnref:/g, 'id="' + prefix + 'fnref:')
            .replace(/href="#fn:/g, 'href="#' + prefix + 'fn:')
            .replace(/href="#fnref:/g, 'href="#' + prefix + 'fnref:');
    }

    function sanitize(html) {
        if (window.DOMPurify) {
            return window.DOMPurify.sanitize(html, {
                ALLOWED_TAGS,
                ALLOWED_ATTR,
                ALLOW_DATA_ATTR: true,
                ADD_ATTR: ['target', 'disabled', 'checked'],
            });
        }
        return '<p>' + escapeHtml(html).replace(/\n/g, '<br>') + '</p>';
    }

    /** Language label + highlight.js coloring for fenced blocks. */
    function enhanceMarkdownHtml(html) {
        if (!html) return '';
        const wrap = document.createElement('div');
        wrap.innerHTML = html;

        wrap.querySelectorAll('pre code').forEach((code) => {
            const pre = code.parentElement;
            if (!pre || pre.tagName !== 'PRE') return;

            let block = pre.parentElement;
            if (!block || !block.classList.contains('ct-md-codeblock')) {
                block = document.createElement('div');
                block.className = 'ct-md-codeblock';
                pre.parentNode.insertBefore(block, pre);
                block.appendChild(pre);
            }

            pre.classList.add('ct-md-pre');

            const langMatch = String(code.className || '').match(/(?:^|\s)language-([a-z0-9_+#-]+)/i);
            if (langMatch && !block.querySelector('.ct-md-lang')) {
                const label = document.createElement('div');
                label.className = 'ct-md-lang';
                label.textContent = langMatch[1].toLowerCase();
                block.insertBefore(label, pre);
            }

            if (window.hljs && code.dataset.highlighted !== 'yes') {
                try {
                    window.hljs.highlightElement(code);
                } catch (e) {
                    // Unknown language → leave plain
                }
            }
        });

        return wrap.innerHTML;
    }

    function configureMarked() {
        if (!window.marked || window.__ctMarkedReady) return;
        window.marked.setOptions({
            gfm: true,
            breaks: false,
            pedantic: false,
        });
        if (typeof window.marked.use === 'function') {
            window.marked.use({
                renderer: {
                    link({ href, title, text }) {
                        const t = title ? ' title="' + escapeHtml(title) + '"' : '';
                        return '<a href="' + escapeHtml(href || '') + '"' + t
                            + ' target="_blank" rel="noopener noreferrer">' + text + '</a>';
                    },
                    code({ text, lang }) {
                        const language = (lang || '').trim().split(/\s+/)[0];
                        const className = language
                            ? ' class="language-' + escapeHtml(language) + '"'
                            : '';
                        return '<pre><code' + className + '>' + escapeHtml(text) + '</code></pre>\n';
                    },
                },
            });
        }
        window.__ctMarkedReady = true;
    }

    window.ctEnhanceMarkdownHtml = enhanceMarkdownHtml;

    window.ctRenderMarkdown = function (input) {
        const prepared = preprocess(input);
        configureMarked();

        let html;
        if (window.marked && typeof window.marked.parse === 'function') {
            html = window.marked.parse(prepared, { async: false });
        } else {
            html = '<p>' + escapeHtml(prepared).replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
        }

        html = namespaceFootnotes(html);
        html = decorate(html);
        html = sanitize(html);
        return enhanceMarkdownHtml(html);
    };

    document.addEventListener('click', (e) => {
        const el = e.target.closest?.('[data-ct-spoiler]');
        if (!el) return;
        el.classList.toggle('is-revealed');
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const el = e.target.closest?.('[data-ct-spoiler]');
        if (!el) return;
        e.preventDefault();
        el.classList.toggle('is-revealed');
    });
})();
</script>
