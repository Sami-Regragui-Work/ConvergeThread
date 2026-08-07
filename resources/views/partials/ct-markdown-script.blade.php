{{-- Markdown renderer aligned with CommonMark/GFM hard vs soft breaks --}}
<script>
    window.ctRenderMarkdown = function (input) {
        if (input == null) return '';
        let text = String(input).replace(/\r\n/g, '\n');

        const escape = (s) => String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const blocks = [];
        text = text.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            const key = '%%CODEBLOCK' + blocks.length + '%%';
            const langClass = lang ? ' class="language-' + escape(lang) + '"' : '';
            const label = lang
                ? '<div class="ct-md-lang">' + escape(lang) + '</div>'
                : '';
            blocks.push(
                '<div class="ct-md-codeblock">' + label
                + '<pre class="ct-md-pre"><code' + langClass + '>'
                + escape(code.replace(/^\n/, '').replace(/\n$/, ''))
                + '</code></pre></div>'
            );
            return '\n' + key + '\n';
        });

        const lines = text.split('\n');
        const out = [];
        /** @type {{ type: 'ul'|'ol', level: number }[]} */
        let listStack = [];
        let paraParts = []; // { html, breakAfter: 'soft'|'hard'|null }

        const flushListsDeeperThan = (level) => {
            while (listStack.length && listStack[listStack.length - 1].level > level) {
                const top = listStack.pop();
                out.push(top.type === 'ol' ? '</ol>' : '</ul>');
            }
        };

        const flushAllLists = () => {
            while (listStack.length) {
                const top = listStack.pop();
                out.push(top.type === 'ol' ? '</ol>' : '</ul>');
            }
        };

        const openList = (type, level, startNum) => {
            flushListsDeeperThan(level);
            let top = listStack[listStack.length - 1];
            if (top && top.level === level && top.type !== type) {
                listStack.pop();
                out.push(top.type === 'ol' ? '</ol>' : '</ul>');
                top = listStack[listStack.length - 1];
            }
            if (!top || top.level < level) {
                listStack.push({ type, level });
                if (type === 'ol') {
                    out.push('<ol class="ct-md-list" start="' + startNum + '">');
                } else {
                    out.push('<ul class="ct-md-list">');
                }
            }
        };

        const flushPara = () => {
            if (!paraParts.length) return;
            let html = '<p>';
            paraParts.forEach((part, idx) => {
                html += part.html;
                if (idx < paraParts.length - 1) {
                    html += part.breakAfter === 'hard' ? '<br>' : ' ';
                }
            });
            html += '</p>';
            out.push(html);
            paraParts = [];
        };

        const inline = (line) => {
            let raw = line.replace(/(\\)$/, '').replace(/ {2}$/, '');
            let s = escape(raw);
            s = s.replace(/`([^`]+)`/g, '<code class="ct-md-code">$1</code>');
            s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
            s = s.replace(/(^|[^\*])\*([^*]+)\*(?!\*)/g, '$1<em>$2</em>');
            s = s.replace(/(^|[^_])_([^_]+)_(?!_)/g, '$1<em>$2</em>');
            s = s.replace(
                /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
                '<a href="$2" target="_blank" rel="noopener noreferrer" class="ct-md-link">$1</a>'
            );
            return s;
        };

        const hardBreakKind = (line) => {
            if (/\\$/.test(line)) return 'hard';
            if (/ {2}$/.test(line)) return 'hard';
            return 'soft';
        };

        const isTableSep = (line) => /^\s*\|?[\s:|-]+\|[\s:|-]*\|?\s*$/.test(line)
            && /-/.test(line)
            && line.includes('|');

        const isTableRow = (line) => {
            const t = line.trim();
            if (!t.includes('|')) return false;
            if (isTableSep(t)) return false;
            return /^\|?.+\|.+\|?$/.test(t);
        };

        const splitRow = (line) => {
            let t = line.trim();
            if (t.startsWith('|')) t = t.slice(1);
            if (t.endsWith('|')) t = t.slice(0, -1);
            return t.split('|').map((c) => c.trim());
        };

        const listLevel = (ws) => Math.floor((ws || '').replace(/\t/g, '  ').length / 2);

        const isSpecial = (line) => {
            if (/^%%CODEBLOCK\d+%%\s*$/.test(line.trim())) return true;
            if (/^#{1,3}\s+/.test(line)) return true;
            if (/^(\s*)[-*+]\s+/.test(line)) return true;
            if (/^(\s*)\d+\.\s+/.test(line)) return true;
            return false;
        };

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];

            if (/^%%CODEBLOCK\d+%%\s*$/.test(line.trim())) {
                flushPara();
                flushAllLists();
                out.push(line.trim().replace(/%%CODEBLOCK(\d+)%%/, (_, n) => blocks[Number(n)] || ''));
                continue;
            }

            if (isTableRow(line) && i + 1 < lines.length && isTableSep(lines[i + 1])) {
                flushPara();
                flushAllLists();
                const header = splitRow(line);
                i += 1;
                const body = [];
                while (i + 1 < lines.length && isTableRow(lines[i + 1])) {
                    i += 1;
                    body.push(splitRow(lines[i]));
                }
                let html = '<div class="ct-md-table-wrap"><table class="ct-md-table"><thead><tr>';
                header.forEach((cell) => { html += '<th>' + inline(cell) + '</th>'; });
                html += '</tr></thead><tbody>';
                body.forEach((row) => {
                    html += '<tr>';
                    header.forEach((_, idx) => {
                        html += '<td>' + inline(row[idx] ?? '') + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                out.push(html);
                continue;
            }

            const heading = line.match(/^(#{1,3})\s+(.*)$/);
            if (heading) {
                flushPara();
                flushAllLists();
                const level = heading[1].length;
                out.push('<h' + level + ' class="ct-md-h">' + inline(heading[2]) + '</h' + level + '>');
                continue;
            }

            const ul = line.match(/^(\s*)[-*+]\s+(.*)$/);
            if (ul) {
                flushPara();
                const level = listLevel(ul[1]);
                openList('ul', level, 1);
                out.push('<li>' + inline(ul[2]) + '</li>');
                continue;
            }

            const ol = line.match(/^(\s*)(\d+)\.\s+(.*)$/);
            if (ol) {
                flushPara();
                const level = listLevel(ol[1]);
                openList('ol', level, Number(ol[2]));
                out.push('<li>' + inline(ol[3]) + '</li>');
                continue;
            }

            if (/^\s*$/.test(line)) {
                flushPara();
                flushAllLists();
                continue;
            }

            flushAllLists();
            const kind = hardBreakKind(line);
            paraParts.push({ html: inline(line), breakAfter: kind });

            const next = lines[i + 1];
            if (next === undefined || /^\s*$/.test(next) || isSpecial(next)
                || (isTableRow(next) && i + 2 < lines.length && isTableSep(lines[i + 2]))) {
                flushPara();
            }
        }
        flushPara();
        flushAllLists();

        return out.join('\n');
    };
</script>
