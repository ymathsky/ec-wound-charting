// Autosave compatibility shim - aliases expected function names and provides fallbacks
// Include this file after visit_notes_logic.js (so collectNoteDraftPayload is defined).
(function () {
    'use strict';

    // Provide collectDraftPayload expected by older autosave code
    window.collectDraftPayload = window.collectDraftPayload || window.collectNoteDraftPayload || function() {
            try {
                if (typeof window.collectNoteDraftPayload === 'function') return window.collectNoteDraftPayload();

                const metadata = {
                    step: 'notes',
                    lastEditedAt: new Date().toISOString(),
                    lastEditedByDevice: (navigator.userAgent || 'web').slice(0,200),
                    clientDraftVersion: (window.clientDraftVersion || 0) + 1
                };

                const payload = {
                    chief_complaint: (document.getElementById('chief_complaint_input') && document.getElementById('chief_complaint_input').value) || '',
                    subjective: (document.getElementById('subjective') && document.getElementById('subjective').value) || '',
                    objective: (document.getElementById('objective') && document.getElementById('objective').value) || '',
                    assessment: (document.getElementById('assessment') && document.getElementById('assessment').value) || '',
                    plan: (document.getElementById('plan') && document.getElementById('plan').value) || ''
                };

                window.clientDraftVersion = metadata.clientDraftVersion;
                return { metadata, payload };
            } catch (e) {
                console.warn('autosave_compat_shim fallback failed', e);
                return { metadata: {}, payload: {} };
            }
        };

    // Alias applyServerDraft for integrations expecting a different name
    window.applyDraftFromServer = window.applyDraftFromServer || window.applyServerDraft || function(draftObj) {
            if (typeof window.applyServerDraft === 'function') return window.applyServerDraft(draftObj);
            try {
                if (!draftObj || !draftObj.payload) return;
                const p = draftObj.payload;
                if (typeof window.setFieldContent === 'function') {
                    setFieldContent('chief_complaint', p.chief_complaint || '');
                    setFieldContent('subjective', p.subjective || '');
                    setFieldContent('objective', p.objective || '');
                    setFieldContent('assessment', p.assessment || '');
                    setFieldContent('plan', p.plan || '');
                } else {
                    // best-effort fallback to DOM
                    document.getElementById('chief_complaint_input') && (document.getElementById('chief_complaint_input').value = p.chief_complaint || '');
                    document.getElementById('subjective') && (document.getElementById('subjective').value = p.subjective || '');
                    document.getElementById('objective') && (document.getElementById('objective').value = p.objective || '');
                    document.getElementById('assessment') && (document.getElementById('assessment').value = p.assessment || '');
                    document.getElementById('plan') && (document.getElementById('plan').value = p.plan || '');
                }
            } catch (e) { console.warn('applyDraftFromServer failed', e); }
        };

})();