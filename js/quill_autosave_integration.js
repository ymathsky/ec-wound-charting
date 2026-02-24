/**
 * quill_autosave_integration.js
 *
 * Integrates Quill editors with the server-backed AutosaveManager (visit_drafts).
 * - Collects Quill HTML into a compact draft payload
 * - Starts AutosaveManager (server + local fallback)
 * - Handles conflicts (pluggable hook: window.handleDraftConflict)
 * - Ensures quill contents are copied into hidden textareas before final submit
 * - Deletes draft on final save (listens for a custom "noteSaved" event or form submit success)
 *
 * Requirements:
 * - AutosaveManager class (provided in autosave_manager_drafts.js) must be loaded
 * - quillEditors global object: keys expected:
 *     chief_complaint, subjective, objective, assessment, plan
 *   If not present, this script will attempt to initialize simple Quill editors into known containers:
 *     #chief_complaint-editor-container, #subjective-editor-container, #objective-editor-container,
 *     #assessment-editor-container, #plan-editor-container
 *
 * Usage:
 * - Include after Quill and quill_editor_manager.js (which normally initializes quillEditors)
 * - Optionally provide window.handleDraftConflict(serverDraft, resolveFn) to present a merge UI
 *
 * Security note:
 * - Drafts contain PHI. Ensure endpoints are protected and DB is encrypted at rest.
 */

(function () {
    'use strict';

    // Config
    const SAVE_INTERVAL_MS = 5000;
    // Use configured endpoints from autosave_config.js if present, otherwise fallback
    const ENDPOINT_SAVE = (window.AUTOSAVE_ENDPOINTS && window.AUTOSAVE_ENDPOINTS.SAVE) || '/api/save_draft.php';
    const ENDPOINT_LOAD = (window.AUTOSAVE_ENDPOINTS && window.AUTOSAVE_ENDPOINTS.LOAD) || '/api/load_draft.php';
    const ENDPOINT_DELETE = (window.AUTOSAVE_ENDPOINTS && window.AUTOSAVE_ENDPOINTS.DELETE) || '/api/delete_draft.php';

    // Helpers
    function safeHtmlFromQuill(editor) {
        // Return the editor's HTML content (Quill root innerHTML) or empty string
        try {
            if (!editor || !editor.root) return '';
            return editor.root.innerHTML || '';
        } catch (e) {
            console.warn('safeHtmlFromQuill error', e);
            return '';
        }
    }

    function ensureQuillEditors() {
        // If quillEditors are already initialized by quill_editor_manager.js, use them.
        // Otherwise create basic Quill editors for required containers.
        if (!window.quillEditors) window.quillEditors = {};
        const required = [
            { key: 'chief_complaint', sel: '#chief_complaint-editor-container' },
            { key: 'subjective', sel: '#subjective-editor-container' },
            { key: 'objective', sel: '#objective-editor-container' },
            { key: 'assessment', sel: '#assessment-editor-container' },
            { key: 'plan', sel: '#plan-editor-container' }
        ];

        required.forEach(item => {
            if (!window.quillEditors[item.key]) {
                const el = document.querySelector(item.sel);
                if (el) {
                    // Create minimal Quill editor if Quill available
                    if (window.Quill) {
                        window.quillEditors[item.key] = new Quill(item.sel, {
                            theme: 'snow',
                            modules: { toolbar: [['bold','italic','underline'], [{ list: 'ordered'}, { list: 'bullet' }], ['clean']] }
                        });
                    } else {
                        // Fallback: create a simple contentEditable wrapper
                        const fallback = document.createElement('div');
                        fallback.contentEditable = 'true';
                        fallback.className = 'quill-fallback p-2';
                        fallback.style.minHeight = '120px';
                        el.appendChild(fallback);
                        window.quillEditors[item.key] = { root: fallback };
                    }
                }
            }
        });
    }

    function collectNoteDraftPayload() {
        // Build the draft JSON payload matching the docs/draft_schema.md structure
        const metadata = {
            step: 'notes',
            lastEditedByDevice: (navigator.userAgent || 'web').slice(0, 200),
            lastEditedAt: new Date().toISOString(),
            clientDraftVersion: (window.clientDraftVersion || 0) + 1
        };

        const payload = {
            chief_complaint: safeHtmlFromQuill(window.quillEditors.chief_complaint),
            subjective: safeHtmlFromQuill(window.quillEditors.subjective),
            objective: safeHtmlFromQuill(window.quillEditors.objective),
            assessment: safeHtmlFromQuill(window.quillEditors.assessment),
            plan: safeHtmlFromQuill(window.quillEditors.plan)
        };

        // update local counter
        window.clientDraftVersion = metadata.clientDraftVersion;

        return { metadata, payload };
    }

    async function deleteDraft(appointmentId, userId) {
        try {
            const form = new URLSearchParams({ appointment_id: appointmentId });
            if (userId) form.append('user_id', userId);
            const resp = await fetch(ENDPOINT_DELETE, {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            });
            const j = await resp.json().catch(() => ({}));
            return j;
        } catch (err) {
            console.warn('deleteDraft error', err);
            return null;
        }
    }

    // Copy quill HTML into hidden textareas before final submit
    function copyQuillToHiddenTextareas() {
        try {
            const map = [
                { inputId: 'chief_complaint_input', editorKey: 'chief_complaint' },
                { inputId: 'subjective', editorKey: 'subjective' },
                { inputId: 'objective', editorKey: 'objective' },
                { inputId: 'assessment', editorKey: 'assessment' },
                { inputId: 'plan', editorKey: 'plan' }
            ];
            map.forEach(m => {
                const el = document.getElementById(m.inputId);
                if (!el) return;
                el.value = safeHtmlFromQuill(window.quillEditors[m.editorKey]) || '';
            });
        } catch (e) {
            console.warn('copyQuillToHiddenTextareas error', e);
        }
    }

    // Wire up everything once DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        // Basic sanity checks for phpVars (set in visit_notes.php)
        const patientId = (window.phpVars && window.phpVars.patientId) ? window.phpVars.patientId : null;
        const appointmentId = (window.phpVars && window.phpVars.appointmentId) ? window.phpVars.appointmentId : null;
        const userId = (window.phpVars && window.phpVars.userId) ? window.phpVars.userId : null;

        if (!appointmentId) {
            console.warn('Autosave: missing appointmentId, autosave disabled.');
            return;
        }

        // Ensure quill editors exist or initialize fallbacks
        ensureQuillEditors();

        // create AutosaveManager instance
        const autosave = new AutosaveManager({
            appointmentId: appointmentId,
            userId: userId,
            saveIntervalMs: SAVE_INTERVAL_MS,
            endpointSave: ENDPOINT_SAVE,
            endpointLoad: ENDPOINT_LOAD,
            getDraftPayload: collectNoteDraftPayload,
            onConflict: function (serverDraft, resolve) {
                /**
                 * Pluggable conflict hook:
                 * - If an application-level UI exists, prefer that: window.handleDraftConflict(serverDraft, resolve)
                 * - Otherwise default behavior: prompt user to choose server/local/merge.
                 *
                 * resolve(action, mergedDraft) must be called by the handler.
                 * action: 'use_server' | 'use_local' | 'merge'
                 * mergedDraft: optional object payload for 'merge' or 'use_local'
                 */
                if (typeof window.handleDraftConflict === 'function') {
                    try {
                        window.handleDraftConflict(serverDraft, resolve);
                        return;
                    } catch (e) {
                        console.warn('window.handleDraftConflict threw', e);
                    }
                }

                // Default simple prompt (blocking): prefer server to avoid overwriting newer clinician work
                const msg = 'A newer draft exists on the server. Click OK to load server draft into the editor (recommended), Cancel to keep your local draft and overwrite server.';
                const useServer = window.confirm(msg);
                if (useServer) {
                    resolve('use_server');
                } else {
                    // User prefers to keep local: force save local (the AutosaveManager will retry)
                    resolve('use_local');
                }
            },
            onSaved: function (result) {
                // Update autosave status UI
                const statusEl = document.getElementById('autosave-status');
                if (statusEl) {
                    const t = new Date().toLocaleTimeString();
                    statusEl.textContent = `Draft saved ${t}`;
                }
            }
        });

        // Start autosave loop
        try {
            autosave.start();
        } catch (e) {
            console.warn('Failed to start AutosaveManager', e);
        }

        // Hook into form submit to:
        // - copy quill content into hidden textareas
        // - attempt a synchronous save (best-effort) or ensure last autosave finished
        const noteForm = document.getElementById('noteForm');
        if (noteForm) {
            noteForm.addEventListener('submit', async function (ev) {
                // copy content first
                copyQuillToHiddenTextareas();

                // If autosave is in-progress, wait briefly
                const waitForAutosaveMs = 1200;
                if (autosave && autosave.isSaving) {
                    // Wait a short moment for current async save to complete
                    await new Promise(resolve => setTimeout(resolve, waitForAutosaveMs));
                }

                // Force-save current content immediately before final submit
                try {
                    const payload = collectNoteDraftPayload();
                    // forceSaveDraft method exists on AutosaveManager instance
                    if (autosave && typeof autosave.forceSaveDraft === 'function') {
                        await autosave.forceSaveDraft(payload.payload); // note: forceSaveDraft expects the draft object in this integration
                    }
                } catch (e) {
                    // best-effort; proceed with submit even if autosave fails
                    console.warn('force save before submit failed', e);
                }

                // Allow normal form submit to continue (or if your app submits via AJAX,
                // trigger that flow and on success dispatch the 'noteSaved' event below)
            });
        }

        // Listen for a custom event 'noteSaved' dispatched by the code that performs the final save.
        // When a final save happens we should delete the draft for this appointment+user.
        window.addEventListener('noteSaved', async function (ev) {
            try {
                await deleteDraft(appointmentId, userId);
                const statusEl = document.getElementById('autosave-status');
                if (statusEl) statusEl.textContent = 'Final note saved — draft cleared';
            } catch (e) {
                console.warn('noteSaved handler failed to delete draft', e);
            }
        });

        // If your create_note.php returns success via AJAX, call:
        // window.dispatchEvent(new Event('noteSaved'));
        // or dispatch a CustomEvent with detail: { appointmentId, userId }

        // Best-effort: when the window unloads, trigger a synchronous save (sendBeacon or navigator.sendBeacon)
        window.addEventListener('beforeunload', function () {
            // copy content into hidden fields so browser unload handler can use them if needed
            copyQuillToHiddenTextareas();
            try {
                // call autosave.saveSync() if available (AutosaveManager has saveSync)
                if (autosave && typeof autosave.saveSync === 'function') autosave.saveSync();
            } catch (e) {
                // ignore
            }
        });

        // Expose helper API for debugging / manual control
        window.__ECWoundAutosave = {
            autosaveInstance: autosave,
            collectDraftPayload,
            deleteDraft: () => deleteDraft(appointmentId, userId),
            copyQuillToHiddenTextareas
        };
    });
})();
