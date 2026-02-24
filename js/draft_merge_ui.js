/**
 * draft_merge_ui.js
 *
 * Compact Merge UI for draft conflict resolution.
 *
 * Exposes a single global function used by the AutosaveManager onConflict hook:
 *   window.handleDraftConflict(serverDraft, resolve)
 *
 * Behavior:
 * - Builds a lightweight modal showing Server (left) vs Local (right)
 * - Local draft is obtained from:
 *     1) a provided `localDraft` param if the caller adds it to window.__ECWoundAutosave.localDraft (optional)
 *     2) window.collectNoteDraftPayload() if present
 *     3) reading window.quillEditors directly (fallback)
 * - Sanitizes HTML before inserting into the modal (removes <script>, <style>, and event-* attributes)
 * - Provides three actions:
 *     * Use Server  -> resolve('use_server')
 *     * Use Local   -> resolve('use_local', localDraftObj)
 *     * Merge       -> open an editable merged editor, allow edits, then resolve('merge', mergedDraftObj)
 *
 * Security note:
 * - This file attempts client-side sanitization to avoid executing scripts from stored HTML.
 * - Always perform server-side sanitization before persistent storage and before re-rendering elsewhere.
 *
 * Integration:
 * - Include this file after quill_autosave_integration.js (so quillEditors and helper functions likely exist).
 * - AutosaveManager's onConflict should call the provided window.handleDraftConflict(serverDraft, resolve) function.
 *
 * Lightweight CSS is injected on first run so you only need to include this single JS file.
 */

(function () {
    'use strict';

    // Inject minimal styles once
    const STYLES_ID = 'draft-merge-ui-styles';
    function ensureStyles() {
        if (document.getElementById(STYLES_ID)) return;
        const css = `
#draftMergeModal { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 12000; }
#draftMergeModal .dialog { width: 92%; max-width: 1100px; max-height: 92vh; background: #fff; border-radius: 10px; overflow: hidden; display:flex; flex-direction:column; box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
#draftMergeModal .header { padding: 12px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #eee; background:#f9fafb; }
#draftMergeModal .title { font-weight:600; color:#111827; }
#draftMergeModal .body { display:flex; gap:12px; padding:12px; flex:1; overflow:auto; }
#draftMergeModal .pane { flex:1; min-width:0; display:flex; flex-direction:column; border:1px solid #e6e6e6; border-radius:6px; overflow:hidden; background:#fff; }
#draftMergeModal .pane .pane-title { padding:8px 10px; background:#f3f4f6; font-weight:600; border-bottom:1px solid #eee; color:#374151; }
#draftMergeModal .pane .content { padding:10px; overflow:auto; flex:1; background:#ffffff; }
#draftMergeModal .content .viewer { width:100%; min-height:220px; border-radius:4px; padding:8px; background:#fff; box-sizing:border-box; overflow:auto; }
#draftMergeModal .footer { padding:10px; border-top:1px solid #eee; display:flex; justify-content:space-between; gap:8px; align-items:center; background:#fff; }
#draftMergeModal .btn { padding:8px 12px; border-radius:6px; border:1px solid transparent; cursor:pointer; font-weight:600; }
#draftMergeModal .btn.positive { background:#2563eb; color:#fff; border-color: #1e40af; }
#draftMergeModal .btn.ghost { background:transparent; color:#374151; border-color:#d1d5db; }
#draftMergeModal .btn.warn { background:#f97316; color:#fff; border-color:#c2410c; }
#draftMergeModal .merged-editor { width:100%; min-height:220px; padding:8px; border-radius:4px; border:1px dashed #d1d5db; box-sizing:border-box; background:#fff; overflow:auto; }
#draftMergeModal .panel-actions { display:flex; gap:8px; padding:6px; border-top:1px dashed #f3f4f6; background:#fbfdff; }
@media (max-width:900px){ #draftMergeModal .body { flex-direction: column; } }
    `;
        const s = document.createElement('style');
        s.id = STYLES_ID;
        s.appendChild(document.createTextNode(css));
        document.head.appendChild(s);
    }

    // Sanitize HTML: remove script/style tags and event attributes, strip javascript: URIs
    function sanitizeHTML(unsafeHtml) {
        if (!unsafeHtml) return '';
        // parse using DOMParser
        const parser = new DOMParser();
        const doc = parser.parseFromString(unsafeHtml, 'text/html');

        // remove script/style
        doc.querySelectorAll('script, style, iframe, object, embed').forEach(n => n.remove());

        // remove event handler attributes and javascript: URIs
        const elements = doc.querySelectorAll('*');
        elements.forEach(el => {
            // remove attributes starting with "on"
            [...el.attributes].forEach(attr => {
                const name = attr.name.toLowerCase();
                const val = attr.value || '';
                if (name.startsWith('on')) {
                    el.removeAttribute(attr.name);
                    return;
                }
                // sanitize href/src values that start with javascript:
                if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(val)) {
                    el.removeAttribute(attr.name);
                    return;
                }
                // remove style attributes with expression() (legacy)
                if (name === 'style' && /expression\s*\(/i.test(val)) {
                    el.removeAttribute(attr.name);
                    return;
                }
            });
        });

        // serialize back
        return doc.body.innerHTML;
    }

    // Build a local draft object using available helpers
    function getLocalDraftObject() {
        // Prefer an explicitly exposed helper if available
        try {
            if (window.__ECWoundAutosave && typeof window.__ECWoundAutosave.collectDraftPayload === 'function') {
                const out = window.__ECWoundAutosave.collectDraftPayload();
                if (out) return out;
            }
            if (typeof window.collectNoteDraftPayload === 'function') {
                return window.collectNoteDraftPayload();
            }
        } catch (e) {
            // continue to quill fallback
        }

        // Fallback: read from quillEditors
        const editors = window.quillEditors || {};
        const keys = ['chief_complaint','subjective','objective','assessment','plan'];
        const payload = {};
        keys.forEach(k => {
            try {
                const ed = editors[k];
                if (ed && ed.root) payload[k] = sanitizeHTML(ed.root.innerHTML || '');
                else {
                    // attempt to find container
                    const sel = {
                        'chief_complaint':'#chief_complaint-editor-container',
                        'subjective':'#subjective-editor-container',
                        'objective':'#objective-editor-container',
                        'assessment':'#assessment-editor-container',
                        'plan':'#plan-editor-container'
                    }[k];
                    const el = sel ? document.querySelector(sel) : null;
                    if (el) payload[k] = sanitizeHTML(el.innerHTML || '');
                    else payload[k] = '';
                }
            } catch (err) {
                payload[k] = '';
            }
        });

        const metadata = {
            step: 'notes',
            lastEditedAt: new Date().toISOString(),
            lastEditedByDevice: (navigator.userAgent||'web').slice(0,200),
            clientDraftVersion: (window.clientDraftVersion||0) + 1
        };
        return { metadata, payload };
    }

    // Create modal DOM
    function createModal() {
        // If already exists, return it
        let modal = document.getElementById('draftMergeModal');
        if (modal) return modal;

        ensureStyles();

        modal = document.createElement('div');
        modal.id = 'draftMergeModal';
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.style.display = 'none';

        modal.innerHTML = `
      <div class="dialog" role="document" aria-label="Draft conflict resolution dialog">
        <div class="header">
          <div class="title">Draft Conflict — Server vs Local</div>
          <div class="subtitle" style="color:#6b7280;font-size:13px;">Choose which draft to keep or merge changes</div>
        </div>

        <div class="body">
          <div class="pane" id="dm-pane-server">
            <div class="pane-title">Server (Newer)</div>
            <div class="content">
              <div class="viewer" id="dm-server-view"></div>
              <div class="panel-actions">
                <button class="btn ghost" id="dm-use-server">Use Server</button>
              </div>
            </div>
          </div>

          <div class="pane" id="dm-pane-local">
            <div class="pane-title">Your Local Draft</div>
            <div class="content">
              <div class="viewer" id="dm-local-view"></div>
              <div class="panel-actions">
                <button class="btn ghost" id="dm-use-local">Use Local</button>
                <button class="btn" id="dm-open-merge">Merge / Edit</button>
              </div>
            </div>
          </div>
        </div>

        <div class="body" id="dm-merge-row" style="display:none; padding:12px;">
          <div class="pane" style="flex:1;">
            <div class="pane-title">Merged Editor (Edit to resolve)</div>
            <div class="content">
              <div id="dm-merged-editor" class="merged-editor" contenteditable="true" aria-label="Merged draft editor"></div>
              <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;">
                <button class="btn ghost" id="dm-cancel-merge">Cancel Merge</button>
                <button class="btn positive" id="dm-confirm-merge">Confirm Merge</button>
              </div>
            </div>
          </div>
        </div>

        <div class="footer">
          <div style="color:#6b7280;font-size:13px;">Tip: Use "Merge / Edit" to combine text or copy sections from either side.</div>
          <div style="display:flex; gap:8px;">
            <button class="btn ghost" id="dm-cancel">Cancel</button>
          </div>
        </div>
      </div>
    `;
        document.body.appendChild(modal);

        // wiring
        modal.querySelector('#dm-use-server').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'use_server' } }));
        });
        modal.querySelector('#dm-use-local').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'use_local' } }));
        });
        modal.querySelector('#dm-open-merge').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'open_merge' } }));
        });
        modal.querySelector('#dm-cancel').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'cancel' } }));
        });
        modal.querySelector('#dm-cancel-merge').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'cancel_merge' } }));
        });
        modal.querySelector('#dm-confirm-merge').addEventListener('click', () => {
            modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'confirm_merge' } }));
        });

        return modal;
    }

    // Render draft object into the viewer element. Draft may be:
    // - object with { metadata, payload } where payload contains fields e.g. subjective
    // - or raw HTML string
    function renderDraftInViewer(draftObjOrHtml, viewerEl) {
        if (!viewerEl) return;
        let html = '';
        if (!draftObjOrHtml) {
            html = '<div style="color:#6b7280;font-style:italic;">(empty)</div>';
        } else if (typeof draftObjOrHtml === 'string') {
            html = sanitizeHTML(draftObjOrHtml);
        } else if (typeof draftObjOrHtml === 'object' && draftObjOrHtml.payload) {
            // Build an ordered rendering of known sections
            const p = draftObjOrHtml.payload;
            const parts = [
                { label: 'Chief Complaint', key: 'chief_complaint' },
                { label: 'Subjective', key: 'subjective' },
                { label: 'Objective', key: 'objective' },
                { label: 'Assessment', key: 'assessment' },
                { label: 'Plan', key: 'plan' }
            ];
            html = parts.map(sec => {
                const content = sanitizeHTML(p[sec.key] || '');
                return `<div style="margin-bottom:10px;">
                  <div style="font-weight:700;color:#111827;margin-bottom:6px;">${sec.label}</div>
                  <div style="color:#111827;line-height:1.45;">${content || '<span style="color:#6b7280;font-style:italic;">(none)</span>'}</div>
                </div>`;
            }).join('');
        } else {
            html = sanitizeHTML(String(draftObjOrHtml));
        }
        viewerEl.innerHTML = html;
    }

    // Compose a merged draft object: keep metadata from local (or server) and payloads merged via mergedHtml.
    function buildMergedDraftObject(baseMeta, mergedHtml) {
        // The mergedHtml is expected to contain the full note (all sections). We will store it into the 'subjective' field
        // if we cannot intelligently split sections. Reasonable approach: put mergedHtml into all main fields as fallback.
        const metadata = baseMeta || { step: 'notes', lastEditedAt: new Date().toISOString(), clientDraftVersion: (window.clientDraftVersion||0) + 1 };
        const payload = {
            chief_complaint: mergedHtml,
            subjective: mergedHtml,
            objective: mergedHtml,
            assessment: mergedHtml,
            plan: mergedHtml
        };
        return { metadata, payload };
    }

    // show modal, attach handlers and return a promise that resolves with { action, mergedDraft? , localDraft? }
    function presentMergeUI(serverDraft, resolveCb) {
        const modal = createModal();
        modal.style.display = 'flex';

        // obtain local draft
        const localDraft = (window.__ECWoundAutosave && window.__ECWoundAutosave.localDraft) ? window.__ECWoundAutosave.localDraft : getLocalDraftObject();

        const serverView = modal.querySelector('#dm-server-view');
        const localView = modal.querySelector('#dm-local-view');
        const mergedEditor = modal.querySelector('#dm-merged-editor');
        const mergeRow = modal.querySelector('#dm-merge-row');

        renderDraftInViewer(serverDraft, serverView);
        renderDraftInViewer(localDraft, localView);

        // set initial merged editor content to a sensible concatenation (server then local)
        mergedEditor.innerHTML = sanitizeHTML(
            (typeof serverDraft === 'object' && serverDraft.payload ? (serverDraft.payload.subjective || '') : (typeof serverDraft === 'string' ? serverDraft : '')) +
            '<hr style="border:none;border-top:1px dashed #e6e6e6;margin:12px 0;">' +
            (localDraft && localDraft.payload ? (localDraft.payload.subjective || '') : '')
        );

        function closeModal() {
            modal.style.display = 'none';
        }

        // single event handler for modal actions
        function onAction(ev) {
            const a = (ev && ev.detail && ev.detail.action) ? ev.detail.action : null;
            if (!a) return;
            if (a === 'use_server') {
                closeModal();
                try { resolveCb('use_server'); } catch(e){ console.warn(e); }
            } else if (a === 'use_local') {
                closeModal();
                try { resolveCb('use_local', localDraft); } catch(e){ console.warn(e); }
            } else if (a === 'open_merge') {
                // show merge editor row
                mergeRow.style.display = 'block';
                // focus editable area
                mergedEditor.focus();
            } else if (a === 'cancel') {
                closeModal();
                try { resolveCb('cancel'); } catch(e){ console.warn(e); }
            } else if (a === 'cancel_merge') {
                mergeRow.style.display = 'none';
            } else if (a === 'confirm_merge') {
                // collect mergedHtml and build mergedDraftObj
                const mergedHtml = sanitizeHTML(mergedEditor.innerHTML || '');
                const mergedObj = buildMergedDraftObject(localDraft && localDraft.metadata ? localDraft.metadata : serverDraft && serverDraft.metadata ? serverDraft.metadata : null, mergedHtml);
                closeModal();
                try { resolveCb('merge', mergedObj); } catch(e){ console.warn(e); }
            }
        }

        modal.addEventListener('dm-action', onAction, { once: false });

        // keyboard accessibility: Esc to cancel
        function onKey(e) {
            if (e.key === 'Escape') {
                modal.dispatchEvent(new CustomEvent('dm-action', { detail: { action: 'cancel' } }));
            }
        }
        document.addEventListener('keydown', onKey);

        // cleanup on close
        const observer = new MutationObserver(() => {
            if (modal.style.display === 'none') {
                modal.removeEventListener('dm-action', onAction);
                document.removeEventListener('keydown', onKey);
                observer.disconnect();
            }
        });
        observer.observe(modal, { attributes: true, attributeFilter: ['style'] });
    }

    // Expose the global handler used by AutosaveManager:
    // window.handleDraftConflict(serverDraft, resolve)
    // resolve is a function provided by the caller; we will call resolve(action, mergedDraft)
    window.handleDraftConflict = window.handleDraftConflict || function (serverDraft, resolve) {
            // Defensive: if no resolve function provided, just do nothing
            if (typeof resolve !== 'function') {
                console.warn('handleDraftConflict called without resolve function; defaulting to use_server');
                return;
            }

            // Present UI, and forward choices to resolve
            presentMergeUI(serverDraft, function(action, draftObj) {
                // Map UI actions to the semantics expected by AutosaveManager's onConflict resolver
                // Accept: 'use_server' | 'use_local' | 'merge' | 'cancel'
                if (action === 'use_server') {
                    // Caller expects resolve('use_server')
                    resolve('use_server');
                } else if (action === 'use_local') {
                    // Provide the local draft when choosing local (some callers accept second parameter)
                    resolve('use_local', draftObj || getLocalDraftObject());
                } else if (action === 'merge') {
                    // Provide merged draft object as the second parameter
                    resolve('merge', draftObj);
                } else if (action === 'cancel') {
                    // Treat cancel as returning without taking action; default to keep server (safer)
                    resolve('use_server');
                } else {
                    // fallback
                    resolve('use_server');
                }
            });
        };

    // Small convenience - if the integration exposes an API to update localDraft before conflict,
    // allow callers to set window.__ECWoundAutosave.localDraft = {...}
    window.__ECWoundDraftMergeUI = {
        presentMergeUI,
        sanitizeHTML,
        getLocalDraftObject
    };

})();