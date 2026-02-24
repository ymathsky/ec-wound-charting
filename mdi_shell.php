<?php
// Filename: mdi_shell.php
// Description: Main MDI (Multiple Document Interface) Shell for EC EMR
// This is the new entry point that wraps all pages in a tabbed interface

require_once 'templates/header.php';
?>

<style>
    /* MDI-specific styles */
    .mdi-layout {
        display: flex;
        height: 100vh;
        width: 100vw;
        overflow: hidden;
    }
    
    .mdi-container {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 0; /* Prevent flex item from overflowing */
        height: 100%;
        overflow: hidden;
    }

    .mdi-tab-bar {
        display: flex;
        background: #1F2937;
        border-bottom: 2px solid #4B5563;
        overflow-x: auto;
        overflow-y: hidden;
        flex-shrink: 0;
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1F2937;
    }

    .mdi-tab-bar::-webkit-scrollbar {
        height: 4px;
    }

    .mdi-tab-bar::-webkit-scrollbar-track {
        background: #1F2937;
    }

    .mdi-tab-bar::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 2px;
    }

    .mdi-tab {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        background: #374151;
        border-right: 1px solid #4B5563;
        cursor: pointer;
        transition: background 0.2s;
        min-width: 150px;
        max-width: 250px;
        position: relative;
        user-select: none;
    }

    .mdi-tab:hover {
        background: #4B5563;
    }

    .mdi-tab.active {
        background: #6366F1;
        color: white;
    }

    .mdi-tab-content {
        display: flex;
        align-items: center;
        flex: 1;
        overflow: hidden;
    }

    .mdi-tab-icon {
        width: 16px;
        height: 16px;
        margin-right: 0.5rem;
        flex-shrink: 0;
        color: #D1D5DB;
    }

    .mdi-tab.active .mdi-tab-icon {
        color: white;
    }

    .mdi-tab-title {
        color: #D1D5DB;
        font-size: 0.875rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mdi-tab.active .mdi-tab-title {
        color: white;
    }

    .mdi-tab-pin {
        width: 12px;
        height: 12px;
        margin-left: 0.5rem;
        color: #FCD34D;
        flex-shrink: 0;
    }

    .mdi-tab-close {
        margin-left: 0.5rem;
        padding: 0.25rem;
        border-radius: 0.25rem;
        background: transparent;
        border: none;
        color: #9CA3AF;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mdi-tab-close:hover {
        background: rgba(239, 68, 68, 0.8);
        color: white;
    }

    .mdi-content {
        flex: 1;
        position: relative;
        overflow: hidden;
        background: #F3F4F6;
    }

    .mdi-content iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }

    /* Hide smart voice UI when requested by child frame */
    .smart-voice-hidden #fab-smart-voice,
    .smart-voice-hidden #smart-command-container {
        display: none !important;
    }

    .mdi-toolbar {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background: white;
        border-bottom: 1px solid #E5E7EB;
        gap: 0.5rem;
    }

    .mdi-toolbar button {
        padding: 0.5rem 1rem;
        background: #6366F1;
        color: white;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: background 0.2s;
    }

    .mdi-toolbar button:hover {
        background: #4F46E5;
    }

    .mdi-toolbar button.secondary {
        background: #6B7280;
    }

    .mdi-toolbar button.secondary:hover {
        background: #4B5563;
    }

    .mdi-toolbar .spacer {
        flex: 1;
    }

    /* Loading state */
    .mdi-loading {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: #6B7280;
    }

    .mdi-loading .spinner {
        margin: 0 auto 1rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .mdi-tab {
            min-width: 120px;
            padding: 0.5rem 0.75rem;
        }

        .mdi-tab-title {
            font-size: 0.8rem;
        }

        .mdi-toolbar button {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
    }
</style>

<div class="mdi-layout">
    <!-- Sidebar -->
    <?php require_once 'templates/sidebar.php'; ?>

    <!-- Main MDI Area -->
    <div class="mdi-container">
        <!-- Tab Bar -->
        <div id="mdi-tab-bar" class="mdi-tab-bar">
            <!-- Tabs will be dynamically inserted here -->
        </div>

        <!-- Content Area -->
        <div class="mdi-content">
            <iframe id="mdi-content-frame"></iframe>
            <div class="mdi-loading" id="mdi-loading">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- Load MDI Manager -->
<script src="js/mdi_manager.js?v=<?php echo time(); ?>"></script>

<script>
    // Show/hide loading indicator
    const iframe = document.getElementById('mdi-content-frame');
    const loading = document.getElementById('mdi-loading');

    iframe.addEventListener('load', function() {
        loading.style.display = 'none';
    });

    iframe.addEventListener('loadstart', function() {
        loading.style.display = 'block';
    });

    // Handle messages from iframes (for tab title updates, etc.)
    window.addEventListener('message', function(event) {
        // Verify origin if needed
        if (event.data.type === 'updateTabTitle') {
            const tabId = event.data.tabId;
            const newTitle = event.data.title;
            if (window.mdiManager && tabId) {
                const tab = window.mdiManager.tabs.find(t => t.id === tabId);
                if (tab) {
                    tab.title = newTitle;
                    window.mdiManager.renderTabs();
                    window.mdiManager.saveTabsToStorage();
                }
            }
        }

        // Handle navigation requests from child frames
        if (event.data.type === 'navigate') {
            const url = event.data.url;
            const title = event.data.title || null;
            const icon = event.data.icon || null;
            window.openPageInTab(url, title, icon);
        }

        // Handle request to replace current active tab (navigation within tab)
        if (event.data.type === 'replaceActiveTab') {
            const url = event.data.url;
            const title = event.data.title;
            const icon = event.data.icon;
            
            if (window.mdiManager && window.mdiManager.activeTabId) {
                const tab = window.mdiManager.tabs.find(t => t.id === window.mdiManager.activeTabId);
                if (tab) {
                    // Update metadata
                    tab.url = url;
                    if (title) tab.title = title;
                    if (icon) tab.icon = icon;
                    
                    window.mdiManager.renderTabs();
                    window.mdiManager.saveTabsToStorage();
                    
                    // Force navigation of iframe if it's not already there
                    // (This handles cases where the child asked parent to navigate)
                    const iframe = document.getElementById('mdi-content-frame');
                    if (iframe && !iframe.src.endsWith(url)) {
                        iframe.src = url;
                    }
                }
            }
        }

        // Toggle Smart Voice UI (e.g., hide on chat page)
        if (event.data.type === 'toggleSmartVoice') {
            const enabled = !!event.data.enabled;
            document.body.classList.toggle('smart-voice-hidden', !enabled);
        }
    });

    // Initialize Lucide icons
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

<?php require_once 'templates/footer.php'; ?>
