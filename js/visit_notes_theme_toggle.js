// visit_notes_theme_toggle.js
// Provides simple API and keyboard shortcut to toggle dark theme for Visit Notes page.
// Also injects a small toggle button into the header on page load.
//
// - Persist preference in localStorage under key "visitNotesTheme".
// - Call setVisitNotesTheme('dark') or setVisitNotesTheme('light') to set explicitly.
// - Toggle with Ctrl/Cmd+Alt+D (keyboard) or call toggleVisitNotesTheme().
// - A header button is injected automatically (id="vn-theme-toggle") and mirrors the current state.

(function () {
    'use strict';

    const LS_KEY = 'visitNotesTheme';
    const DARK_CLASS = 'vn-dark';
    const TOGGLE_ID = 'vn-theme-toggle';
    const TOGGLE_WRAP_CLASS = 'vn-theme-toggle-wrap';

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add(DARK_CLASS);
            document.body && document.body.classList.add(DARK_CLASS);
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove(DARK_CLASS);
            document.body && document.body.classList.remove(DARK_CLASS);
            document.documentElement.setAttribute('data-theme', 'light');
        }
        updateToggleButtonUI();
    }

    function getStoredTheme() {
        try { return localStorage.getItem(LS_KEY); } catch (e) { return null; }
    }

    function setVisitNotesTheme(theme) {
        if (theme !== 'dark' && theme !== 'light') return;
        try { localStorage.setItem(LS_KEY, theme); } catch (e) {}
        applyTheme(theme);
    }

    function toggleVisitNotesTheme() {
        const cur = getStoredTheme() || (document.documentElement.classList.contains(DARK_CLASS) ? 'dark' : 'light');
        setVisitNotesTheme(cur === 'dark' ? 'light' : 'dark');
    }

    // Keyboard shortcut: Ctrl/Cmd + Alt + D toggles theme
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.altKey && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            toggleVisitNotesTheme();
        }
    });

    // --- Toggle button UI helpers ---

    // Create (if needed) and update the button icon/aria state.
    function updateToggleButtonUI() {
        const btn = document.getElementById(TOGGLE_ID);
        if (!btn) return;
        const isDark = document.documentElement.classList.contains(DARK_CLASS);
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        btn.title = isDark ? 'Switch to light theme' : 'Switch to dark theme';
        btn.setAttribute('aria-label', btn.title);
        // Use simple inline SVG icons for clarity (no external resources).
        if (isDark) {
            // Sun icon for switching back to light
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4V2M12 22v-2M4 12H2M22 12h-2M5 5l-1.5-1.5M20.5 20.5 19 19M5 19l-1.5 1.5M20.5 3.5 19 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>';
            btn.classList.add('active');
        } else {
            // Moon icon for switching to dark
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            btn.classList.remove('active');
        }
    }

    // Inject minimal styles for the toggle button (so it looks good without requiring extra CSS files)
    function injectToggleStyles() {
        if (document.getElementById('vn-theme-toggle-styles')) return;
        const style = document.createElement('style');
        style.id = 'vn-theme-toggle-styles';
        style.textContent = `
      .${TOGGLE_WRAP_CLASS} { display:inline-flex; align-items:center; margin-left:8px; }
      #${TOGGLE_ID} {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:38px;
        height:34px;
        border-radius:8px;
        border:1px solid rgba(16,24,40,0.06);
        background: rgba(255,255,255,0.02);
        color: inherit;
        cursor: pointer;
        padding:4px;
        line-height:0;
      }
      #${TOGGLE_ID}:hover { transform: translateY(-1px); box-shadow: 0 6px 12px rgba(2,6,23,0.06); }
      #${TOGGLE_ID}.active { background: linear-gradient(90deg,#4f46e5,#7c3aed); color: #fff; border-color: rgba(124,58,237,0.32); }
      /* Dark-theme adjustments so toggle remains visible */
      .vn-dark #${TOGGLE_ID} { border-color: rgba(255,255,255,0.06); background: rgba(255,255,255,0.02); }
      .vn-dark #${TOGGLE_ID}.active { background: linear-gradient(90deg,#7c3aed,#4f46e5); color: #fff; }
      /* Small responsive tweak */
      @media (max-width:640px) {
        .${TOGGLE_WRAP_CLASS} { margin-left:6px; }
        #${TOGGLE_ID} { width:34px; height:32px; }
      }
    `;
        document.head.appendChild(style);
    }

    // Inject the toggle button into the header.
    function injectHeaderToggle() {
        // Avoid injecting more than once
        if (document.getElementById(TOGGLE_ID)) return;

        // Look for typical header containers in this app. The user earlier added id="vn-header" in visit_notes.php.
        const header = document.getElementById('vn-header') || document.querySelector('header') || document.querySelector('.vn-header') || document.querySelector('.top-nav') || document.body;
        if (!header) return;

        // Create wrapper and button
        const wrap = document.createElement('div');
        wrap.className = TOGGLE_WRAP_CLASS;
        wrap.style.display = 'inline-flex';
        wrap.style.alignItems = 'center';

        const btn = document.createElement('button');
        btn.id = TOGGLE_ID;
        btn.type = 'button';
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('aria-label', 'Toggle theme');
        btn.style.marginLeft = '6px';

        // Click toggles theme and updates UI
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleVisitNotesTheme();
        });

        wrap.appendChild(btn);

        // Prefer to place it inside header action area if available
        const preferredTargets = [
            header.querySelector('.action-buttons'),
            header.querySelector('.actions'),
            header.querySelector('.top-actions'),
            header.querySelector('.vn-header-actions'),
            header
        ];
        let placed = false;
        for (const t of preferredTargets) {
            if (!t) continue;
            try {
                t.appendChild(wrap);
                placed = true;
                break;
            } catch (err) { /* ignore and try next */ }
        }
        if (!placed) {
            // fallback: append to body (unlikely)
            document.body.appendChild(wrap);
        }

        updateToggleButtonUI();
    }

    // Initialize on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        injectToggleStyles();
        // Apply stored or system-preference theme as before
        const stored = getStoredTheme();
        if (stored === 'dark' || stored === 'light') {
            applyTheme(stored);
        } else {
            // detect prefers-color-scheme
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            applyTheme(prefersDark ? 'dark' : 'light');
        }

        // Inject the header toggle once theme is applied so UI state is accurate
        // Delay slightly to ensure header exists if it is rendered later by other scripts
        setTimeout(injectHeaderToggle, 80);

        // Expose API
        window.setVisitNotesTheme = setVisitNotesTheme;
        window.toggleVisitNotesTheme = toggleVisitNotesTheme;
        window.getVisitNotesTheme = () => getStoredTheme() || (document.documentElement.classList.contains(DARK_CLASS) ? 'dark' : 'light');
    });

    // Also ensure the button updates if theme is changed programmatically
    // (detect attribute changes on <html> for robustness)
    try {
        const mo = new MutationObserver(() => updateToggleButtonUI());
        mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
        // If header is added dynamically later, attempt to inject again when DOM changes
        const bodyMo = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
                    injectHeaderToggle();
                    break;
                }
            }
        });
        bodyMo.observe(document.body || document.documentElement, { childList: true, subtree: true });
    } catch (e) {
        // swallow errors silently (older browsers)
    }

})();