// Filename: js/mdi_navigation.js
// Description: Helper script to intercept navigation and open pages in MDI tabs
// This script should be loaded in pages that are rendered inside the MDI shell

(function() {
    'use strict';

    // Check if we're in the parent MDI shell or in an iframe
    const isInIframe = window.self !== window.top;
    const isInMDI = isInIframe || (window.location.href.includes('mdi_shell.php'));

    // Helper function to navigate to a page in a new tab
    function navigateInTab(url, title = null, icon = null) {
        // Check if we're in a controlled navigation (like form submission redirect)
        if (window._controlledNavigation) {
            console.log('[MDI Nav] Controlled navigation detected, bypassing interception');
            return; // Don't intercept
        }
        
        if (isInIframe && window.parent.openPageInTab) {
            // We're in an iframe, tell parent to open a new tab
            window.parent.openPageInTab(url, title, icon);
        } else if (window.openPageInTab) {
            // We're in the parent, use the global function
            window.openPageInTab(url, title, icon);
        } else {
            // Fallback to regular navigation
            window.location.href = url;
        }
    }

    // Intercept all link clicks and open them in tabs if appropriate
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        
        if (!link) return;
        
        // Skip if link has data-no-mdi attribute
        if (link.hasAttribute('data-no-mdi')) return;
        
        // Skip external links
        const href = link.getAttribute('href');
        if (href.startsWith('http://') || href.startsWith('https://') || href.startsWith('//')) {
            return;
        }
        
        // Skip anchors, javascript:, mailto:, tel:
        if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }
        
        // Skip logout
        if (href.includes('logout.php')) {
            return;
        }
        
        // Skip login page
        if (href.includes('login.php')) {
            return;
        }

        // Get title from link text or title attribute
        let title = link.getAttribute('data-tab-title') || link.textContent.trim() || null;
        
        // Get icon from data attribute
        let icon = link.getAttribute('data-tab-icon') || null;
        
        // Prevent default navigation
        e.preventDefault();
        
        // Open in tab
        navigateInTab(href, title, icon);
    }, true); // Use capture phase to catch all clicks

    // Intercept form submissions (optional - for forms that navigate)
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Skip if form has data-no-mdi attribute
        if (form.hasAttribute('data-no-mdi')) return;
        
        // Skip if form method is POST (usually API calls)
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'POST') return;
        
        // For GET forms, intercept and open in new tab
        const action = form.getAttribute('action') || window.location.href;
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const url = action + (params ? '?' + params : '');
        
        e.preventDefault();
        navigateInTab(url);
    });

    // Expose global function for programmatic navigation
    window.navigateInTab = navigateInTab;

    // Replace window.location.href assignments (but NOT window.location.replace)
    // Only define if not already defined (prevents error on multiple loads)
    const descriptor = Object.getOwnPropertyDescriptor(window.location, 'href');
    if (descriptor && descriptor.configurable !== false) {
        const originalLocationSetter = descriptor.set;
        const originalReplace = window.location.replace;
        
        Object.defineProperty(window.location, 'href', {
            set: function(url) {
                // Skip if starts with hash or same origin checks
                if (url.startsWith('#') || url.includes('logout.php') || url.includes('login.php')) {
                    originalLocationSetter.call(window.location, url);
                    return;
                }
                
                // Open in tab instead
                navigateInTab(url);
            },
            get: function() {
                return window.location.href;
            },
            configurable: true
        });
    }

})();
