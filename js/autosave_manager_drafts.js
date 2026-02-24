/**
 * autosave_manager_drafts.js
 *
 * Lightweight autosave manager for visit notes drafts.
 * - Posts JSON to server endpoints (save/load/delete) configured via window.AUTOSAVE_ENDPOINTS
 * - Falls back to localStorage when server is unavailable or returns error
 * - Dispatches custom events for other modules to consume:
 *     - window.dispatchEvent(new CustomEvent('ec:autosave:success', { detail: {...} }));
 *     - window.dispatchEvent(new CustomEvent('ec:autosave:failure', { detail: {...} }));
 *
 * Usage:
 *   AutosaveManagerDrafts.init({ appointment_id: 58, user_id: 16, intervalMs: 30000 });
 *   AutosaveManagerDrafts.start();
 *   AutosaveManagerDrafts.stop();
 *   AutosaveManagerDrafts.forceSave(); // returns Promise
 *
 * Dependencies:
 *   - Expects a function window.collectDraftPayload() (or window.collectNoteDraftPayload)
 *     that returns either { metadata: {...}, payload: {...} } OR the payload object itself.
 *   - window.AUTOSAVE_ENDPOINTS: { SAVE, LOAD, DELETE } (relative paths recommended)
 *
 * This file intentionally does not show UI; it emits events that other modules (e.g. autosave_alert_adapter.js)
 * can listen to to show floating alerts, etc.
 */

(function (global) {
    'use strict';

    const DEFAULT_INTERVAL = 30000; // ms

    // Resolve endpoints (defaults fall back to relative paths)
    function getEndpoints() {
        const cfg = (global.AUTOSAVE_ENDPOINTS && typeof global.AUTOSAVE_ENDPOINTS === 'object') ? global.AUTOSAVE_ENDPOINTS : null;
        return {
            SAVE: (cfg && cfg.SAVE) || 'api/save_draft.php',
            LOAD: (cfg && cfg.LOAD) || 'api/load_draft.php',
            DELETE: (cfg && cfg.DELETE) || 'api/delete_draft.php'
        };
    }

    // Helper: safe JSON fetch wrapper
    async function fetchJson(url, options = {}) {
        const opts = Object.assign({ credentials: 'same-origin' }, options);
        const r = await fetch(url, opts);
        const text = await r.text();
        let json = null;
        try {
            json = text ? JSON.parse(text) : null;
        } catch (e) {
            // Not JSON
            throw new Error('Invalid JSON response from ' + url + ' : ' + e.message + ' -- raw: ' + text.slice(0, 200));
        }
        if (!r.ok) {
            // include message if provided by backend json
            const errMsg = (json && json.message) ? json.message : ('HTTP ' + r.status);
            const err = new Error(errMsg);
            err.responseJson = json;
            err.status = r.status;
            throw err;
        }
        return json;
    }

    // LocalStorage helpers (fallback)
    function localKey(appointment_id, user_id) {
        return 'autosave_local_' + appointment_id + '::' + (user_id !== null && user_id !== undefined ? user_id : 'anon');
    }
    function saveLocalDraft(appointment_id, user_id, data) {
        try {
            localStorage.setItem(localKey(appointment_id, user_id), JSON.stringify({
                saved_at: new Date().toISOString(),
                data: data
            }));
            return true;
        } catch (e) {
            console.warn('saveLocalDraft failed', e);
            return false;
        }
    }
    function loadLocalDraft(appointment_id, user_id) {
        try {
            const raw = localStorage.getItem(localKey(appointment_id, user_id));
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            console.warn('loadLocalDraft failed', e);
            return null;
        }
    }
    function deleteLocalDraft(appointment_id, user_id) {
        try {
            localStorage.removeItem(localKey(appointment_id, user_id));
            return true;
        } catch (e) {
            console.warn('deleteLocalDraft failed', e);
            return false;
        }
    }

    // Normalize collector output to { metadata, payload }
    function collectPayloadNormalized() {
        try {
            const collector = global.collectDraftPayload || global.collectNoteDraftPayload;
            if (typeof collector !== 'function') {
                throw new Error('No collectDraftPayload / collectNoteDraftPayload function found on window');
            }
            const collected = collector();
            if (!collected) return { metadata: {}, payload: {} };
            if (collected.metadata || collected.payload) {
                return {
                    metadata: collected.metadata || {},
                    payload: collected.payload || {}
                };
            }
            // If collector returned the payload object directly
            return { metadata: {}, payload: collected };
        } catch (e) {
            console.warn('collectPayloadNormalized error:', e);
            return { metadata: {}, payload: {} };
        }
    }

    // Event dispatch helper
    function emitEvent(name, detail) {
        try {
            global.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
        } catch (e) {
            console.warn('emitEvent failed', e);
        }
    }

    // The manager
    const AutosaveManagerDrafts = {
        _intervalMs: DEFAULT_INTERVAL,
        _timer: null,
        _appointmentId: null,
        _userId: null,
        _endpoints: getEndpoints(),
        _running: false,

        init(options = {}) {
            this._endpoints = getEndpoints();
            this._intervalMs = typeof options.intervalMs === 'number' ? options.intervalMs : (window.AUTOSAVE_INTERVAL_MS || DEFAULT_INTERVAL);
            // appointment_id and user_id can come from options or from window.phpVars / window.PAGE_VARS
            this._appointmentId = options.appointment_id || options.appointmentId || (global.phpVars && global.phpVars.appointmentId) || (global.PAGE_VARS && global.PAGE_VARS.appointment_id) || null;
            this._userId = (options.user_id || options.userId || (global.phpVars && global.phpVars.userId) || (global.PAGE_VARS && global.PAGE_VARS.user_id)) || null;

            // Normalize to numbers
            this._appointmentId = this._appointmentId ? parseInt(this._appointmentId, 10) : 0;
            this._userId = (this._userId !== null && this._userId !== undefined && this._userId !== '') ? parseInt(this._userId, 10) : null;

            // expose small helpers
            global.AutosaveManagerDrafts = this;
            return this;
        },

        _validateContextOrThrow() {
            if (!this._appointmentId || isNaN(this._appointmentId) || this._appointmentId <= 0) {
                throw new Error('Missing or invalid appointment_id for autosave manager');
            }
        },

        async loadServerDraft() {
            // Try to load a draft from server; returns draft object or null
            try {
                this._validateContextOrThrow();
                const url = `${this._endpoints.LOAD}?appointment_id=${encodeURIComponent(this._appointmentId)}&user_id=${encodeURIComponent(this._userId ?? '')}`;
                const json = await fetchJson(url, { method: 'GET' });
                if (json && json.success && json.draft) {
                    return json.draft;
                }
                // if server returned not found, return null
                return null;
            } catch (err) {
                console.warn('loadServerDraft:', err);
                return null;
            }
        },

        async deleteServerDraft() {
            try {
                this._validateContextOrThrow();
                const payload = { appointment_id: this._appointmentId, user_id: this._userId };
                const res = await fetchJson(this._endpoints.DELETE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                return res;
            } catch (err) {
                console.warn('deleteServerDraft:', err);
                return null;
            }
        },

        async save() {
            // Build payload
            try {
                this._validateContextOrThrow();
            } catch (err) {
                console.warn(err.message);
                // do not throw to avoid uncaught exceptions in interval
                return { success: false, message: err.message };
            }

            const collected = collectPayloadNormalized();
            const payload = {
                appointment_id: this._appointmentId,
                user_id: this._userId,
                metadata: collected.metadata || {},
                payload: collected.payload || {}
            };

            // Try to POST JSON to server
            try {
                const resJson = await fetchJson(this._endpoints.SAVE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                // Expect server to return { success: true, ... } on success
                if (resJson && resJson.success) {
                    emitEvent('ec:autosave:success', {
                        appointment_id: this._appointmentId,
                        user_id: this._userId,
                        serverResponse: resJson
                    });
                    // Remove any local fallback on success
                    deleteLocalDraft(this._appointmentId, this._userId);
                    return { success: true, server: true, response: resJson };
                } else {
                    // server responded but indicated failure
                    const msg = (resJson && resJson.message) ? resJson.message : 'Server save failed';
                    console.warn('Autosave server responded with failure:', resJson);
                    // fallback to local save
                    const okLocal = saveLocalDraft(this._appointmentId, this._userId, payload);
                    emitEvent('ec:autosave:failure', {
                        appointment_id: this._appointmentId,
                        user_id: this._userId,
                        reason: msg,
                        savedLocal: okLocal
                    });
                    return { success: false, server: true, response: resJson };
                }
            } catch (err) {
                // network / parse / other error - fallback to localStorage
                console.warn('Autosave save error; saving locally', err);
                const okLocal = saveLocalDraft(this._appointmentId, this._userId, payload);
                emitEvent('ec:autosave:failure', {
                    appointment_id: this._appointmentId,
                    user_id: this._userId,
                    reason: err.message || 'Network/Fetch error',
                    savedLocal: okLocal
                });
                return { success: false, server: false, error: err };
            }
        },

        start() {
            try {
                this._validateContextOrThrow();
            } catch (err) {
                console.warn('AutosaveManager start aborted:', err.message);
                return;
            }
            if (this._running) return;
            this._running = true;

            // Initial immediate attempt to save/load server draft (load server draft for merge flow)
            // but do not block start
            (async () => {
                try {
                    const draft = await this.loadServerDraft();
                    if (draft) {
                        emitEvent('ec:autosave:serverDraftLoaded', {
                            appointment_id: this._appointmentId,
                            user_id: this._userId,
                            draft: draft
                        });
                    }
                } catch (e) {
                    console.warn('initial loadServerDraft error', e);
                }
            })();

            // Start interval
            this._timer = setInterval(() => {
                // perform save but do not await to keep timer unblocked
                this.save().catch(e => {
                    // errors are handled inside save
                    console.warn('AutosaveManager save error (interval)', e);
                });
            }, this._intervalMs);

            // Also attempt one immediate save
            this.save().catch(() => {});
        },

        stop() {
            if (!this._running) return;
            this._running = false;
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        async forceSave() {
            return this.save();
        },

        // Utility to expose local fallback draft (useful for UI)
        getLocalDraft() {
            return loadLocalDraft(this._appointmentId, this._userId);
        },

        // Utility to clear local fallback
        clearLocalDraft() {
            return deleteLocalDraft(this._appointmentId, this._userId);
        }
    };

    // Expose on window
    global.AutosaveManagerDrafts = AutosaveManagerDrafts;

    // Auto-init convention: if page defines AUTOSAVE_AUTO_START = true, initialize and start automatically
    try {
        if (global.AUTOSAVE_AUTO_START) {
            AutosaveManagerDrafts.init(global.AUTOSAVE_CONFIG || {}).start();
        }
    } catch (e) {
        // ignore
    }
})(window);