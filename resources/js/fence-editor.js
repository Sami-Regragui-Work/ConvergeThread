import { basicSetup } from 'codemirror';
import { EditorView, keymap } from '@codemirror/view';
import { EditorState, StateEffect, StateField } from '@codemirror/state';
import { indentUnit, LanguageDescription } from '@codemirror/language';
import { languages } from '@codemirror/language-data';
import { indentWithTab } from '@codemirror/commands';
import { vscodeDark } from '@uiw/codemirror-theme-vscode';

const IDENTIFIER = /[A-Za-z_$][\w$]*/g;

function resolveLanguage(raw) {
    const name = String(raw ?? '').trim();
    if (!name) return null;
    const byName = LanguageDescription.matchLanguageName(languages, name, true);
    if (byName) return byName;
    return LanguageDescription.matchFilename(languages, `x.${name}`);
}

async function loadSupport(lang) {
    const desc = resolveLanguage(lang);
    if (!desc) return null;
    try {
        let support = await desc.load();
        if (desc.name === 'PHP') {
            const { php } = await import('@codemirror/lang-php');
            support = php({ plain: true });
        }
        return support;
    } catch (err) {
        console.warn('[ct-fence] language load failed, falling back to plain text:', lang, err);
        return null;
    }
}

function wordCompletionSource(context) {
    const before = context.matchBefore(/[\w$]+/);
    if (!before || (before.from === before.to && !context.explicit)) return null;
    const text = context.state.doc.toString();
    const words = new Set();
    let m;
    while ((m = IDENTIFIER.exec(text))) {
        if (m[0].length >= 2) words.add(m[0]);
    }
    words.delete(before.text);
    const options = Array.from(words)
        .slice(0, 100)
        .map((label) => ({ label, type: 'text' }));
    if (!options.length) return null;
    return { from: before.from, options, validFor: /^[\w$]+$/ };
}

function codeSuggestCompletionSource(context) {
    if (!window.ctCodeSuggest?.itemsFor) return null;
    const lang = context.state.field(langField, false) || 'plain';
    const before = context.matchBefore(/[\w$+#-]*/);
    if (!before || (before.from === before.to && !context.explicit)) return null;
    const items = window.ctCodeSuggest.filter(lang, before.text);
    if (!items.length) return null;
    return {
        from: before.from,
        options: items.map((it) => ({
            label: it.label,
            type: it.kind === 'keyword' ? 'keyword' : it.kind === 'snippet' ? 'text' : 'text',
            detail: it.detail,
            apply: (view, completion, from, to) => {
                const expanded = window.ctCodeSuggest.expandSnippet(it.insert);
                view.dispatch({
                    changes: { from, to, insert: expanded.text },
                    selection: { anchor: from + expanded.caret },
                    scrollIntoView: true,
                });
            },
        })),
        validFor: /^[\w$+#-]*$/,
    };
}

const setLangEffect = StateEffect.define();

const langField = StateField.define({
    create: () => 'plain',
    update(value, tr) {
        for (const e of tr.effects) if (e.is(setLangEffect)) return e.value;
        return value;
    },
});

const combinedAutocomplete = EditorState.languageData.of(() => [
    { autocomplete: codeSuggestCompletionSource },
    { autocomplete: wordCompletionSource },
]);

const fillTheme = EditorView.theme({
    '&': { height: '100%', fontSize: '13px' },
    '.cm-scroller': { overflow: 'auto' },
    '&.cm-focused': { outline: 'none' },
});

const baseExtensionsStore = new WeakMap();

function baseExtensions(onChange, onEscape, onDone) {
    const ext = [
        indentUnit.of('    '),
        basicSetup,
        vscodeDark,
        fillTheme,
        EditorView.lineWrapping,
        combinedAutocomplete,
        langField,
        keymap.of([indentWithTab]),
        keymap.of([
            { key: 'Escape', run: () => { onEscape?.(); return true; } },
            { key: 'Mod-Enter', run: () => { onDone?.(); return true; } },
        ]),
    ];
    if (onChange) {
        ext.push(EditorView.updateListener.of((update) => {
            if (update.docChanged) onChange(update.state.doc.toString());
        }));
    }
    return ext;
}

/**
 * Create a CodeMirror 6 editor inside `host`.
 *
 * @returns {Promise<EditorView>} the view (its value mirrors into the draft).
 */
export async function create({ host, value = '', language = '', onChange, onEscape, onDone }) {
    const base = baseExtensions(onChange, onEscape, onDone);
    const support = await loadSupport(language);
    const extensions = support ? [...base, support] : base;

    const view = new EditorView({ doc: value, extensions, parent: host });
    baseExtensionsStore.set(view, base);
    window.ctFenceEditor.lastView = view;
    return view;
}

/**
 * Swap the editor's language live (used when the fence opener changes).
 */
export async function setLanguage(view, lang) {
    if (!view) return;
    const base = baseExtensionsStore.get(view);
    if (!base) return;
    const support = await loadSupport(lang);
    const extensions = support ? [...base, support] : base;
    view.dispatch({ effects: [StateEffect.reconfigure.of(extensions), setLangEffect.of(lang)] });
}

window.ctFenceEditor = { create, setLanguage, resolveLanguage, getView: () => window.ctFenceEditor.lastView ?? null };
