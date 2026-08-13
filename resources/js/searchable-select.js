/**
 * `x-searchable` — turns a native <select> into a Tom Select searchable dropdown while staying
 * transparent to the rest of the form. Registered from app.js as an Alpine directive (NOT an
 * Alpine.data component) precisely because a directive runs inside the element's existing Alpine
 * scope: the same <select> keeps its `x-model` / `@change` bindings pointing at the parent form's
 * state instead of a new island.
 *
 * Behind <x-searchable-select>. Handles the three ways a plain enhancer would otherwise regress
 * the Alpine forms in this app:
 *   1. User picks in Tom Select  -> writes the hidden <select> value + fires `change`, so x-model
 *      and @change handlers (cascades, custodian auto-fill) react exactly as before.
 *   2. Alpine sets the value programmatically (e.g. custodian -> department auto-fill) -> an
 *      effect on the element's x-model getter pushes it into the control.
 *   3. Alpine repopulates <option>s via `<template x-for>` (location cascade) or toggles
 *      `disabled` -> MutationObservers re-sync the control.
 */
import TomSelect from 'tom-select';

export default function registerSearchableSelect(Alpine) {
    Alpine.directive('searchable', (el, directive, { effect, cleanup }) => {
        if (el.tomselect) {
            return;
        }

        // Defer one microtask so the `x-model` directive on the same element has attached its
        // `el._x_model` getter/setter before we wire the two-way sync.
        queueMicrotask(() => {
            if (el.tomselect) {
                return;
            }

            const placeholder =
                el.dataset.placeholder ||
                el.querySelector('option[value=""]')?.textContent?.trim() ||
                'Select…';

            const ts = new TomSelect(el, {
                allowEmptyOption: true,
                placeholder,
                maxOptions: null,
                // Keep the DOM order of options rather than re-sorting alphabetically.
                sortField: [{ field: '$order' }, { field: '$score' }],
            });

            // (2) Alpine data -> control. Reading the model getter inside an effect makes this
            // re-run whenever the bound state changes; `true` keeps it silent (no change loop).
            if (el._x_model) {
                effect(() => {
                    const value = el._x_model.get();
                    if (String(value ?? '') !== String(ts.getValue() ?? '')) {
                        ts.setValue(value ?? '', true);
                    }
                });
            }

            // (3a) Options repopulated by x-for (cascading location selects) -> re-read them.
            // Disconnect around our own sync: ts.sync() mutates the <select>, which would
            // otherwise re-trigger this observer forever and hang the page before it paints.
            const optionObserver = new MutationObserver(() => {
                optionObserver.disconnect();
                const keep = el.value;
                try {
                    ts.sync();
                    ts.setValue(keep, true);
                } catch {
                    // Tom Select may be mid-teardown; nothing to recover.
                }
                optionObserver.observe(el, { childList: true });
            });
            optionObserver.observe(el, { childList: true });

            // (3b) `:disabled` toggled by Alpine -> reflect on the control.
            const attrObserver = new MutationObserver(() => {
                el.disabled ? ts.disable() : ts.enable();
            });
            attrObserver.observe(el, { attributes: true, attributeFilter: ['disabled'] });
            if (el.disabled) {
                ts.disable();
            }

            cleanup(() => {
                optionObserver.disconnect();
                attrObserver.disconnect();
                ts.destroy();
            });
        });
    });
}
