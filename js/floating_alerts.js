// js/floating_alerts.js
// Robust floating alert helper (attaches to window.showFloatingAlert, hideFloatingAlert, clearFloatingAlerts)
// Safe to include before DOMContentLoaded. Logs activity to console for debugging.

(function () {
    'use strict';

    // ensure single container creation (works even before DOMContentLoaded)
    function getOrCreateContainer() {
        let c = document.getElementById('floatingAlertContainer');
        if (c) return c;

        c = document.createElement('div');
        c.id = 'floatingAlertContainer';
        c.setAttribute('aria-live', 'polite');
        c.setAttribute('role', 'status');
        // minimal inline base style in case CSS not loaded yet; CSS file will refine
        Object.assign(c.style, {
            position: 'fixed',
            right: '1rem',
            bottom: '1.25rem',
            display: 'flex',
            flexDirection: 'column',
            gap: '0.5rem',
            alignItems: 'flex-end',
            zIndex: 2147483000,
            pointerEvents: 'none',
            maxWidth: 'calc(100% - 2rem)'
        });

        // append to body as soon as available
        if (document.body) {
            document.body.appendChild(c);
        } else {
            // if body not yet present, wait for DOMContentLoaded
            document.addEventListener('DOMContentLoaded', function () {
                document.body.appendChild(c);
            }, { once: true });
        }
        console.debug('floating_alerts: container created');
        return c;
    }

    // map for CSS class types if CSS present
    const TYPE_CLASS = {
        success: 'fa-success',
        error: 'fa-error',
        info: 'fa-info',
        warn: 'fa-warn'
    };

    function createAlertElement(message, type) {
        const alert = document.createElement('div');
        alert.className = 'floating-alert ' + (TYPE_CLASS[type] || TYPE_CLASS.info);
        alert.setAttribute('role', 'alert');
        alert.setAttribute('tabindex', '-1');
        alert.style.pointerEvents = 'auto'; // make controls clickable

        const msg = document.createElement('div');
        msg.className = 'floating-alert-message';
        msg.innerHTML = message;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'floating-alert-close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Dismiss');
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            hideFloatingAlert(alert);
        });

        alert.appendChild(msg);
        alert.appendChild(closeBtn);
        return alert;
    }

    // show alert API
    window.showFloatingAlert = function (message, type = 'info', timeout = 4000) {
        try {
            const container = getOrCreateContainer();
            const el = createAlertElement(message, type);

            container.appendChild(el);

            // animate in (CSS classes expected from floating_alerts.css)
            // fallback inline animation if CSS not present
            requestAnimationFrame(() => {
                el.classList.add('floating-alert-show');
            });

            // auto-dismiss
            if (timeout && timeout > 0) {
                const id = setTimeout(() => {
                    hideFloatingAlert(el);
                    clearTimeout(id);
                }, timeout);
                // store timeout id for potential cancellation
                el.__timeoutId = id;
            }

            console.debug('floating_alerts: shown', { message: message, type: type });
            return el;
        } catch (e) {
            console.warn('showFloatingAlert error', e);
            return null;
        }
    };

    // hide a single alert
    window.hideFloatingAlert = function (alertEl) {
        try {
            if (!alertEl) return;
            // clear auto timeout if present
            if (alertEl.__timeoutId) {
                clearTimeout(alertEl.__timeoutId);
                delete alertEl.__timeoutId;
            }
            alertEl.classList.remove('floating-alert-show');
            alertEl.classList.add('floating-alert-hide');
            // remove after animation (300ms)
            setTimeout(() => {
                if (alertEl && alertEl.parentNode) alertEl.parentNode.removeChild(alertEl);
            }, 300);
            console.debug('floating_alerts: hide called');
        } catch (e) {
            console.warn('hideFloatingAlert error', e);
        }
    };

    // clear all alerts
    window.clearFloatingAlerts = function () {
        try {
            const c = document.getElementById('floatingAlertContainer');
            if (!c) return;
            while (c.firstChild) c.removeChild(c.firstChild);
            console.debug('floating_alerts: cleared all alerts');
        } catch (e) {
            console.warn('clearFloatingAlerts error', e);
        }
    };

    // small auto-test if query param ?floating_alerts_test=1 is present — helps debug deployments
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.get('floating_alerts_test') === '1') {
            setTimeout(() => window.showFloatingAlert('Floating alert test (success)', 'success', 3000), 200);
            setTimeout(() => window.showFloatingAlert('Floating alert test (info)', 'info', 3000), 700);
        }
    } catch (e) {}
})();