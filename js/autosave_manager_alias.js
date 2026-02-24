/**
 * js/autosave_manager_alias.js
 *
 * Constructor alias so code that does `new AutosaveManager(...)` works while the real
 * implementation lives in window.AutosaveManagerDrafts (singleton).
 *
 * Behavior:
 * - Exposes a constructor `AutosaveManager` (doesn't overwrite if already defined).
 * - Delegates calls to `window.AutosaveManagerDrafts` when it becomes available.
 * - Queues init/start/stop calls made before the real impl is ready and flushes them once available.
 * - Methods that return promises (forceSave) return a promise that resolves once the impl is ready
 *   and the delegated call completes.
 *
 * Include this file after autosave_manager_drafts.js (which should set window.AutosaveManagerDrafts)
 * or include it inline immediately before quill_autosave_integration.js to guarantee the constructor exists.
 */

(function () {
    'use strict';

    if (window.AutosaveManager) {
        // Do not clobber an existing implementation
        console.debug && console.debug('autosave_manager_alias: AutosaveManager already defined; skipping alias.');
        return;
    }

    function AutosaveManager(options) {
        if (!(this instanceof AutosaveManager)) return new AutosaveManager(options);
        this._options = options || {};
        this._impl = (window.AutosaveManagerDrafts && typeof window.AutosaveManagerDrafts === 'object') ? window.AutosaveManagerDrafts : null;
        this._queued = []; // queue of { name, args, resolve, reject } for calls before impl ready
        this._ready = !!this._impl;

        // Start polling for the real implementation if not available yet.
        if (!this._ready) {
            this._startWaitingForImpl();
        }
    }

    AutosaveManager.prototype._startWaitingForImpl = function () {
        if (this._polling) return;
        const self = this;
        let attempts = 0;
        const maxAttempts = 50; // ~5 seconds at 100ms
        this._polling = setInterval(function () {
            attempts++;
            if (window.AutosaveManagerDrafts && typeof window.AutosaveManagerDrafts === 'object') {
                self._impl = window.AutosaveManagerDrafts;
                self._ready = true;
                clearInterval(self._polling);
                self._polling = null;
                // If init options were provided and impl has init, call it
                try { self._flushQueueToImpl(); } catch (e) { console.warn('autosave_manager_alias flush error', e); }
            } else if (attempts >= maxAttempts) {
                clearInterval(self._polling);
                self._polling = null;
                console.warn('autosave_manager_alias: AutosaveManagerDrafts did not appear after timeout');
                // reject queued calls that expect promises
                self._rejectQueued(new Error('Autosave implementation not available'));
            }
        }, 100);
    };

    AutosaveManager.prototype._flushQueueToImpl = function () {
        if (!this._impl) return;
        // If impl has init and we stored options, call init first (so impl is configured)
        try {
            if (this._options && typeof this._impl.init === 'function') {
                // impl.init may return the impl or the instance; call and ignore return for now
                try { this._impl.init(this._options); } catch (e) { console.warn('autosave_manager_alias: impl.init threw', e); }
            }
        } catch (e) { /* ignore */ }

        // Process queued operations in order
        while (this._queued.length) {
            const item = this._queued.shift();
            try {
                const fn = this._impl[item.name];
                if (typeof fn === 'function') {
                    const result = fn.apply(this._impl, item.args || []);
                    // If caller expects a promise (stored resolve), forward result
                    if (item.resolve) {
                        // If result is a Promise-like, link it; else resolve immediately with result
                        if (result && typeof result.then === 'function') {
                            result.then(item.resolve).catch(item.reject);
                        } else {
                            item.resolve(result);
                        }
                    }
                } else {
                    if (item.resolve) item.resolve(undefined);
                }
            } catch (err) {
                if (item.reject) item.reject(err);
            }
        }
    };

    AutosaveManager.prototype._rejectQueued = function (err) {
        while (this._queued.length) {
            const item = this._queued.shift();
            if (item.reject) item.reject(err);
        }
    };

    AutosaveManager.prototype._resolveImpl = function () {
        if (!this._impl && window.AutosaveManagerDrafts) {
            this._impl = window.AutosaveManagerDrafts;
            this._ready = true;
            this._flushQueueToImpl();
        }
        return this._impl;
    };

    // Public API: init
    AutosaveManager.prototype.init = function (opts) {
        this._options = Object.assign({}, this._options || {}, opts || {});
        if (this._resolveImpl() && typeof this._impl.init === 'function') {
            try { return this._impl.init(this._options); } catch (e) { console.warn('autosave_manager_alias.init error', e); return this; }
        }
        // If impl not ready, queue the init call (no promise returned)
        this._queued.push({ name: 'init', args: [this._options] });
        return this;
    };

    AutosaveManager.prototype.start = function () {
        if (this._resolveImpl() && typeof this._impl.start === 'function') {
            try { return this._impl.start(); } catch (e) { console.warn('autosave_manager_alias.start error', e); return; }
        }
        // queue start (no promise)
        this._queued.push({ name: 'start', args: [] });
    };

    AutosaveManager.prototype.stop = function () {
        if (this._resolveImpl() && typeof this._impl.stop === 'function') {
            try { return this._impl.stop(); } catch (e) { console.warn('autosave_manager_alias.stop error', e); return; }
        }
        this._queued.push({ name: 'stop', args: [] });
    };

    // forceSave should return a promise
    AutosaveManager.prototype.forceSave = function () {
        var self = this;
        return new Promise(function (resolve, reject) {
            if (self._resolveImpl() && typeof self._impl.forceSave === 'function') {
                try {
                    const r = self._impl.forceSave();
                    if (r && typeof r.then === 'function') r.then(resolve).catch(reject);
                    else resolve(r);
                    return;
                } catch (e) {
                    reject(e);
                    return;
                }
            }
            // queue and attach resolve/reject
            self._queued.push({ name: 'forceSave', args: [], resolve: resolve, reject: reject });
            // ensure we are polling for impl
            if (!self._polling) self._startWaitingForImpl();
        });
    };

    AutosaveManager.prototype.getLocalDraft = function () {
        if (this._resolveImpl() && typeof this._impl.getLocalDraft === 'function') {
            try { return this._impl.getLocalDraft(); } catch (e) { console.warn('autosave_manager_alias.getLocalDraft error', e); return null; }
        }
        // If not ready, try to use the singleton directly if present in global (non-instance)
        if (window.AutosaveManagerDrafts && typeof window.AutosaveManagerDrafts.getLocalDraft === 'function') {
            try { return window.AutosaveManagerDrafts.getLocalDraft(); } catch (e) { return null; }
        }
        return null;
    };

    AutosaveManager.prototype.clearLocalDraft = function () {
        if (this._resolveImpl() && typeof this._impl.clearLocalDraft === 'function') {
            try { return this._impl.clearLocalDraft(); } catch (e) { console.warn('autosave_manager_alias.clearLocalDraft error', e); return null; }
        }
        if (window.AutosaveManagerDrafts && typeof window.AutosaveManagerDrafts.clearLocalDraft === 'function') {
            try { return window.AutosaveManagerDrafts.clearLocalDraft(); } catch (e) { return null; }
        }
        return null;
    };

    // Expose the constructor on window
    window.AutosaveManager = AutosaveManager;

    // Convenience: if the singleton already exists, also expose as AutosaveManagerSingleton
    if (window.AutosaveManagerDrafts && !window.AutosaveManagerSingleton) {
        window.AutosaveManagerSingleton = window.AutosaveManagerDrafts;
    }

    // Watch for the singleton to appear and, when it does, set AutosaveManagerSingleton and flush any queues
    if (!window.AutosaveManagerDrafts) {
        var aliasObserverAttempts = 0;
        var aliasObserverMax = 50;
        var aliasObserver = setInterval(function () {
            aliasObserverAttempts++;
            if (window.AutosaveManagerDrafts) {
                window.AutosaveManagerSingleton = window.AutosaveManagerDrafts;
                clearInterval(aliasObserver);
            } else if (aliasObserverAttempts >= aliasObserverMax) {
                clearInterval(aliasObserver);
            }
        }, 100);
    }

})();