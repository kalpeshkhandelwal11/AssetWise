/**
 * `filterBar` — turns the shared <x-filter-bar> GET form into an auto-applying filter, so no
 * "Filter" button is needed: selects / checkboxes / date inputs submit on change, and text
 * inputs submit on a debounced keystroke (the `.debounce` modifier lives on @input in the
 * Blade). Registered as an Alpine.data component (not inline in x-data) per the project's
 * Blade+Alpine conventions.
 *
 * Each submit is a full GET navigation, which would drop focus mid-typing. So before a
 * text-driven submit we stash the focused field name + caret in sessionStorage and restore
 * them on the next load (same path only), keeping the search box usable.
 */
const KEY = 'filterbar:focus';
const TEXTY = ['text', 'search', 'number', 'email', 'tel', 'url', ''];

export default function filterBar() {
    return {
        submit() {
            const f = this.$el;
            f.requestSubmit ? f.requestSubmit() : f.submit();
        },

        // Selects, checkboxes, radios and date pickers apply immediately.
        onChange(e) {
            const t = e.target;
            if (t.tagName === 'SELECT' || t.type === 'checkbox' || t.type === 'radio' || t.type === 'date') {
                this.submit();
            }
        },

        // Free-text fields apply on a debounced keystroke; remember focus + caret across reload.
        onInput(e) {
            const t = e.target;
            const texty = t.tagName === 'TEXTAREA' || TEXTY.includes(t.type);
            if (! texty) return;
            try {
                sessionStorage.setItem(KEY, JSON.stringify({
                    path: location.pathname,
                    name: t.getAttribute('name'),
                    caret: t.selectionStart ?? null,
                }));
            } catch (_) { /* private mode / quota — non-fatal */ }
            this.submit();
        },

        restore() {
            let raw;
            try { raw = sessionStorage.getItem(KEY); } catch (_) { return; }
            if (! raw) return;
            try { sessionStorage.removeItem(KEY); } catch (_) { /* ignore */ }

            let info;
            try { info = JSON.parse(raw); } catch (_) { return; }
            if (! info || info.path !== location.pathname || ! info.name) return;

            const el = this.$el.querySelector(`[name="${CSS.escape(info.name)}"]`);
            if (! el) return;
            el.focus();
            if (info.caret != null && el.setSelectionRange) {
                // setSelectionRange throws on type=number in some browsers — best-effort only.
                try { el.setSelectionRange(info.caret, info.caret); } catch (_) { /* ignore */ }
            }
        },
    };
}
