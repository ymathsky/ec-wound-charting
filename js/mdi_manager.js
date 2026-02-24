// Filename: js/mdi_manager.js
// Description: MDI (Multiple Document Interface) Tab Manager for EC EMR

class MDIManager {
    constructor() {
        this.tabs = [];
        this.activeTabId = null;
        this.tabCounter = 0;
        this.maxTabs = 10;
        this.init();
    }

    init() {
        // Load tabs from localStorage
        this.loadTabsFromStorage();
        
        // Render initial tabs
        this.renderTabs();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // If no tabs exist, open dashboard
        if (this.tabs.length === 0) {
            this.openTab('Dashboard', 'dashboard.php?layout=modal', 'layout-dashboard', true);
        } else {
            // Activate the last active tab
            const lastActiveTab = this.tabs.find(t => t.id === this.activeTabId);
            if (lastActiveTab) {
                this.switchTab(lastActiveTab.id);
            } else {
                this.switchTab(this.tabs[0].id);
            }
        }
    }

    setupEventListeners() {
        // Close tab on middle mouse click
        document.getElementById('mdi-tab-bar').addEventListener('mousedown', (e) => {
            if (e.button === 1) { // Middle mouse button
                const tabEl = e.target.closest('.mdi-tab');
                if (tabEl) {
                    const tabId = parseInt(tabEl.dataset.tabId);
                    this.closeTab(tabId);
                    e.preventDefault();
                }
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+W to close current tab
            if (e.ctrlKey && e.key === 'w') {
                e.preventDefault();
                if (this.activeTabId !== null) {
                    this.closeTab(this.activeTabId);
                }
            }
            
            // Ctrl+Tab to switch to next tab
            if (e.ctrlKey && e.key === 'Tab') {
                e.preventDefault();
                this.switchToNextTab();
            }
            
            // Ctrl+Shift+Tab to switch to previous tab
            if (e.ctrlKey && e.shiftKey && e.key === 'Tab') {
                e.preventDefault();
                this.switchToPrevTab();
            }

            // Ctrl+T to open new dashboard tab
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                this.openTab('Dashboard', 'dashboard.php?layout=modal', 'layout-dashboard', false);
            }
        });
    }

    openTab(title, url, icon = 'file', isPinned = false) {
        // Check if URL is already open
        const existingTab = this.tabs.find(t => t.url === url);
        if (existingTab) {
            this.switchTab(existingTab.id);
            return existingTab.id;
        }

        // Check max tabs limit
        if (this.tabs.length >= this.maxTabs) {
            this.showNotification('Maximum tab limit reached. Close some tabs to open new ones.', 'error');
            return null;
        }

        const tabId = ++this.tabCounter;
        const tab = {
            id: tabId,
            title: title,
            url: url,
            icon: icon,
            isPinned: isPinned,
            createdAt: Date.now()
        };

        this.tabs.push(tab);
        this.renderTabs();
        this.switchTab(tabId);
        this.saveTabsToStorage();

        return tabId;
    }

    closeTab(tabId) {
        const tabIndex = this.tabs.findIndex(t => t.id === tabId);
        if (tabIndex === -1) return;

        const tab = this.tabs[tabIndex];
        
        // Don't close pinned tabs
        if (tab.isPinned) {
            this.showNotification('Cannot close pinned tab', 'info');
            return;
        }

        // If closing active tab, switch to another
        if (this.activeTabId === tabId) {
            const nextTab = this.tabs[tabIndex + 1] || this.tabs[tabIndex - 1];
            if (nextTab) {
                this.switchTab(nextTab.id);
            } else {
                this.activeTabId = null;
            }
        }

        this.tabs.splice(tabIndex, 1);
        this.renderTabs();
        this.saveTabsToStorage();

        // If no tabs left, open dashboard
        if (this.tabs.length === 0) {
            this.openTab('Dashboard', 'dashboard.php?layout=modal', 'layout-dashboard', true);
        }
    }

    switchTab(tabId) {
        const tab = this.tabs.find(t => t.id === tabId);
        if (!tab) return;

        this.activeTabId = tabId;
        
        // Update tab UI
        document.querySelectorAll('.mdi-tab').forEach(el => {
            el.classList.remove('active');
        });
        const tabEl = document.querySelector(`[data-tab-id="${tabId}"]`);
        if (tabEl) {
            tabEl.classList.add('active');
        }

        // Update iframe content
        const iframe = document.getElementById('mdi-content-frame');
        if (iframe.src !== tab.url) {
            iframe.src = tab.url;
        }

        // Show iframe for this tab
        iframe.style.display = 'block';

        this.saveTabsToStorage();
    }

    switchToNextTab() {
        if (this.tabs.length === 0) return;
        
        const currentIndex = this.tabs.findIndex(t => t.id === this.activeTabId);
        const nextIndex = (currentIndex + 1) % this.tabs.length;
        this.switchTab(this.tabs[nextIndex].id);
    }

    switchToPrevTab() {
        if (this.tabs.length === 0) return;
        
        const currentIndex = this.tabs.findIndex(t => t.id === this.activeTabId);
        const prevIndex = (currentIndex - 1 + this.tabs.length) % this.tabs.length;
        this.switchTab(this.tabs[prevIndex].id);
    }

    renderTabs() {
        const tabBar = document.getElementById('mdi-tab-bar');
        if (!tabBar) return;

        tabBar.innerHTML = '';

        this.tabs.forEach(tab => {
            const tabEl = document.createElement('div');
            tabEl.className = 'mdi-tab' + (tab.id === this.activeTabId ? ' active' : '');
            tabEl.dataset.tabId = tab.id;
            
            tabEl.innerHTML = `
                <div class="mdi-tab-content" onclick="window.mdiManager.switchTab(${tab.id})">
                    <i data-lucide="${tab.icon}" class="mdi-tab-icon"></i>
                    <span class="mdi-tab-title">${this.truncateTitle(tab.title, 20)}</span>
                    ${tab.isPinned ? '<i data-lucide="pin" class="mdi-tab-pin"></i>' : ''}
                </div>
                ${!tab.isPinned ? `<button class="mdi-tab-close" onclick="event.stopPropagation(); window.mdiManager.closeTab(${tab.id})" title="Close tab (Ctrl+W)"><i data-lucide="x" class="w-3 h-3"></i></button>` : ''}
            `;
            
            tabBar.appendChild(tabEl);
        });

        // Re-initialize Lucide icons
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    truncateTitle(title, maxLength) {
        if (title.length <= maxLength) return title;
        return title.substring(0, maxLength - 3) + '...';
    }

    saveTabsToStorage() {
        const data = {
            tabs: this.tabs,
            activeTabId: this.activeTabId,
            tabCounter: this.tabCounter
        };
        localStorage.setItem('ec_mdi_tabs', JSON.stringify(data));
    }

    loadTabsFromStorage() {
        const stored = localStorage.getItem('ec_mdi_tabs');
        if (stored) {
            try {
                const data = JSON.parse(stored);
                this.tabs = data.tabs || [];
                this.activeTabId = data.activeTabId || null;
                this.tabCounter = data.tabCounter || 0;
            } catch (e) {
                console.error('Failed to load tabs from storage:', e);
                this.tabs = [];
            }
        }
    }

    clearAllTabs() {
        this.tabs = [];
        this.activeTabId = null;
        this.tabCounter = 0;
        localStorage.removeItem('ec_mdi_tabs');
        this.openTab('Dashboard', 'dashboard.php?layout=modal', 'layout-dashboard', true);
    }

    showNotification(message, type = 'info') {
        const toast = document.getElementById('toast-notification');
        if (!toast) return;

        toast.textContent = message;
        toast.className = `show ${type}`;
        
        setTimeout(() => {
            toast.className = '';
        }, 3000);
    }

    // Helper method to get icon based on page type
    static getIconForPage(url) {
        if (url.includes('patient_profile')) return 'user';
        if (url.includes('visit_')) return 'file-text';
        if (url.includes('appointment')) return 'calendar';
        if (url.includes('dashboard')) return 'layout-dashboard';
        if (url.includes('billing')) return 'dollar-sign';
        if (url.includes('medication')) return 'pill';
        if (url.includes('wound')) return 'alert-circle';
        if (url.includes('chat')) return 'message-square';
        return 'file';
    }

    // Helper method to get title from URL
    static getTitleFromURL(url) {
        if (url.includes('patient_profile')) return 'Patient Profile';
        if (url.includes('visit_ai_assistant')) return 'AI Visit';
        if (url.includes('visit_vitals')) return 'Vitals';
        if (url.includes('visit_hpi')) return 'HPI';
        if (url.includes('visit_wounds')) return 'Wounds';
        if (url.includes('visit_medications')) return 'Medications';
        if (url.includes('dashboard')) return 'Dashboard';
        if (url.includes('appointments')) return 'Appointments';
        if (url.includes('billing')) return 'Billing';
        return 'Page';
    }
}

// Global method to open pages in tabs (replaces modal logic)
window.openPageInTab = function(url, title = null, icon = null) {
    if (!window.mdiManager) return;
    
    // Auto-detect title and icon if not provided
    if (!title) {
        title = MDIManager.getTitleFromURL(url);
    }
    if (!icon) {
        icon = MDIManager.getIconForPage(url);
    }

    // Ensure URL has layout=modal parameter
    if (!url.includes('layout=modal')) {
        url += (url.includes('?') ? '&' : '?') + 'layout=modal';
    }

    window.mdiManager.openTab(title, url, icon, false);
};

// Initialize MDI Manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.mdiManager = new MDIManager();
});
