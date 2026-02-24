<?php
// Filename: ec/chat.php
// Start session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Real-Time Chat";

// --- Include dependencies ---
include_once 'db_connect.php';
include_once 'audit_log_function.php';

// --- Auth Check ---
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit;
}

// --- Define variables for this page ---
$current_user_id = $_SESSION['ec_user_id'];
$current_user_name = $_SESSION['ec_full_name'] ?? 'Unknown User';
$user_full_name = isset($_SESSION['ec_full_name']) ? htmlspecialchars($_SESSION['ec_full_name']) : 'User';
$user_role = isset($_SESSION['ec_role']) ? htmlspecialchars($_SESSION['ec_role']) : 'Role';


// --- Audit Log Call ---
log_audit(
    $conn,
    $current_user_id,
    $current_user_name,
    "VIEWED",
    "Chat",
    NULL,
    "Chat Page Accessed"
);

// ---
// FINAL FIX: Page Assembly
// ---

// 1. Include the header. Opens <html>, <body>, and <div id="app-wrapper" ...>
include 'templates/header_chat.php';

// 2. Include the sidebar.
include 'templates/sidebar.php';
?>

<style>
    #typing-indicator {
        display: flex;
        align-items: center;
        column-gap: 4px;
        padding: 8px 12px;
        background-color: #f3f4f6;
        border-radius: 12px;
        width: fit-content;
        margin-bottom: 8px;
        margin-left: 10px; /* Align with received messages */
    }
    .typing-dots {
        display: flex;
        align-items: center;
        column-gap: 3px;
    }
    .typing-dots span {
        width: 6px;
        height: 6px;
        background-color: #9ca3af;
        border-radius: 50%;
        animation: typing 1.4s infinite ease-in-out both;
    }
    .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
    .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typing {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }
    .quoted-message {
        border-left: 4px solid #6366f1;
        background-color: #f3f4f6;
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 4px;
        font-size: 0.85rem;
        color: #4b5563;
        cursor: pointer;
    }
    .quoted-message .quoted-sender {
        font-weight: 600;
        color: #6366f1;
        margin-bottom: 2px;
        font-size: 0.75rem;
    }
    .reaction-bubble {
        display: inline-flex;
        align-items: center;
        background-color: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 2px 6px;
        margin-right: 4px;
        margin-top: 4px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .reaction-bubble:hover {
        background-color: #e5e7eb;
    }
    .reaction-bubble.active {
        background-color: #e0e7ff;
        border-color: #c7d2fe;
        color: #4338ca;
    }
    .reaction-picker {
        position: absolute;
        bottom: 100%;
        left: 0;
        z-index: 10;
        margin-bottom: 4px;
    }
    .reaction-picker.hidden {
        display: none !important;
    }
    .reaction-option {
        cursor: pointer;
        padding: 4px;
        border-radius: 50%;
        transition: background-color 0.2s;
        font-size: 1.2rem;
        line-height: 1;
    }
    .reaction-option:hover {
        background-color: #f3f4f6;
        transform: scale(1.2);
    }
    
    /* Hide smart voice button on chat page */
    #fab-smart-voice,
    #smart-command-container {
        display: none !important;
    }
</style>

<!--
  3. Manually create the main content column as the SECOND child of #app-wrapper.
     This code is copied from your original header_chat.php to match the layout.
-->
<div id="main-content" class="flex flex-col flex-1 overflow-hidden">
    <!-- TOP HEADER -->
    <header class="header-main">
        <div class="flex items-center">
            <button id="sidebar-toggle" onclick="openSidebar()" class="md:hidden p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                <i data-lucide="menu"></i>
            </button>

            <h1 class="text-xl font-semibold text-gray-800 ml-2">Real time Messaging</h1>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm font-medium text-gray-700 hidden sm:inline">Welcome, <?php echo $user_full_name; ?> (<?php echo $user_role; ?>)</span>
            <a href="account_profile.php" class="p-2 text-indigo-600 hover:text-indigo-800 transition-colors duration-150">
                <i data-lucide="user-circle" class="w-6 h-6"></i>
            </a>
            <a href="logout.php" class="p-2 text-red-600 hover:text-red-800 transition-colors duration-150">
                <i data-lucide="log-out" class="w-6 h-6"></i>
            </a>
        </div>
    </header>

    <!-- FIX: Removed padding from <main> to allow chat UI to fill the space -->
    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
        <!-- 4. Render the Chat UI (This sits inside the <main> tag) -->
        <div class="w-full h-full flex flex-col md:flex-row shadow-md rounded-lg bg-white overflow-hidden">

            <!-- User List Sidebar -->
            <div id="user-list-container" class="w-full md:w-1/3 border-r border-gray-200 bg-gray-50 flex flex-col max-h-full overflow-y-auto">
                <div class="p-4 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Clinicians/Staff</h2>
                    <div class="mt-2 relative">
                        <input type="text" id="user-search" placeholder="Search users..." class="w-full p-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div id="user-list" class="flex-1 overflow-y-auto">
                    <!-- User list items will be loaded here by chat_logic.js -->
                    <div class="p-4 text-center text-gray-500">Loading users...</div>
                </div>
            </div>

            <!-- Chat Window -->
            <div id="chat-window-container" class="flex-1 flex flex-col hidden md:flex relative">

                <!-- Drag & Drop Overlay -->
                <div id="drag-drop-overlay" class="absolute inset-0 bg-indigo-50 bg-opacity-90 z-50 hidden flex flex-col justify-center items-center border-4 border-indigo-300 border-dashed m-4 rounded-xl">
                    <i class="fas fa-cloud-upload-alt text-6xl text-indigo-500 mb-4 pointer-events-none"></i>
                    <h3 class="text-2xl font-bold text-indigo-700 pointer-events-none">Drop file to upload</h3>
                </div>

                <!-- Chat Header (Recipient Info) -->
                <div id="chat-header" class="p-4 border-b bg-white flex items-center shadow-sm">
                    <button id="back-to-users-btn" class="p-1 mr-2 md:hidden text-gray-600 hover:text-indigo-600 transition">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <i class="fas fa-comments text-indigo-500 mr-3 text-lg"></i>
                    <div class="flex flex-col">
                        <h2 id="recipient-name-header" class="text-lg font-semibold text-gray-800 truncate">Select a user to chat</h2>
                        <div id="chat-header-status" class="text-xs text-gray-500 hidden">Offline</div>
                    </div>
                </div>

                <!-- Messages Area -->
                <div id="messages-area" class="flex-1 p-6 overflow-y-auto space-y-4 bg-gray-50">
                    <div id="chat-start-message" class="text-center text-gray-500 mt-20">
                        <i class="fas fa-hand-point-left text-4xl mb-3"></i>
                        <p>Click on a user from the left to begin a conversation.</p>
                    </div>
                </div>

                <div id="chat-loading-spinner" class="absolute inset-0 bg-white bg-opacity-70 flex justify-center items-center z-10 hidden">
                    <div class="spinner"></div>
                </div>

                <!-- Message Input Area -->
                <div id="message-input-area" class="p-4 border-t bg-white">
                    <div id="reply-preview-area" class="p-2 mb-2 bg-gray-100 border-l-4 border-indigo-500 rounded-r-lg hidden flex justify-between items-center">
                        <div class="flex-1 overflow-hidden">
                            <div class="text-xs font-bold text-indigo-600 mb-1">Replying to <span id="reply-to-name">User</span></div>
                            <div id="reply-to-text" class="text-sm text-gray-600 truncate">Message content...</div>
                        </div>
                        <button id="cancel-reply" class="text-gray-400 hover:text-gray-600 ml-2">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="file-preview-area" class="p-2 mb-2 bg-indigo-50 border border-indigo-200 rounded-lg hidden">
                        <span id="file-name-display" class="text-sm font-medium text-gray-700"></span>
                        <button id="cancel-upload" class="float-right text-red-500 hover:text-red-700 text-lg">&times;</button>
                        <div id="upload-progress" class="w-full bg-gray-200 rounded-full h-1 mt-1 hidden">
                            <div id="progress-bar" class="bg-indigo-600 h-1 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <input type="file" id="file-input" accept="image/*,.pdf,.doc,.docx" multiple style="opacity: 0; position: absolute; z-index: -1; width: 1px; height: 1px;">
                        <button id="attach-file-btn" title="Attach Document/Photo" class="p-3 text-indigo-600 bg-indigo-100 rounded-full hover:bg-indigo-200 transition">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="text" id="message-input" placeholder="Type a message..." class="flex-1 p-3 border rounded-full focus:ring-indigo-500 focus:border-indigo-500" disabled>
                        <button id="send-btn" class="p-3 bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition disabled:bg-indigo-400" disabled>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Initial Prompt for Mobile -->
            <div id="mobile-prompt" class="w-full md:hidden flex flex-col justify-center items-center p-8 text-center text-gray-500 h-full">
                <i class="fas fa-mobile-alt text-6xl mb-4"></i>
                <p class="text-lg font-medium">Please select a user to begin chatting.</p>
            </div>

        </div>
        <!-- End of Chat UI -->
    </main> <!-- Closes <main> -->
</div> <!-- Closes <div id="main-content"> -->

<!-- File Preview Modal -->
<div id="file-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeFileModal()"></div>

        <!-- Modal panel -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">File Preview</h3>
                            <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" onclick="closeFileModal()">
                                <span class="sr-only">Close</span>
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="mt-2 w-full bg-gray-100 rounded-lg overflow-hidden relative" style="height: 80vh;">
                            <iframe id="file-modal-iframe" src="" class="w-full h-full border-0"></iframe>
                            <div id="file-modal-image-container" class="w-full h-full flex items-center justify-center hidden">
                                <img id="file-modal-image" src="" class="max-w-full max-h-full object-contain">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a id="file-download-btn" href="#" download class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Download
                </a>
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeFileModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Forward Message Modal -->
<div id="forward-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="forward-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeForwardModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="forward-modal-title">Forward Message</h3>
                        <div class="mt-2">
                            <input type="text" id="forward-search" placeholder="Search user to forward to..." class="w-full p-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 mb-4">
                            <div id="forward-user-list" class="max-h-60 overflow-y-auto border rounded-md divide-y divide-gray-200">
                                <!-- Users will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeForwardModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="confirmation-modal-title">Delete Message</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="confirmation-modal-message">Are you sure you want to delete this message? This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirmation-confirm-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button type="button" id="confirmation-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Prompt Modal (For Editing) -->
<div id="prompt-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="prompt-modal-title">Edit Message</h3>
                    <div class="mt-4">
                        <textarea id="prompt-input" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" placeholder="Type your message..."></textarea>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="prompt-confirm-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Save
                </button>
                <button type="button" id="prompt-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// 5. Include the core chat JavaScript logic
echo '<script type="module" src="chat_logic.js?v=' . (time() + 1) . '"></script>';
?>

<!-- 6. Manually close all tags opened by the header/sidebar templates -->
</div> <!-- Closes <div id="app-wrapper"> opened in header_chat.php -->

<!-- Mobile toggle script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const userListContainer = document.getElementById('user-list-container');
        const chatWindowContainer = document.getElementById('chat-window-container');
        const mobilePrompt = document.getElementById('mobile-prompt');

        // Initial state check for mobile
        if (window.innerWidth < 768) {
            userListContainer.classList.remove('hidden');
            chatWindowContainer.classList.add('hidden');
            mobilePrompt.classList.remove('hidden');
        } else {
            userListContainer.classList.remove('hidden');
            chatWindowContainer.classList.add('flex');
            chatWindowContainer.classList.remove('hidden');
            mobilePrompt.classList.add('hidden');
        }
    });
</script>

<!-- Hide Smart Voice UI in MDI shell while chat is active -->
<script>
    (function() {
        if (window.self !== window.top && window.parent) {
            window.parent.postMessage({ type: 'toggleSmartVoice', enabled: false }, '*');
            window.addEventListener('beforeunload', () => {
                window.parent.postMessage({ type: 'toggleSmartVoice', enabled: true }, '*');
            });
        }
    })();
</script>

</body>
</html>