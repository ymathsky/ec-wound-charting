// place_quick_insert_button_in_wrappers.js
// Insert a "Quick Insert" button into section wrappers and robustly resolve per-section data keys.
// Updated: added ability to exclude specific sections (deny list) and honor wrapper-level opt-out
// so the "Chief Complaint" section (and other denied keys) will not receive a Quick Insert button.
//
// Behavior:
// - Finds wrappers with ids ending "-content-wrapper" and inserts a Quick Insert button before the content.
// - Skips insertion when the section is explicitly denied (deny list) or when wrapper has data-quick-insert="false".
// - On click, tries to open quickInsertClean.open(sectionData) where sectionData is looked up using multiple heuristics.
// - If no per-section data is found, falls back to window.__quickInsertData (global) to avoid "unknown section" errors.

(function () {
    'use strict';

    const WRAPPER_SUFFIX = '-content-wrapper';   // wrapper id suffix to look for
    const CONTENT_SUFFIX = '-content';           // content id suffix to look for (seen in user's example)
    const BUTTON_CLASS = 'quick-insert-btn';
    const BUTTON_WRAP_CLASS = 'quick-insert-insert-wrap';

    // Deny list: normalized keys for sections that should NOT have a Quick Insert button.
    // To remove the Quick Insert from "Chief Complaint" we include common variants here.
    const DENY_LIST = new Set([
        'chiefcomplaint', // chief_complaint, chief-complaint, chiefComplaint -> normalized
        'cc',              // short alias
        'chief'            // other possible shorthand
    ]);

    // Normalize a key (remove non-alphanum and lowercase) for robust comparisons
    function normalizeKey(k) {
        if (!k || typeof k !== 'string') return '';
        return k.replace(/[^a-z0-9]/gi, '').toLowerCase();
    }

    // Return true if Quick Insert is allowed for this section
    function isSectionAllowed(sectionName, wrapperEl) {
        // Wrapper-level opt-out: data-quick-insert="false" will explicitly prevent insertion.
        try {
            if (wrapperEl && wrapperEl.dataset && wrapperEl.dataset.quickInsert === 'false') return false;
        } catch (e) { /* ignore */ }

        const norm = normalizeKey(sectionName);
        if (!norm) return true;
        if (DENY_LIST.has(norm)) return false;
        return true;
    }

    // Attempt to find per-section data using several heuristics:
    // 1) direct key match in __quickInsertDataBySection
    // 2) normalized key match (strip punctuation/case)
    // 3) partial matches
    // 4) common aliases mapping
    function findSectionData(sectionName) {
        const globalBySection = window.__quickInsertDataBySection || null;
        const fallbackGlobal = window.__quickInsertData || null;
        if (!sectionName) return fallbackGlobal;

        if (!globalBySection || typeof globalBySection !== 'object') return fallbackGlobal;

        if (globalBySection[sectionName]) return globalBySection[sectionName];

        const requestedNorm = normalizeKey(sectionName);

        for (const k of Object.keys(globalBySection)) {
            if (normalizeKey(k) === requestedNorm) return globalBySection[k];
        }

        for (const k of Object.keys(globalBySection)) {
            const kn = normalizeKey(k);
            if (kn.indexOf(requestedNorm) !== -1 || requestedNorm.indexOf(kn) !== -1) return globalBySection[k];
        }

        const aliasMap = {
            'cc': 'chief_complaint',
            'chief': 'chief_complaint',
            'hpi': 'hpi',
            's': 'subjective',
            'o': 'objective',
            'a': 'assessment',
            'p': 'plan'
        };
        const aliasKey = aliasMap[sectionName];
        if (aliasKey && globalBySection[aliasKey]) return globalBySection[aliasKey];

        return fallbackGlobal;
    }

    function createQuickInsertButton(sectionName) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `${BUTTON_CLASS} btn btn-sm`;
        btn.textContent = 'Quick Insert';
        btn.title = `Quick Insert - ${capitalize(sectionName)}`;
        btn.setAttribute('data-section', sectionName);

        btn.addEventListener('click', (ev) => {
            ev.preventDefault();

            const sectionData = findSectionData(sectionName);
            const usedKeyMsg = sectionData && sectionData === (window.__quickInsertDataBySection && window.__quickInsertDataBySection[sectionName]) ? sectionName : '(mapped/fallback)';

            if (typeof window.quickInsertClean === 'undefined' || typeof window.quickInsertClean.open !== 'function') {
                console.warn('quickInsertClean API not available yet. Will retry shortly...');
                setTimeout(() => {
                    if (typeof window.quickInsertClean !== 'undefined' && typeof window.quickInsertClean.open === 'function') {
                        window.quickInsertClean.open(sectionData || {});
                    } else {
                        console.error('quickInsertClean API still not available. Ensure quick_insert_modal_clean.js is loaded.');
                    }
                }, 250);
                return;
            }

            if (!sectionData) {
                console.warn(`Quick Insert: no per-section data found for "${sectionName}". Falling back to global quickInsert data.`);
            } else {
                console.info(`Quick Insert: opening modal for section "${sectionName}" using ${usedKeyMsg}`);
            }

            try {
                window.quickInsertClean.open(sectionData || {}, { selected: [] });
            } catch (err) {
                console.error('Error opening quickInsertClean:', err);
            }
        });

        return btn;
    }

    // Insert a small wrapper <div> containing the button before the contentEl
    function insertButtonBeforeContent(wrapperEl, contentEl, sectionName) {
        if (!wrapperEl || !contentEl) return;

        // Avoid duplicates: if wrapper already contains a button for this section, skip
        const existing = wrapperEl.querySelector(`button.${BUTTON_CLASS}[data-section="${sectionName}"]`);
        if (existing) return;

        // Respect deny list and wrapper-level opt-out
        if (!isSectionAllowed(sectionName, wrapperEl)) {
            // Debug log so you know why it's not inserted
            console.info(`Quick Insert: skipping insertion for section "${sectionName}" (deny list or opt-out).`);
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = BUTTON_WRAP_CLASS;
        wrap.style.marginBottom = '6px';
        wrap.style.display = 'block';

        const btn = createQuickInsertButton(sectionName);
        wrap.appendChild(btn);

        contentEl.parentNode.insertBefore(wrap, contentEl);
    }

    // Utility to capitalize label for titles
    function capitalize(s) {
        if (!s) return s;
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    // Main pass: find wrappers and insert buttons
    function processAllWrappers() {
        const all = Array.from(document.querySelectorAll(`[id$="${WRAPPER_SUFFIX}"]`));
        all.forEach(wrapper => {
            try {
                const wrapperId = wrapper.id || '';
                if (!wrapperId.endsWith(WRAPPER_SUFFIX)) return;
                const section = wrapperId.substring(0, wrapperId.length - WRAPPER_SUFFIX.length); // e.g. "subjective"
                const contentId = section + CONTENT_SUFFIX; // e.g. "subjective-content"
                const contentEl = wrapper.querySelector(`#${contentId}`) || wrapper.querySelector(`.${section}-content`) || wrapper.querySelector('.quill-editor') || wrapper.querySelector('[id$="-editor-container"]');
                if (contentEl) insertButtonBeforeContent(wrapper, contentEl, section);
            } catch (e) {
                console.warn('quick-insert attach error for wrapper', wrapper, e);
            }
        });
    }

    // Observe for new wrappers added to DOM
    function observeForWrappers() {
        if (!window.MutationObserver) return;
        const mo = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
                    m.addedNodes.forEach(node => {
                        if (!(node instanceof HTMLElement)) return;
                        if (node.id && node.id.endsWith(WRAPPER_SUFFIX)) {
                            processAllWrappers();
                        } else {
                            const nested = node.querySelectorAll && node.querySelectorAll(`[id$="${WRAPPER_SUFFIX}"]`);
                            if (nested && nested.length) processAllWrappers();
                        }
                    });
                }
            }
        });
        try {
            mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
        } catch (e) {
            console.warn('quick-insert: MutationObserver observe failed', e);
        }
    }

    // Init routine (idempotent)
    function init() {
        processAllWrappers();
        observeForWrappers();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    // Expose helper for manual use
    window.quickInsertAttachToWrapper = function (wrapperEl) {
        if (!wrapperEl || !(wrapperEl instanceof HTMLElement)) return;
        const wrapperId = wrapperEl.id || '';
        if (!wrapperId.endsWith(WRAPPER_SUFFIX)) return;
        const section = wrapperId.substring(0, wrapperId.length - WRAPPER_SUFFIX.length);
        const contentEl = wrapperEl.querySelector(`#${section + CONTENT_SUFFIX}`) || wrapperEl.querySelector('.quill-editor') || wrapperEl.querySelector('[id$="-editor-container"]');
        if (contentEl) insertButtonBeforeContent(wrapperEl, contentEl, section);
    };

})();