/**
 * autosave_alert_adapter.js
 *
 * Small adapter that listens to autosave events and shows floating alerts.
 * - Listens for:
 *     ec:autosave:success  -> shows a "Autosaved" success message
 *     ec:autosave:failure  -> shows an error message "Autosave failed — saved locally"
 *     ec:autosave:serverDraftLoaded -> optional info about loaded server draft
 *
 * - Uses window.showFloatingAlert(message, type, timeoutMs) if available,
 *   otherwise falls back to console logs.
 *
 * Include this file after floating_alerts.js and before or alongside AutosaveManagerDrafts.
 */

(function () {
    'use strict';

    function info(msg, timeout) {
        if (typeof window.showFloatingAlert === 'function') {
            window.showFloatingAlert(msg, 'info', timeout || 2500);
        } else {
            console.info(msg);
        }
    }
    function success(msg, timeout) {
        if (typeof window.showFloatingAlert === 'function') {
            window.showFloatingAlert(msg, 'success', timeout || 2000);
        } else {
            console.info(msg);
        }
    }
    function error(msg, timeout) {
        if (typeof window.showFloatingAlert === 'function') {
            window.showFloatingAlert(msg, 'error', timeout || 5000);
        } else {
            console.error(msg);
        }
    }

    // Autosave success
    window.addEventListener('ec:autosave:success', function (ev) {
        try {
            const d = ev && ev.detail ? ev.detail : {};
            let msg = 'Autosaved';
            // If server gave extra info we can include small detail
            if (d.serverResponse && d.serverResponse.saved_key) {
                msg += ' ✓';
            }
            success(msg, 1800);
        } catch (e) {
            console.warn('autosave_alert_adapter success handler error', e);
        }
    });

    // Autosave failure -> saved locally
    window.addEventListener('ec:autosave:failure', function (ev) {
        try {
            const d = ev && ev.detail ? ev.detail : {};
            let msg = 'Autosave failed — saved locally';
            if (d.reason) {
                // show short reason if available
                let r = String(d.reason).replace(/\s+/g, ' ').trim();
                if (r.length > 120) r = r.slice(0, 117) + '...';
                msg += ': ' + r;
            }
            error(msg, 7000);
        } catch (e) {
            console.warn('autosave_alert_adapter failure handler error', e);
        }
    });

    // Server draft loaded (informational)
    window.addEventListener('ec:autosave:serverDraftLoaded', function (ev) {
        try {
            const d = ev && ev.detail ? ev.detail : {};
            // Small informational alert optionally shown
            // If you prefer silent, comment out the next line
            info('Server draft available — you may merge it into this note', 4000);
        } catch (e) {
            console.warn('autosave_alert_adapter serverDraftLoaded handler error', e);
        }
    });

})();