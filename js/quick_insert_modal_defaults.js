// quick_insert_modal_defaults.js
// Patch to ensure Quick Insert modal defaults to "show all" and the modal body is scrollable.
// Updated: tries to trigger the modal's "Show All" control after open (robustly, with retries).
// This file is safe to include even if quick_insert_modal_clean.js loads later; it will retry patching.
//
// Install: include this AFTER quick_insert_modal_clean.js (or anywhere — it will wait/retry).
(function () {
    'use strict';

    const MODAL_BODY_ID = 'checklistModalBody';
    const MODAL_DIALOG_ID = 'checklistModalDialog';
    const SCROLLABLE_CLASS = 'quick-insert-scrollable';
    const DEFAULT_OPTS = { showAll: true, scrollable: true };

    function applyScrollableStyles() {
        const body = document.getElementById(MODAL_BODY_ID);
        const dialog = document.getElementById(MODAL_DIALOG_ID);
        if (!body || !dialog) return;

        dialog.style.maxHeight = dialog.style.maxHeight || '80vh';
        dialog.style.overflow = dialog.style.overflow || 'hidden';

        body.style.maxHeight = body.style.maxHeight || 'calc(80vh - 120px)';
        body.style.overflowY = 'auto';
        body.style.paddingRight = body.style.paddingRight || '12px';
        body.classList.add(SCROLLABLE_CLASS);
    }

    function expandAllSectionsInModal() {
        const modalRoot = document.getElementById(MODAL_BODY_ID);
        if (!modalRoot) return;

        modalRoot.querySelectorAll('.collapsed, .is-collapsed, .hidden, [aria-hidden="true"]').forEach(el => {
            try {
                el.classList.remove('collapsed', 'is-collapsed', 'hidden');
                if (el.getAttribute && el.getAttribute('aria-hidden') === 'true') el.setAttribute('aria-hidden', 'false');
            } catch (e) { /* ignore */ }
        });

        modalRoot.querySelectorAll('[aria-expanded="false"]').forEach(t => {
            try { t.setAttribute('aria-expanded', 'true'); } catch (e) { /* ignore */ }
        });

        modalRoot.querySelectorAll('.collapse').forEach(c => {
            try { c.classList.add('show'); } catch (e) { /* ignore */ }
        });

        // As a last resort, un-hide any section cards (helps if the UI is using inline display toggles)
        modalRoot.querySelectorAll('.section-card').forEach(sc => {
            try { sc.style.display = ''; } catch (e) { /* ignore */ }
        });
    }

    // Try to find and click the "Show All" control in the modal.
    // This is robust: it searches for elements whose visible text contains "show all"
    // and clicks the first matching control. Retries several times if not found yet.
    function triggerShowAllInModal(retries = 8, delay = 120) {
        const modalRoot = document.getElementById(MODAL_BODY_ID);
        if (!modalRoot) {
            if (retries > 0) setTimeout(() => triggerShowAllInModal(retries - 1, delay), delay);
            return;
        }

        // helper to test candidate element
        function isShowAllCandidate(el) {
            if (!el) return false;
            if (!(el instanceof HTMLElement)) return false;
            const txt = (el.textContent || '').trim().toLowerCase();
            if (!txt) return false;
            // accept "show all" or "show all items" etc.
            return txt.indexOf('show all') !== -1;
        }

        // 1) Prefer explicit data-action/data-role selectors if present
        const explicit = modalRoot.querySelector('[data-action="show-all"], [data-role="show-all"], [data-action="showall"]');
        if (explicit && isShowAllCandidate(explicit)) {
            try { explicit.click(); return; } catch (e) { /* ignore */ }
        }

        // 2) Search for buttons/links within modal whose text contains "show all"
        const candidates = modalRoot.querySelectorAll('button, a, span, div');
        for (const c of candidates) {
            if (isShowAllCandidate(c)) {
                try { c.click(); return; } catch (e) { /* ignore */ }
            }
        }

        // 3) Fallback: if there is a left-side filter list with a "Show All" label, try to expand it programmatically
        //   Example: find elements that look like a filter control and remove active filter classes
        try {
            const filters = modalRoot.querySelectorAll('.filter, .filters, .left-controls, .sidebar-controls');
            if (filters && filters.length) {
                filters.forEach(f => {
                    try {
                        f.querySelectorAll('.active, .selected, [aria-selected="true"]').forEach(a => {
                            a.classList.remove('active', 'selected');
                            a.setAttribute && a.setAttribute('aria-selected', 'false');
                        });
                    } catch (e) { /* ignore */ }
                });
                // also expand everything just in case
                expandAllSectionsInModal();
                return;
            }
        } catch (e) { /* ignore */ }

        // 4) final fallback: if we couldn't find a Show All control, expand all sections so content is visible
        expandAllSectionsInModal();

        // If still nothing and retry left, try again after delay (useful while animation/rendering completes)
        if (retries > 0) setTimeout(() => triggerShowAllInModal(retries - 1, delay), delay);
    }

    // Wrap/patch quickInsertClean.open to apply defaults and do our UI fixes after the modal opens.
    function patchQuickInsertOpen() {
        if (!window.quickInsertClean || typeof window.quickInsertClean.open !== 'function') return false;

        const originalOpen = window.quickInsertClean.open.bind(window.quickInsertClean);

        window.quickInsertClean.open = function (data, opts) {
            const mergedOpts = Object.assign({}, DEFAULT_OPTS, opts || {});
            originalOpen(data, mergedOpts);

            // After open, apply UI fixes
            setTimeout(() => {
                if (mergedOpts.scrollable) applyScrollableStyles();
                if (mergedOpts.showAll) {
                    // try to trigger native "Show All" control; expand as fallback
                    triggerShowAllInModal();
                }
            }, 120);
        };

        return true;
    }

    // keep retrying until quickInsertClean becomes available (some pages load it async)
    function ensurePatched(retries = 12, delay = 200) {
        if (patchQuickInsertOpen()) return;
        if (retries <= 0) return;
        setTimeout(() => ensurePatched(retries - 1, delay), delay);
    }

    // Observe modal inserted into DOM in case open isn't called via API; then apply fixes
    function observeModalInsert() {
        if (!window.MutationObserver) return;
        const mo = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
                    for (const n of m.addedNodes) {
                        if (!(n instanceof HTMLElement)) continue;
                        if (n.id === MODAL_DIALOG_ID || (n.querySelector && n.querySelector('#' + MODAL_BODY_ID))) {
                            // small delay for any rendering/animations to finish
                            setTimeout(() => {
                                applyScrollableStyles();
                                triggerShowAllInModal();
                            }, 80);
                            return;
                        }
                    }
                }
            }
        });
        try {
            mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
        } catch (e) { /* ignore */ }
    }

    // init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => { ensurePatched(); observeModalInsert(); });
    } else {
        ensurePatched();
        observeModalInsert();
    }

    // Expose helpers for manual invocation
    window.quickInsertModalHelpers = Object.assign(window.quickInsertModalHelpers || {}, {
        applyScrollableStyles,
        expandAllSectionsInModal,
        triggerShowAllInModal,
        DEFAULT_OPTS
    });

})();