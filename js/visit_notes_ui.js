// js/visit_notes_ui.js
// UI/UX enhancements for Visit Notes page
// - modal focus trap
// - Ctrl/Cmd+S save shortcut
// - autosave status indicator (listens to ec:autosave events)
// - Save button busy state (spinner)
// - Insert checklist items at Quill cursor (used by visit_notes_logic.js when inserting)

(function () {
    'use strict';

    // Utility: find all focusable elements inside container
    function getFocusable(el) {
        if (!el) return [];
        return Array.from(el.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])'))
            .filter(e => !e.hasAttribute('disabled') && e.offsetParent !== null);
    }

    // Modal focus trap for checklistModal and previewModal
    function makeModalFocusTrap(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        function onKeyDown(e) {
            if (e.key === 'Escape') {
                // close with existing close button if available
                const closeBtn = modal.querySelector('[data-dismiss], .modal-close-btn, #closeChecklistModalBtn, #closePreviewModalBtn, #closeChecklistBtn');
                if (closeBtn) closeBtn.click();
            } else if (e.key === 'Tab') {
                const focusables = getFocusable(modal);
                if (!focusables.length) { e.preventDefault(); return; }
                const first = focusables[0], last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            }
        }

        // When modal shown, attach listeners; when hidden, remove.
        const observer = new MutationObserver(() => {
            const visible = window.getComputedStyle(modal).display !== 'none' && modal.classList.contains('show-modal');
            if (visible) {
                const focusables = getFocusable(modal);
                // Focus first focusable or modal dialog
                if (focusables.length) focusables[0].focus();
                else modal.focus();
                document.addEventListener('keydown', onKeyDown);
            } else {
                document.removeEventListener('keydown', onKeyDown);
            }
        });

        observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
    }

    // Initialize focus traps
    document.addEventListener('DOMContentLoaded', function () {
        makeModalFocusTrap('checklistModal');
        makeModalFocusTrap('previewModal');
    });

    // Keyboard shortcut for Save: Ctrl/Cmd + S
    document.addEventListener('keydown', function (e) {
        // Ignore when typing in inputs or if modifiers for other actions are present (e.g., meta+shift)
        const active = document.activeElement;
        const tag = active && active.tagName && active.tagName.toLowerCase();
        if (tag === 'input' || tag === 'textarea' || active && active.isContentEditable) {
            // allow in contentEditable only if user wants it; we handle globally to save note
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            const saveBtn = document.getElementById('saveNoteBtn');
            if (saveBtn) saveBtn.click();
        }
    });

    // Autosave status element: keep it updated based on events
    function updateAutosaveStatus(text, type) {
        const el = document.getElementById('autosave-status');
        if (!el) return;
        el.textContent = text;
        if (type === 'saving') {
            el.style.color = ''; // keep muted or set special style if needed
        } else if (type === 'ok') {
            el.style.color = '';
        } else if (type === 'error') {
            el.style.color = 'var(--vn-error)';
        }
    }

    // Listen to autosave events
    window.addEventListener('ec:autosave:success', function (e) {
        const time = new Date().toLocaleTimeString();
        updateAutosaveStatus('Saved (at ' + time + ')', 'ok');
        // optional: show floating alert handled by autosave_alert_adapter
    });

    window.addEventListener('ec:autosave:failure', function (e) {
        updateAutosaveStatus('Autosave failed — saved locally', 'error');
    });

    // Hook noteSaved (final save) to update status and optionally clear local draft UI
    window.addEventListener('noteSaved', function (e) {
        const time = new Date().toLocaleTimeString();
        updateAutosaveStatus('Saved (final) at ' + time, 'ok');
        // Also show floating alert if floating alerts exist
        if (typeof window.showFloatingAlert === 'function') {
            window.showFloatingAlert('Note saved', 'success', 2400);
        }
    });

    // Save button busy state helper
    function setSaveBusy(busy) {
        const btn = document.getElementById('saveNoteBtn');
        if (!btn) return;
        btn.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (busy) {
            // add spinner if not present
            if (!btn.querySelector('.save-spinner')) {
                const s = document.createElement('span');
                s.className = 'save-spinner';
                s.style.marginLeft = '8px';
                btn.appendChild(s);
            }
        } else {
            const s = btn.querySelector('.save-spinner');
            if (s) s.remove();
        }
    }

    // Wrap existing save handler to set busy state automatically
    // If save is performed via clicking saveNoteBtn, we update busy flag before/after
    document.addEventListener('click', function (e) {
        const target = e.target.closest && e.target.closest('#saveNoteBtn');
        if (!target) return;
        // set busy true; the existing handler will call fetch; we can't hook its promise without editing that handler
        // But we can attach a one-time listener for noteSaved or errors to unset busy
        setSaveBusy(true);

        // Unset busy when noteSaved fires or autosave failure
        function cleanup() {
            setSaveBusy(false);
            window.removeEventListener('noteSaved', onSaved);
            window.removeEventListener('ec:autosave:failure', onFail);
        }
        function onSaved() { cleanup(); }
        function onFail() { cleanup(); }

        window.addEventListener('noteSaved', onSaved);
        window.addEventListener('ec:autosave:failure', onFail);

        // safety: unset after 10s in case no event arrives
        setTimeout(cleanup, 10000);
    });

    // Insert HTML into Quill at current cursor in a way that preserves undo history
    window.insertHtmlIntoQuillAtCursor = function (editorInstance, html) {
        try {
            if (!editorInstance) return;
            const sel = editorInstance.getSelection();
            const index = (sel && typeof sel.index === 'number') ? sel.index : editorInstance.getLength();
            // Use clipboard.dangerouslyPasteHTML with 'user' source to ensure history
            if (editorInstance.clipboard && typeof editorInstance.clipboard.dangerouslyPasteHTML === 'function') {
                editorInstance.clipboard.dangerouslyPasteHTML(index, html, 'user');
                // move selection after inserted content
                editorInstance.setSelection(index + 1, 0, 'user');
            } else {
                // fallback: insert text
                editorInstance.insertText(index, html);
            }
        } catch (err) {
            console.warn('insertHtmlIntoQuillAtCursor error', err);
        }
    };

    // Enhance quick-insert buttons with ARIA attributes and keyboard focus
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.quick-insert-btn').forEach(btn => {
            btn.setAttribute('role', 'button');
            btn.setAttribute('aria-haspopup', 'dialog');
            const section = btn.getAttribute('data-section') || '';
            btn.setAttribute('aria-controls', 'checklistModal');
            // Ensure focus visible
            btn.addEventListener('keyup', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') btn.click();
            });
        });
    });

})();