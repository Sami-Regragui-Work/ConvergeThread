{{-- Lazy Monaco loader for markdown fenced-code editing (no LSP). --}}
<script>
(function () {
    const MONACO_VER = '0.52.2';
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

    function loadMonaco() {
        if (window.monaco?.editor) return Promise.resolve(window.monaco);
        if (loadPromise) return loadPromise;

        loadPromise = new Promise((resolve, reject) => {
            window.MonacoEnvironment = {
                getWorkerUrl() {
                    const src = `
                        self.MonacoEnvironment = { baseUrl: '${MONACO_BASE}/' };
                        importScripts('${MONACO_BASE}/vs/base/worker/workerMain.js');
                    `;
                    return URL.createObjectURL(new Blob([src], { type: 'text/javascript' }));
                },
            };

            const loader = document.createElement('script');
            loader.src = `${MONACO_BASE}/vs/loader.js`;
            loader.async = true;
            loader.onerror = () => {
                loadPromise = null;
                reject(new Error('Monaco loader failed'));
            };
            loader.onload = () => {
                try {
                    window.require.config({ paths: { vs: `${MONACO_BASE}/vs` } });
                    window.require(['vs/editor/editor.main'], () => {
                        if (!window.monaco?.editor) {
                            loadPromise = null;
                            reject(new Error('Monaco missing after load'));
                            return;
                        }
                        resolve(window.monaco);
                    });
                } catch (err) {
                    loadPromise = null;
                    reject(err);
                }
            };
            document.head.appendChild(loader);
        });

        return loadPromise;
    }

    window.ctMonacoFence = {
        monacoLanguage,
        loadMonaco,
        preferMonaco() {
            if (typeof window.matchMedia !== 'function') return true;
            // Skip on coarse pointers / narrow screens — keep lightweight suggest.
            if (window.matchMedia('(max-width: 640px)').matches) return false;
            if (window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(pointer: fine)').matches) {
                return false;
            }
            return true;
        },
    };
})();
</script>
