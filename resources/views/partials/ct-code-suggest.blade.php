{{-- Lightweight keyword/snippet suggestions for markdown fenced code (not full LSP/IntelliSense). --}}
<script>
(function () {
    const ALIASES = {
        js: 'javascript', javascript: 'javascript', mjs: 'javascript', cjs: 'javascript',
        ts: 'typescript', typescript: 'typescript',
        py: 'python', python: 'python',
        php: 'php',
        rb: 'ruby', ruby: 'ruby',
        go: 'go', golang: 'go',
        rs: 'rust', rust: 'rust',
        java: 'java',
        kt: 'kotlin', kotlin: 'kotlin',
        cs: 'csharp', csharp: 'csharp',
        c: 'c', h: 'c',
        cpp: 'cpp', cxx: 'cpp', cc: 'cpp', 'c++': 'cpp',
        sql: 'sql',
        html: 'html', htm: 'html',
        css: 'css', scss: 'css',
        sh: 'bash', bash: 'bash', zsh: 'bash', shell: 'bash',
        json: 'json',
        yaml: 'yaml', yml: 'yaml',
        md: 'markdown', markdown: 'markdown',
        xml: 'xml',
        plain: 'plain', text: 'plain',
    };

    const kw = (words) => words.map((w) => ({ label: w, insert: w, kind: 'keyword' }));
    const sn = (label, insert, detail) => ({ label, insert, kind: 'snippet', detail: detail || 'snippet' });

    const LANG = {
        javascript: [
            ...kw(['async', 'await', 'break', 'class', 'const', 'constructor', 'continue', 'debugger', 'default',
                'delete', 'else', 'export', 'extends', 'false', 'finally', 'for', 'from', 'function', 'if',
                'import', 'in', 'instanceof', 'let', 'new', 'null', 'of', 'return', 'static', 'super',
                'switch', 'this', 'throw', 'true', 'try', 'typeof', 'undefined', 'var', 'void', 'while',
                'yield', 'Promise', 'Array', 'Object', 'Map', 'Set', 'JSON', 'Math', 'Date', 'Error',
                'console', 'window', 'document', 'fetch', 'localStorage', 'sessionStorage']),
            sn('log', 'console.log($0)', 'console.log'),
            sn('fn', 'function $1($2) {\n    $0\n}', 'function'),
            sn('afn', 'async function $1($2) {\n    $0\n}', 'async function'),
            sn('arrow', '($1) => $0', 'arrow function'),
            sn('iife', '(() => {\n    $0\n})();', 'IIFE'),
            sn('try', 'try {\n    $0\n} catch (error) {\n    \n}', 'try/catch'),
            sn('forof', 'for (const $1 of $2) {\n    $0\n}', 'for…of'),
            sn('forin', 'for (const $1 in $2) {\n    $0\n}', 'for…in'),
            sn('map', '$1.map(($2) => $0)', '.map'),
            sn('filter', '$1.filter(($2) => $0)', '.filter'),
            sn('timeout', 'setTimeout(() => {\n    $0\n}, $1);', 'setTimeout'),
        ],
        typescript: null, // filled below from javascript + extras
        python: [
            ...kw(['False', 'None', 'True', 'and', 'as', 'assert', 'async', 'await', 'break', 'class',
                'continue', 'def', 'del', 'elif', 'else', 'except', 'finally', 'for', 'from', 'global',
                'if', 'import', 'in', 'is', 'lambda', 'nonlocal', 'not', 'or', 'pass', 'raise', 'return',
                'try', 'while', 'with', 'yield', 'print', 'len', 'range', 'dict', 'list', 'set', 'tuple',
                'str', 'int', 'float', 'bool', 'self', 'cls', 'super', 'Exception']),
            sn('def', 'def $1($2):\n    $0', 'def'),
            sn('class', 'class $1:\n    def __init__(self$2):\n        $0', 'class'),
            sn('ifmain', 'if __name__ == "__main__":\n    $0', 'main guard'),
            sn('try', 'try:\n    $0\nexcept Exception as e:\n    pass', 'try/except'),
            sn('with', 'with $1 as $2:\n    $0', 'with'),
            sn('lc', '[$1 for $2 in $3]', 'list comp'),
            sn('print', 'print($0)', 'print'),
        ],
        php: [
            ...kw(['abstract', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone',
                'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty',
                'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'extends',
                'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
                'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset',
                'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public',
                'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait',
                'try', 'use', 'var', 'while', 'xor', 'yield', 'true', 'false', 'null', 'self', 'parent',
                'mixed', 'string', 'int', 'float', 'bool', 'void', 'iterable', 'object']),
            sn('fn', 'function $1($2)\n{\n    $0\n}', 'function'),
            sn('pub', 'public function $1($2)\n{\n    $0\n}', 'public function'),
            sn('foreach', 'foreach ($1 as $2) {\n    $0\n}', 'foreach'),
            sn('try', 'try {\n    $0\n} catch (\\Throwable $e) {\n    \n}', 'try/catch'),
            sn('class', 'class $1\n{\n    $0\n}', 'class'),
            sn('dump', 'dump($0);', 'dump'),
            sn('dd', 'dd($0);', 'dd'),
        ],
        sql: [
            ...kw(['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON', 'AND', 'OR',
                'NOT', 'IN', 'LIKE', 'BETWEEN', 'IS', 'NULL', 'AS', 'ORDER', 'BY', 'GROUP', 'HAVING',
                'LIMIT', 'OFFSET', 'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE', 'CREATE',
                'TABLE', 'INDEX', 'VIEW', 'DROP', 'ALTER', 'ADD', 'PRIMARY', 'KEY', 'FOREIGN',
                'REFERENCES', 'UNIQUE', 'DEFAULT', 'CHECK', 'CASCADE', 'DISTINCT', 'COUNT', 'SUM',
                'AVG', 'MIN', 'MAX', 'ASC', 'DESC', 'UNION', 'ALL', 'EXISTS', 'CASE', 'WHEN', 'THEN',
                'ELSE', 'END', 'WITH']),
            sn('sel', 'SELECT $1\nFROM $2\nWHERE $0;', 'SELECT'),
            sn('join', 'SELECT $1\nFROM $2\nJOIN $3 ON $0;', 'JOIN'),
            sn('ins', 'INSERT INTO $1 ($2)\nVALUES ($0);', 'INSERT'),
            sn('upd', 'UPDATE $1\nSET $2\nWHERE $0;', 'UPDATE'),
        ],
        html: [
            ...kw(['html', 'head', 'body', 'div', 'span', 'p', 'a', 'img', 'ul', 'ol', 'li', 'table',
                'tr', 'td', 'th', 'thead', 'tbody', 'form', 'input', 'button', 'label', 'select',
                'option', 'textarea', 'script', 'style', 'link', 'meta', 'title', 'header', 'footer',
                'nav', 'main', 'section', 'article', 'aside', 'h1', 'h2', 'h3', 'class', 'id', 'href',
                'src', 'alt', 'type', 'name', 'value', 'placeholder']),
            sn('html5', '<!DOCTYPE html>\n<html lang="en">\n<head>\n    <meta charset="UTF-8">\n    <title>$1</title>\n</head>\n<body>\n    $0\n</body>\n</html>', 'HTML5 scaffold'),
            sn('div', '<div class="$1">$0</div>', 'div'),
            sn('a', '<a href="$1">$0</a>', 'anchor'),
            sn('img', '<img src="$1" alt="$2">', 'img'),
        ],
        css: [
            ...kw(['color', 'background', 'background-color', 'border', 'border-radius', 'margin',
                'padding', 'display', 'flex', 'grid', 'gap', 'width', 'height', 'min-width', 'max-width',
                'min-height', 'max-height', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
                'overflow', 'font-size', 'font-weight', 'font-family', 'line-height', 'text-align',
                'justify-content', 'align-items', 'flex-direction', 'flex-wrap', 'opacity', 'box-shadow',
                'transition', 'transform', 'cursor', 'content', 'important']),
            sn('flex', 'display: flex;\nalign-items: $1;\njustify-content: $0;', 'flexbox'),
            sn('abs', 'position: absolute;\ntop: $1;\nleft: $0;', 'absolute'),
            sn('media', '@media (min-width: $1) {\n    $0\n}', '@media'),
        ],
        bash: [
            ...kw(['echo', 'cd', 'ls', 'pwd', 'mkdir', 'rm', 'cp', 'mv', 'cat', 'grep', 'find', 'chmod',
                'chown', 'export', 'source', 'if', 'then', 'else', 'elif', 'fi', 'for', 'while', 'do',
                'done', 'case', 'esac', 'function', 'return', 'exit', 'true', 'false', 'sudo', 'apt',
                'docker', 'git', 'npm', 'composer', 'php', 'python', 'curl', 'wget']),
            sn('if', 'if [ $1 ]; then\n    $0\nfi', 'if'),
            sn('for', 'for $1 in $2; do\n    $0\ndone', 'for'),
            sn('shebang', '#!/usr/bin/env bash\nset -euo pipefail\n$0', 'shebang'),
        ],
        json: kw(['true', 'false', 'null']),
        go: [
            ...kw(['break', 'case', 'chan', 'const', 'continue', 'default', 'defer', 'else', 'fallthrough',
                'for', 'func', 'go', 'goto', 'if', 'import', 'interface', 'map', 'package', 'range',
                'return', 'select', 'struct', 'switch', 'type', 'var', 'nil', 'true', 'false', 'error',
                'string', 'int', 'bool', 'byte', 'rune', 'float64']),
            sn('func', 'func $1($2) $3 {\n    $0\n}', 'func'),
            sn('main', 'package main\n\nfunc main() {\n    $0\n}', 'main'),
            sn('ife', 'if err != nil {\n    return $0\n}', 'if err'),
        ],
        rust: [
            ...kw(['as', 'async', 'await', 'break', 'const', 'continue', 'crate', 'dyn', 'else', 'enum',
                'extern', 'false', 'fn', 'for', 'if', 'impl', 'in', 'let', 'loop', 'match', 'mod',
                'move', 'mut', 'pub', 'ref', 'return', 'self', 'Self', 'static', 'struct', 'super',
                'trait', 'true', 'type', 'unsafe', 'use', 'where', 'while', 'String', 'Vec', 'Option',
                'Result', 'Some', 'None', 'Ok', 'Err']),
            sn('fn', 'fn $1($2) $3 {\n    $0\n}', 'fn'),
            sn('match', 'match $1 {\n    $2 => $0,\n}', 'match'),
        ],
        java: [
            ...kw(['abstract', 'assert', 'boolean', 'break', 'byte', 'case', 'catch', 'char', 'class',
                'const', 'continue', 'default', 'do', 'double', 'else', 'enum', 'extends', 'final',
                'finally', 'float', 'for', 'if', 'implements', 'import', 'instanceof', 'int',
                'interface', 'long', 'new', 'null', 'package', 'private', 'protected', 'public',
                'return', 'short', 'static', 'super', 'switch', 'this', 'throw', 'throws', 'try',
                'void', 'while', 'true', 'false', 'String', 'System', 'List', 'Map', 'Optional']),
            sn('main', 'public static void main(String[] args) {\n    $0\n}', 'main'),
            sn('sout', 'System.out.println($0);', 'println'),
            sn('class', 'public class $1 {\n    $0\n}', 'class'),
        ],
        ruby: [
            ...kw(['begin', 'break', 'case', 'class', 'def', 'do', 'else', 'elsif', 'end', 'ensure',
                'false', 'for', 'if', 'in', 'module', 'next', 'nil', 'redo', 'rescue', 'retry',
                'return', 'self', 'super', 'then', 'true', 'unless', 'until', 'when', 'while', 'yield',
                'attr_reader', 'attr_writer', 'attr_accessor', 'require', 'puts', 'p']),
            sn('def', 'def $1($2)\n  $0\nend', 'def'),
            sn('class', 'class $1\n  $0\nend', 'class'),
        ],
        csharp: [
            ...kw(['abstract', 'as', 'base', 'bool', 'break', 'byte', 'case', 'catch', 'char', 'checked',
                'class', 'const', 'continue', 'decimal', 'default', 'delegate', 'do', 'double', 'else',
                'enum', 'event', 'explicit', 'extern', 'false', 'finally', 'fixed', 'float', 'for',
                'foreach', 'goto', 'if', 'implicit', 'in', 'int', 'interface', 'internal', 'is', 'lock',
                'long', 'namespace', 'new', 'null', 'object', 'operator', 'out', 'override', 'params',
                'private', 'protected', 'public', 'readonly', 'ref', 'return', 'sbyte', 'sealed',
                'short', 'sizeof', 'stackalloc', 'static', 'string', 'struct', 'switch', 'this', 'throw',
                'true', 'try', 'typeof', 'uint', 'ulong', 'unchecked', 'unsafe', 'ushort', 'using',
                'virtual', 'void', 'volatile', 'while', 'var', 'async', 'await', 'record']),
            sn('cw', 'Console.WriteLine($0);', 'WriteLine'),
            sn('class', 'public class $1\n{\n    $0\n}', 'class'),
        ],
        c: [
            ...kw(['auto', 'break', 'case', 'char', 'const', 'continue', 'default', 'do', 'double',
                'else', 'enum', 'extern', 'float', 'for', 'goto', 'if', 'int', 'long', 'register',
                'return', 'short', 'signed', 'sizeof', 'static', 'struct', 'switch', 'typedef',
                'union', 'unsigned', 'void', 'volatile', 'while', 'NULL', 'printf', 'scanf', 'malloc',
                'free', 'include']),
            sn('main', 'int main(int argc, char **argv) {\n    $0\n    return 0;\n}', 'main'),
            sn('printf', 'printf("$1\\n"$2);', 'printf'),
        ],
        cpp: null,
        kotlin: [
            ...kw(['as', 'break', 'class', 'continue', 'do', 'else', 'false', 'for', 'fun', 'if', 'in',
                'interface', 'is', 'null', 'object', 'package', 'return', 'super', 'this', 'throw',
                'true', 'try', 'typealias', 'typeof', 'val', 'var', 'when', 'while', 'by', 'catch',
                'constructor', 'delegate', 'dynamic', 'field', 'file', 'finally', 'get', 'import',
                'init', 'param', 'property', 'receiver', 'set', 'setparam', 'where', 'actual',
                'abstract', 'annotation', 'companion', 'const', 'crossinline', 'data', 'enum',
                'expect', 'external', 'final', 'infix', 'inline', 'inner', 'internal', 'lateinit',
                'noinline', 'open', 'operator', 'out', 'override', 'private', 'protected', 'public',
                'reified', 'sealed', 'suspend', 'tailrec', 'vararg']),
            sn('fun', 'fun $1($2): $3 {\n    $0\n}', 'fun'),
            sn('main', 'fun main() {\n    $0\n}', 'main'),
        ],
        yaml: kw(['true', 'false', 'null', 'yes', 'no']),
        markdown: kw(['TODO', 'NOTE', 'FIXME']),
        xml: kw(['xmlns', 'version', 'encoding']),
        plain: [],
    };

    LANG.typescript = [
        ...LANG.javascript,
        ...kw(['interface', 'type', 'implements', 'readonly', 'enum', 'namespace', 'declare',
            'keyof', 'infer', 'satisfies', 'override', 'string', 'number', 'boolean', 'any',
            'unknown', 'never', 'void', 'Record', 'Partial', 'Required', 'Readonly', 'Pick', 'Omit']),
        sn('iface', 'interface $1 {\n    $0\n}', 'interface'),
        sn('type', 'type $1 = $0;', 'type alias'),
    ];

    LANG.cpp = [
        ...LANG.c,
        ...kw(['class', 'public', 'private', 'protected', 'virtual', 'template', 'typename',
            'namespace', 'using', 'bool', 'true', 'false', 'nullptr', 'cout', 'cin', 'endl', 'std',
            'vector', 'string', 'map', 'set', 'unique_ptr', 'shared_ptr']),
        sn('cout', 'std::cout << $0 << std::endl;', 'cout'),
    ];

    function normalizeLang(raw) {
        const key = String(raw || '').trim().toLowerCase();
        return ALIASES[key] || key || 'plain';
    }

    function itemsFor(lang) {
        const id = normalizeLang(lang);
        return LANG[id] || LANG.plain || [];
    }

    function expandSnippet(insert) {
        // Replace $0 / $1 / $2 with empty (caret at first $0 or end).
        let caretOffset = null;
        let out = '';
        let i = 0;
        while (i < insert.length) {
            if (insert[i] === '$' && /[0-9]/.test(insert[i + 1] || '')) {
                const n = insert[i + 1];
                if (n === '0' && caretOffset === null) caretOffset = out.length;
                i += 2;
                continue;
            }
            out += insert[i];
            i += 1;
        }
        if (caretOffset === null) caretOffset = out.length;
        return { text: out, caret: caretOffset };
    }

    window.ctCodeSuggest = {
        normalizeLang,
        itemsFor,
        expandSnippet,
        filter(lang, prefix) {
            const q = String(prefix || '').toLowerCase();
            const items = itemsFor(lang);
            if (!q) return items.slice(0, 40);
            return items
                .filter((it) => it.label.toLowerCase().startsWith(q) || it.label.toLowerCase().includes(q))
                .sort((a, b) => {
                    const as = a.label.toLowerCase().startsWith(q) ? 0 : 1;
                    const bs = b.label.toLowerCase().startsWith(q) ? 0 : 1;
                    if (as !== bs) return as - bs;
                    return a.label.length - b.label.length;
                })
                .slice(0, 40);
        },
    };
})();
</script>
