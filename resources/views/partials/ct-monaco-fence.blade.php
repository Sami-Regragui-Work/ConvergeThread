{{-- Lazy Monaco loader for markdown fenced-code editing (no LSP). --}}
<script>
(function () {
    const MONACO_VER = '0.45.0';
    const MONACO_BASE = `https://cdn.jsdelivr.net/npm/monaco-editor@${MONACO_VER}/min`;
    let loadPromise = null;

    const LANG_MAP = {
        javascript: 'javascript', js: 'javascript', mjs: 'javascript', cjs: 'javascript',
        typescript: 'typescript', ts: 'typescript', tsx: 'typescript',
        python: 'python', py: 'python',
        php: 'php',
        ruby: 'ruby', rb: 'ruby',
        go: 'go', golang: 'go',
        rust: 'rust', rs: 'rust',
        java: 'java',
        kotlin: 'kotlin', kt: 'kotlin',
        csharp: 'csharp', cs: 'csharp',
        c: 'c', h: 'c',
        cpp: 'cpp', cxx: 'cpp', cc: 'cpp', 'c++': 'cpp',
        sql: 'sql',
        html: 'html', htm: 'html',
        css: 'css', scss: 'scss',
        bash: 'shell', sh: 'shell', zsh: 'shell', shell: 'shell',
        json: 'json',
        yaml: 'yaml', yml: 'yaml',
        markdown: 'markdown', md: 'markdown',
        xml: 'xml',
        plain: 'plaintext', text: 'plaintext',
    };

    function monacoLanguage(lang) {
        const key = String(lang || '').trim().toLowerCase();
        return LANG_MAP[key] || 'plaintext';
    }

    function ensureEditorCss() {
        if (document.getElementById('ct-monaco-editor-css')) return;
        const link = document.createElement('link');
        link.id = 'ct-monaco-editor-css';
        link.rel = 'stylesheet';
        link.href = `${MONACO_BASE}/vs/editor/editor.main.css`;
        document.head.appendChild(link);
    }

    function installMainThreadWorkers() {
        // Avoid CDN blob/data worker CORS failures. Fence editing still gets
        // syntax highlighting + word/snippet suggestions without language servers.
        window.MonacoEnvironment = {
            getWorker() {
                return {
                    postMessage() {},
                    terminate() {},
                    addEventListener() {},
                    removeEventListener() {},
                    dispatchEvent() { return false; },
                };
            },
        };
    }

    function loadMonaco() {
        if (window.monaco?.editor) return Promise.resolve(window.monaco);
        if (loadPromise) return loadPromise;

        loadPromise = new Promise((resolve, reject) => {
            ensureEditorCss();
            installMainThreadWorkers();

            const timeout = setTimeout(() => {
                loadPromise = null;
                reject(new Error('Monaco load timed out'));
            }, 6000);

            const done = (err, monaco) => {
                clearTimeout(timeout);
                if (err) {
                    loadPromise = null;
                    reject(err);
                    return;
                }
                resolve(monaco);
            };

            const startRequire = () => {
                try {
                    const req = window.require;
                    if (typeof req !== 'function' || typeof req.config !== 'function') {
                        done(new Error('AMD require unavailable'));
                        return;
                    }
                    req.config({
                        paths: { vs: `${MONACO_BASE}/vs` },
                        'vs/nls': { availableLanguages: { '*': '' } },
                    });
                    req(
                        ['vs/editor/editor.main'],
                        () => {
                            if (!window.monaco?.editor) {
                                done(new Error('Monaco missing after load'));
                                return;
                            }
                            done(null, window.monaco);
                        },
                        (err) => done(err || new Error('Monaco AMD require failed')),
                    );
                } catch (err) {
                    done(err);
                }
            };

            if (typeof window.require === 'function' && window.require.config) {
                startRequire();
                return;
            }

            const loader = document.createElement('script');
            loader.src = `${MONACO_BASE}/vs/loader.js`;
            loader.async = true;
            loader.onerror = () => done(new Error('Monaco loader failed'));
            loader.onload = () => startRequire();
            document.head.appendChild(loader);
        });

        return loadPromise;
    }

    window.ctMonacoFence = {
        monacoLanguage,
        loadMonaco,
        preferMonaco() {
            if (typeof window.matchMedia !== 'function') return true;
            if (window.matchMedia('(max-width: 640px)').matches) return false;
            if (window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(pointer: fine)').matches) {
                return false;
            }
            return true;
        },
    };
})();
</script>
