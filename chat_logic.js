// Filename: ec/chat_logic.js
// Client-side logic for the PHP/MySQL Chat System

document.addEventListener('DOMContentLoaded', () => {
    // --- Global Variables ---
    let currentRecipient = null;
    let allUsers = [];
    let pollingInterval = null;
    let lastMessageId = 0;
    let isSending = false;
    let typingTimeout = null;
    let replyToMessageId = null; // Track reply state
    
    // --- DOM Elements ---
    const userListEl = document.getElementById('user-list');
    const userSearchInput = document.getElementById('user-search');
    const messagesArea = document.getElementById('messages-area');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const recipientNameHeader = document.getElementById('recipient-name-header');
    const chatHeader = document.getElementById('chat-header');
    const chatStartMessage = document.getElementById('chat-start-message');
    const chatLoadingSpinner = document.getElementById('chat-loading-spinner');
    const messageInputArea = document.getElementById('message-input-area');
    const chatHeaderStatus = document.getElementById('chat-header-status');
    
    // Reply Elements
    const replyPreviewArea = document.getElementById('reply-preview-area');
    const replyToName = document.getElementById('reply-to-name');
    const replyToText = document.getElementById('reply-to-text');
    const cancelReplyBtn = document.getElementById('cancel-reply');

    // Modal Elements
    const confirmationModal = document.getElementById('confirmation-modal');
    const confirmationConfirmBtn = document.getElementById('confirmation-confirm-btn');
    const confirmationCancelBtn = document.getElementById('confirmation-cancel-btn');
    
    const promptModal = document.getElementById('prompt-modal');
    const promptInput = document.getElementById('prompt-input');
    const promptConfirmBtn = document.getElementById('prompt-confirm-btn');
    const promptCancelBtn = document.getElementById('prompt-cancel-btn');

    // File Preview Modal Elements
    const fileModal = document.getElementById('file-modal');
    const fileModalIframe = document.getElementById('file-modal-iframe');
    const fileModalImageContainer = document.getElementById('file-modal-image-container');
    const fileModalImage = document.getElementById('file-modal-image');
    const fileDownloadBtn = document.getElementById('file-download-btn');
    
    let messageToDeleteId = null;
    let messageToEditId = null;

    // File Upload Elements
    const attachFileBtn = document.getElementById('attach-file-btn');
    const fileInput = document.getElementById('file-input');
    const filePreviewArea = document.getElementById('file-preview-area');
    const fileNameDisplay = document.getElementById('file-name-display');
    const cancelUploadBtn = document.getElementById('cancel-upload');
    const uploadProgress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    let selectedFiles = [];

    // Mobile Elements
    const userListContainer = document.getElementById('user-list-container');
    const chatWindowContainer = document.getElementById('chat-window-container');
    const mobilePrompt = document.getElementById('mobile-prompt');
    const backToUsersBtn = document.getElementById('back-to-users-btn');
    const dragDropOverlay = document.getElementById('drag-drop-overlay');

    // --- Modal Logic ---

    function openFileModal(url, type, filename) {
        if (!fileModal) return;
        
        fileDownloadBtn.href = url;
        fileDownloadBtn.download = filename || 'download';

        if (type === 'photo') {
            fileModalImage.src = url;
            fileModalImageContainer.classList.remove('hidden');
            fileModalIframe.classList.add('hidden');
        } else {
            // For PDFs and other docs, use iframe
            // Note: Some browsers might download instead of display if not supported in iframe
            fileModalIframe.src = url;
            fileModalIframe.classList.remove('hidden');
            fileModalImageContainer.classList.add('hidden');
        }
        
        fileModal.classList.remove('hidden');
    }

    function closeFileModal() {
        if (!fileModal) return;
        fileModal.classList.add('hidden');
        fileModalIframe.src = '';
        fileModalImage.src = '';
    }
    
    // Expose closeFileModal globally for the onclick in HTML
    window.closeFileModal = closeFileModal;
    // Expose openFileModal globally for the onclick in HTML
    window.openFileModal = openFileModal;

    function openDeleteModal(msgId) {
        messageToDeleteId = msgId;
        confirmationModal.classList.remove('hidden');
    }

    function closeDeleteModal() {
        messageToDeleteId = null;
        confirmationModal.classList.add('hidden');
    }

    async function confirmDelete() {
        if (!messageToDeleteId) return;
        
        try {
            const res = await fetch('api/delete_chat_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ message_id: messageToDeleteId })
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                // Update UI locally
                const msgEl = document.getElementById(`msg-${messageToDeleteId}`);
                if (msgEl) {
                    const contentBox = msgEl.querySelector('.message-content-box');
                    if (contentBox) {
                        contentBox.innerHTML = `<p class="text-sm text-gray-500 italic"><i class="fas fa-ban mr-1"></i> This message was deleted</p>`;
                        contentBox.classList.remove('bg-indigo-600', 'text-white', 'bg-white', 'text-gray-800');
                        contentBox.classList.add('bg-gray-100', 'border', 'border-gray-200');
                    }
                    // Remove actions
                    const actions = msgEl.querySelector('.message-actions');
                    if (actions) actions.remove();
                }
            } else {
                alert('Failed to delete: ' + data.message);
            }
        } catch (e) {
            console.error(e);
        } finally {
            closeDeleteModal();
        }
    }

    function openEditModal(msgId, currentText) {
        messageToEditId = msgId;
        promptInput.value = currentText;
        promptModal.classList.remove('hidden');
        promptInput.focus();
    }

    function closeEditModal() {
        messageToEditId = null;
        promptInput.value = '';
        promptModal.classList.add('hidden');
    }

    async function confirmEdit() {
        if (!messageToEditId) return;
        const newText = promptInput.value.trim();
        if (!newText) return;

        try {
            const res = await fetch('api/edit_chat_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ message_id: messageToEditId, message_text: newText })
            });
            const data = await res.json();

            if (data.status === 'success') {
                // Update UI locally
                const msgEl = document.getElementById(`msg-${messageToEditId}`);
                if (msgEl) {
                    const textEl = msgEl.querySelector('.message-text');
                    if (textEl) {
                        textEl.textContent = newText;
                        // Add edited label if not present
                        const metaEl = msgEl.querySelector('.message-meta');
                        if (metaEl && !metaEl.querySelector('.edited-label')) {
                            const span = document.createElement('span');
                            span.className = 'edited-label text-[10px] text-gray-400 ml-1';
                            span.textContent = '(edited)';
                            metaEl.insertBefore(span, metaEl.firstChild);
                        }
                    }
                }
            } else {
                alert('Failed to edit: ' + data.message);
            }
        } catch (e) {
            console.error(e);
        } finally {
            closeEditModal();
        }
    }

    async function toggleReaction(msgId, emoji) {
        try {
            const res = await fetch('api/toggle_reaction.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ message_id: msgId, emoji: emoji })
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                // Pass my ID and Name for immediate update
                updateReactionUI(msgId, emoji, data.action, window.PHP_USER_ID, window.PHP_USER_FULL_NAME);
            } else {
                console.error("Reaction failed:", data.message);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function updateReactionUI(msgId, emoji, action, userId, userName) {
        const msgEl = document.getElementById(`msg-${msgId}`);
        if (!msgEl) return;

        // Structure: msgEl > div.flex > [avatar, contentWrapper]
        const flexContainer = msgEl.firstElementChild;
        if (!flexContainer) return;
        
        const contentWrapper = flexContainer.children[1]; 
        if (!contentWrapper) return;

        let reactionsContainer = msgEl.querySelector('.reactions-container');
        
        if (!reactionsContainer) {
            if (action === 'removed') return; // Nothing to remove
            
            reactionsContainer = document.createElement('div');
            reactionsContainer.className = 'reactions-container flex flex-wrap mt-1 justify-end space-x-1';
            
            // Insert after message-content-box
            const contentBox = msgEl.querySelector('.message-content-box');
            if (contentBox) {
                contentBox.parentNode.insertBefore(reactionsContainer, contentBox.nextSibling);
            }
        }

        // Find specific bubble for this user and emoji
        const bubbles = Array.from(reactionsContainer.children);
        const targetBubble = bubbles.find(el => 
            el.textContent.trim() === emoji && el.dataset.userId == userId
        );

        if (action === 'added') {
            if (!targetBubble) {
                const bubble = document.createElement('span');
                const isMe = userId == window.PHP_USER_ID;
                bubble.className = `reaction-bubble ${isMe ? 'active' : ''}`;
                bubble.textContent = emoji;
                bubble.title = userName || 'User';
                bubble.dataset.userId = userId;
                
                // Only bind click if it's me (to toggle off) or if we want to allow toggling others? 
                // Usually you can only toggle YOUR own.
                // But clicking someone else's reaction usually adds YOUR reaction of same type.
                // For simplicity, let's just bind the toggle.
                bubble.onclick = (e) => {
                     e.stopPropagation();
                     toggleReaction(msgId, emoji);
                };
                reactionsContainer.appendChild(bubble);
            }
        } else if (action === 'removed') {
            if (targetBubble) {
                targetBubble.remove();
            }
            // If empty, remove container
            if (reactionsContainer.children.length === 0) {
                reactionsContainer.remove();
            }
        }
    }

    // --- Utility Functions ---

    // Expose toggleReaction globally so onclick attributes work
    window.toggleReaction = toggleReaction;

    function formatMessageTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function buildProfilePicUrl(picUrl) {
        if (!picUrl) return null;
        if (picUrl.startsWith('http') || picUrl.startsWith('uploads/')) return picUrl;
        return `uploads/profile_pictures/${picUrl}`;
    }

    function renderProfilePic(picUrl, cssClass = 'w-10 h-10') {
        const fullPicUrl = buildProfilePicUrl(picUrl);
        if (fullPicUrl) {
            return `<img src="${fullPicUrl}" alt="Profile" class="${cssClass} rounded-full object-cover">`;
        }
        return `
            <span class="${cssClass} rounded-full bg-indigo-500 flex items-center justify-center text-white font-semibold text-xl">
                <i class="fas fa-user-alt"></i>
            </span>
        `;
    }

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    // --- Core Functions ---

    async function fetchUserList() {
        try {
            const response = await fetch('api/get_user_list_for_chat.php');
            const data = await response.json();

            if (data.status === 'success') {
                allUsers = data.users;
                renderUserList(allUsers);
                fetchUnreadCounts(); // Initial fetch
            } else {
                userListEl.innerHTML = `<div class="p-4 text-center text-red-500">Error: ${data.message}</div>`;
            }
        } catch (error) {
            console.error("Error fetching users:", error);
            userListEl.innerHTML = '<div class="p-4 text-center text-red-500">Could not load users.</div>';
        }
    }

    function renderUserList(users) {
        userListEl.innerHTML = '';
        if (users.length === 0) {
            userListEl.innerHTML = '<div class="p-4 text-center text-gray-500">No users found.</div>';
            return;
        }

        users.forEach(user => {
            const userEl = document.createElement('div');
            // Check if active
            const isActive = currentRecipient && currentRecipient.user_id == user.user_id;
            const activeClass = isActive ? 'bg-indigo-200' : '';

            userEl.className = `relative flex items-center p-4 cursor-pointer hover:bg-indigo-100 transition duration-150 border-b border-gray-100 ${activeClass}`;
            userEl.setAttribute('data-user-id', user.user_id);
            
            // Online Status Logic
            let statusColor = 'bg-gray-400';
            let statusTitle = 'Offline';
            
            if (user.last_active_at) {
                // Parse MySQL Date: YYYY-MM-DD HH:MM:SS
                const t = user.last_active_at.split(/[- :]/);
                const lastActive = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
                const now = new Date();
                const diffMinutes = (now - lastActive) / 1000 / 60;
                
                if (diffMinutes < 5) {
                    statusColor = 'bg-green-500';
                    statusTitle = 'Online';
                } else {
                    statusTitle = 'Last seen: ' + formatMessageTime(user.last_active_at);
                }
            }

            userEl.innerHTML = `
                <div class="relative">
                    ${renderProfilePic(user.profile_image_url, 'w-12 h-12')}
                    <span id="status-dot-${user.user_id}" class="absolute bottom-0 right-0 w-3 h-3 ${statusColor} border-2 border-white rounded-full" title="${statusTitle}"></span>
                </div>
                <div class="ml-3 flex-1 min-w-0">
                    <h3 class="text-md font-semibold text-gray-800 truncate">${user.full_name}</h3>
                </div>
                <div class="ml-2" id="unread-badge-${user.user_id}"></div>
            `;

            userEl.addEventListener('click', () => {
                // Highlight selection
                document.querySelectorAll('#user-list > div').forEach(el => el.classList.remove('bg-indigo-200'));
                userEl.classList.add('bg-indigo-200');
                
                startChat(user);

                // Mobile View Logic
                if (window.innerWidth < 768) {
                    userListContainer.classList.add('hidden');
                    mobilePrompt.classList.add('hidden');
                    chatWindowContainer.classList.remove('hidden');
                    chatWindowContainer.classList.add('flex');
                }
            });

            userListEl.appendChild(userEl);
        });
    }

    async function fetchUnreadCounts() {
        try {
            const response = await fetch('api/get_unread_counts.php');
            const data = await response.json();
            if (data.status === 'success') {
                updateUnreadBadges(data.counts);
            }
        } catch (e) {
            console.error("Error fetching unread counts:", e);
        }
    }

    function updateUnreadBadges(counts) {
        // Clear all badges first
        document.querySelectorAll('[id^="unread-badge-"]').forEach(el => el.innerHTML = '');
        
        for (const [userId, count] of Object.entries(counts)) {
            if (count > 0) {
                const badgeEl = document.getElementById(`unread-badge-${userId}`);
                if (badgeEl) {
                    badgeEl.innerHTML = `<span class="unread-badge">${count}</span>`;
                }
            }
        }
    }

    // --- Chat Logic ---

    function startChat(user) {
        if (currentRecipient && currentRecipient.user_id === user.user_id) return;

        currentRecipient = user;
        lastMessageId = 0;
        
        // Update Header
        recipientNameHeader.textContent = user.full_name;
        chatHeaderStatus.textContent = ''; // Clear status
        chatHeaderStatus.classList.remove('hidden');

        // Show Chat UI
        chatStartMessage.classList.add('hidden');
        chatHeader.classList.remove('hidden');
        messageInputArea.classList.remove('hidden');
        messagesArea.innerHTML = ''; // Clear old messages
        chatLoadingSpinner.classList.remove('hidden');
        
        // Enable Inputs
        messageInput.disabled = false;
        sendBtn.disabled = false;

        // Stop previous polling
        if (pollingInterval) clearInterval(pollingInterval);

        // Load Messages
        loadMessages(true);

        // Start Polling (every 3 seconds)
        pollingInterval = setInterval(() => {
            loadMessages(false);
            fetchUnreadCounts(); // Also update sidebar badges
        }, 3000);
    }

    async function loadMessages(isInitialLoad = false) {
        if (!currentRecipient) return;

        try {
            let url = `api/get_chat_messages.php?recipient_id=${currentRecipient.user_id}`;
            if (!isInitialLoad && lastMessageId > 0) {
                url += `&after_id=${lastMessageId}`;
            }

            const response = await fetch(url);
            const data = await response.json();

            if (data.status === 'success') {
                if (isInitialLoad) {
                    chatLoadingSpinner.classList.add('hidden');
                    messagesArea.innerHTML = '';
                }

                if (data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        renderMessage(msg);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.message_id));
                    });
                    scrollToBottom();
                    
                    // Mark as read if we just loaded them
                    markMessagesAsRead(currentRecipient.user_id);
                }

                // Update Typing Status
                if (data.partner_typing) {
                    console.log('Partner is typing...');
                    chatHeaderStatus.innerHTML = `
                        <div class="flex items-center text-indigo-600 font-semibold text-xs">
                            <span class="mr-1">Typing</span>
                            <span class="animate-pulse">...</span>
                        </div>`;
                    chatHeaderStatus.classList.remove('hidden');
                } else {
                    chatHeaderStatus.innerHTML = '';
                    chatHeaderStatus.classList.add('hidden');
                }

                // Update Read Status (Live)
                if (data.last_read_message_id) {
                    updateReadStatus(data.last_read_message_id);
                }

                // Update Recent Reactions (Live)
                if (data.recent_reactions && data.recent_reactions.length > 0) {
                    data.recent_reactions.forEach(r => {
                        updateReactionUI(r.message_id, r.emoji, r.action, r.user_id, r.user_name);
                    });
                }

            }
        } catch (error) {
            console.error("Error loading messages:", error);
            if (isInitialLoad) {
                chatLoadingSpinner.classList.add('hidden');
                messagesArea.innerHTML = '<div class="text-center text-red-500 mt-4">Failed to load messages.</div>';
            }
        }
    }

    function updateReadStatus(lastReadId) {
        // Find all messages sent by ME that are <= lastReadId and not yet marked read
        const myMessages = document.querySelectorAll('.flex.justify-end'); // Simple selector for my messages
        myMessages.forEach(wrapper => {
            const idParts = wrapper.id.split('-');
            if (idParts.length === 2) {
                const msgId = parseInt(idParts[1]);
                if (msgId <= lastReadId) {
                    const checkIcon = wrapper.querySelector('.fa-check-double');
                    if (checkIcon) {
                        const parentSpan = checkIcon.parentElement;
                        if (parentSpan.classList.contains('text-gray-400')) {
                            parentSpan.classList.remove('text-gray-400');
                            parentSpan.classList.add('text-blue-500');
                            parentSpan.title = 'Read';
                        }
                    }
                }
            }
        });
    }

    function renderMessage(message) {
        // Prevent duplicates
        if (document.getElementById(`msg-${message.message_id}`)) {
            // If message exists, check if we need to update it (e.g. reactions, deleted status, edited status)
            // For now, we skip. In a real app, we'd diff and update.
            // Actually, let's handle "Deleted" update if it changed.
            const existing = document.getElementById(`msg-${message.message_id}`);
            if (message.is_deleted == 1 && !existing.dataset.deleted) {
                existing.innerHTML = ''; // Clear and re-render? 
                // Easier to just remove and let it re-render below? No, ID check prevents it.
                existing.remove(); // Remove old one, let it re-render
            } else {
                return;
            }
        }

        const isSender = message.sender_id == window.PHP_USER_ID;
        const isDeleted = message.is_deleted == 1;
        
        const messageWrapper = document.createElement('div');
        messageWrapper.id = `msg-${message.message_id}`;
        if (isDeleted) messageWrapper.dataset.deleted = "true";
        
        messageWrapper.className = `flex ${isSender ? 'justify-end' : 'justify-start'} mb-4 group relative`; 

        const picUrl = isSender ? window.PHP_USER_PROFILE_PIC : currentRecipient.profile_image_url;
        
        let contentHtml = '';

        if (isDeleted) {
            contentHtml = `<p class="text-sm text-gray-500 italic"><i class="fas fa-ban mr-1"></i> This message was deleted</p>`;
        } else {
            // Handle Quoted/Reply Content
            if (message.quoted_text || message.quoted_file_name) {
                const quotedContent = message.quoted_text || (message.quoted_file_name ? `[File] ${message.quoted_file_name}` : 'Attachment');
                contentHtml += `
                    <div class="mb-2 p-2 rounded bg-opacity-20 ${isSender ? 'bg-black' : 'bg-indigo-100'} border-l-4 ${isSender ? 'border-white' : 'border-indigo-500'} text-xs cursor-pointer opacity-80">
                        <div class="font-bold mb-1">${message.quoted_sender_name || 'User'}</div>
                        <div class="truncate">${quotedContent}</div>
                    </div>
                `;
            }
            
            // Handle Text
            if (message.message_text) {
                contentHtml += `<p class="text-sm whitespace-pre-wrap message-text">${message.message_text}</p>`;
            }

            // Handle File/Photo
            if (message.message_type === 'photo' && message.file_url) {
                contentHtml += `
                    <div onclick="openFileModal('${message.file_url}', 'photo', '${message.file_name || 'image.jpg'}')" class="block mt-2 cursor-pointer">
                        <img src="${message.file_url}" class="max-w-xs rounded-lg shadow-sm border hover:opacity-90 transition">
                    </div>`;
            } else if (message.message_type === 'file' && message.file_url) {
                contentHtml += `
                    <div onclick="openFileModal('${message.file_url}', 'file', '${message.file_name || 'document'}')" class="flex items-center p-2 mt-2 bg-gray-50 rounded border hover:bg-gray-100 transition cursor-pointer">
                        <i class="fas fa-file-alt text-indigo-500 mr-2"></i>
                        <span class="text-sm text-indigo-700 underline truncate max-w-[200px]">${message.file_name || 'Attachment'}</span>
                    </div>`;
            }
        }

        // Read Status & Edited Status
        let statusHtml = '';
        let editedHtml = '';
        
        if (!isDeleted && message.edited_at) {
            editedHtml = `<span class="edited-label text-[10px] text-gray-400 ml-1">(edited)</span>`;
        }

        if (isSender && !isDeleted) {
            const isRead = message.read_at !== null;
            const checkColor = isRead ? 'text-blue-500' : 'text-gray-400';
            statusHtml = `
                <span class="ml-1 ${checkColor}" title="${isRead ? 'Read: ' + message.read_at : 'Sent'}">
                    <i class="fas fa-check-double text-[10px]"></i>
                </span>
            `;
        }

        // Reactions Display
        let reactionsHtml = '';
        // Always create the container, even if empty, to make updates easier? 
        // Or just create it if needed. Let's stick to creating if needed in render, but handle in update.
        if (!isDeleted && message.reactions && message.reactions.length > 0) {
            reactionsHtml = `<div class="reactions-container flex flex-wrap mt-1 justify-end space-x-1">`;
            message.reactions.forEach(r => {
                const isMe = r.user_id == window.PHP_USER_ID;
                reactionsHtml += `
                    <span class="reaction-bubble ${isMe ? 'active' : ''}" 
                          data-user-id="${r.user_id}" 
                          onclick="toggleReaction(${message.message_id}, '${r.emoji}')" 
                          title="${r.user_name}">
                        ${r.emoji}
                    </span>
                `;
            });
            reactionsHtml += `</div>`;
        } else if (!isDeleted) {
             // Placeholder for easier DOM manipulation? No, we'll create it dynamically.
        }

        // Action Buttons (Reply, React, Edit/Delete)
        let actionButtonsHtml = '';
        if (!isDeleted) {
            actionButtonsHtml = `
                <div class="message-actions flex items-center opacity-0 group-hover:opacity-100 transition-opacity absolute ${isSender ? 'right-full mr-2' : 'left-full ml-2'} top-0 h-full">
                    <button class="reply-btn p-1 text-gray-400 hover:text-indigo-600" title="Reply">
                        <i class="fas fa-reply"></i>
                    </button>
                    
                    <div class="relative reaction-menu-container">
                        <button class="reaction-menu-btn p-1 text-gray-400 hover:text-yellow-500" title="React">
                            <i class="far fa-smile"></i>
                        </button>
                        <div class="reaction-picker hidden absolute bottom-full mb-1 bg-white shadow-lg rounded-full p-1 border z-10">
                            <span class="reaction-option" data-emoji="👍">👍</span>
                            <span class="reaction-option" data-emoji="❤️">❤️</span>
                            <span class="reaction-option" data-emoji="😂">😂</span>
                            <span class="reaction-option" data-emoji="😮">😮</span>
                            <span class="reaction-option" data-emoji="😢">😢</span>
                            <span class="reaction-option" data-emoji="🙏">🙏</span>
                        </div>
                    </div>

                    ${isSender ? `
                    <div class="relative action-menu-container">
                        <button class="action-menu-btn p-1 text-gray-400 hover:text-gray-600" title="More">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="action-menu-dropdown hidden absolute bottom-full left-0 mb-1 w-24 bg-white shadow-lg rounded border z-20 overflow-hidden">
                            <button class="edit-btn block w-full text-left px-3 py-2 text-xs hover:bg-gray-100 text-gray-700">Edit</button>
                            <button class="delete-btn block w-full text-left px-3 py-2 text-xs hover:bg-gray-100 text-red-600">Delete</button>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
        }

        // Bubble Styling
        const bubbleClass = isDeleted 
            ? 'bg-gray-100 border border-gray-200 text-gray-800 rounded-bl-none' 
            : (isSender ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-white text-gray-800 border border-gray-200 rounded-bl-none');

        messageWrapper.innerHTML = `
            <div class="flex items-end max-w-[80%] ${isSender ? 'flex-row-reverse' : 'flex-row'} relative">
                <div class="flex-shrink-0">
                    ${renderProfilePic(picUrl, 'w-8 h-8')}
                </div>
                
                <div class="flex flex-col ${isSender ? 'items-end' : 'items-start'} mx-2 relative">
                    <div class="message-content-box px-4 py-2 rounded-2xl shadow-sm ${bubbleClass}">
                        ${contentHtml}
                    </div>
                    ${reactionsHtml}
                    <div class="flex items-center mt-1 space-x-1 message-meta">
                        <span class="text-xs text-gray-400">${formatMessageTime(message.sent_at)}</span>
                        ${editedHtml}
                        ${statusHtml}
                    </div>
                    
                    ${actionButtonsHtml}
                </div>
            </div>
        `;

        // Bind Events
        if (!isDeleted) {
            const replyBtn = messageWrapper.querySelector('.reply-btn');
            if (replyBtn) replyBtn.addEventListener('click', () => enableReply(message));

            // Reaction Menu Toggle
            const reactBtn = messageWrapper.querySelector('.reaction-menu-btn');
            const reactPicker = messageWrapper.querySelector('.reaction-picker');
            
            if (reactBtn && reactPicker) {
                reactBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Close other pickers
                    document.querySelectorAll('.reaction-picker').forEach(el => {
                        if (el !== reactPicker) el.classList.add('hidden');
                    });
                    // Close action menus
                    document.querySelectorAll('.action-menu-dropdown').forEach(el => el.classList.add('hidden'));
                    
                    reactPicker.classList.toggle('hidden');
                    reactPicker.classList.toggle('flex'); // Ensure flex display when shown
                });
            }

            const reactionOptions = messageWrapper.querySelectorAll('.reaction-option');
            reactionOptions.forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleReaction(message.message_id, opt.dataset.emoji);
                    // Close picker after selection
                    if (reactPicker) {
                        reactPicker.classList.add('hidden');
                        reactPicker.classList.remove('flex');
                    }
                });
            });

            if (isSender) {
                const menuBtn = messageWrapper.querySelector('.action-menu-btn');
                const menuDropdown = messageWrapper.querySelector('.action-menu-dropdown');
                
                if (menuBtn && menuDropdown) {
                    menuBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        document.querySelectorAll('.action-menu-dropdown').forEach(el => {
                            if (el !== menuDropdown) el.classList.add('hidden');
                        });
                        menuDropdown.classList.toggle('hidden');
                    });
                }

                const editBtn = messageWrapper.querySelector('.edit-btn');
                if (editBtn) editBtn.addEventListener('click', () => openEditModal(message.message_id, message.message_text));

                const deleteBtn = messageWrapper.querySelector('.delete-btn');
                if (deleteBtn) deleteBtn.addEventListener('click', () => openDeleteModal(message.message_id));
            }
        }

        messagesArea.appendChild(messageWrapper);
    }

    function enableReply(message) {
        replyToMessageId = message.message_id;
        replyToName.textContent = message.sender_name || 'User';
        
        let previewText = message.message_text || '';
        if (message.message_type === 'photo') previewText = '[Photo]';
        else if (message.message_type === 'file') previewText = `[File] ${message.file_name}`;
        
        replyToText.textContent = previewText;
        replyPreviewArea.classList.remove('hidden');
        messageInput.focus();
    }

    function cancelReply() {
        replyToMessageId = null;
        replyPreviewArea.classList.add('hidden');
        replyToName.textContent = '';
        replyToText.textContent = '';
    }

    async function sendMessage() {
        const text = messageInput.value.trim();
        if (!text && selectedFiles.length === 0) return;
        if (!currentRecipient) return;

        if (isSending) return;
        isSending = true;
        sendBtn.disabled = true;

        try {
            // 1. Send Text Message if exists
            if (text) {
                const msgData = new FormData();
                msgData.append('recipient_id', currentRecipient.user_id);
                msgData.append('message_text', text);
                msgData.append('message_type', 'text');
                if (replyToMessageId) msgData.append('reply_to_id', replyToMessageId);

                const res = await fetch('api/send_chat_message.php', { method: 'POST', body: msgData });
                const result = await res.json();
                if (result.status !== 'success') throw new Error(result.message);
            }

            // 2. Send Files
            if (selectedFiles.length > 0) {
                uploadProgress.classList.remove('hidden');
                const totalFiles = selectedFiles.length;
                
                for (let i = 0; i < totalFiles; i++) {
                    const file = selectedFiles[i];
                    const progress = ((i + 1) / totalFiles) * 100;
                    progressBar.style.width = `${progress}%`;

                    // Upload
                    const uploadData = new FormData();
                    uploadData.append('chat_file', file);
                    uploadData.append('recipient_id', currentRecipient.user_id);

                    const uploadRes = await fetch('api/upload_chat_file.php', {
                        method: 'POST',
                        body: uploadData
                    });
                    const uploadResult = await uploadRes.json();

                    if (uploadResult.status === 'success') {
                        // Send Message for File
                        const msgData = new FormData();
                        msgData.append('recipient_id', currentRecipient.user_id);
                        msgData.append('message_type', uploadResult.mime_type.startsWith('image/') ? 'photo' : 'file');
                        msgData.append('file_url', uploadResult.file_url);
                        msgData.append('file_name', uploadResult.original_filename);
                        
                        // Attach reply ID only to the first message if no text was sent
                        if (!text && i === 0 && replyToMessageId) {
                            msgData.append('reply_to_id', replyToMessageId);
                        }

                        await fetch('api/send_chat_message.php', { method: 'POST', body: msgData });
                    } else {
                        console.error("Failed to upload file:", file.name, uploadResult.message);
                    }
                }
            }

            // Success
            messageInput.value = '';
            resetFileInput();
            cancelReply();
            loadMessages(false);

        } catch (error) {
            console.error("Send error:", error);
            alert("Failed to send message(s).");
        } finally {
            isSending = false;
            sendBtn.disabled = false;
            setTimeout(() => {
                uploadProgress.classList.add('hidden');
                progressBar.style.width = '0%';
            }, 1000);
        }
    }

    async function markMessagesAsRead(senderId) {
        try {
            await fetch('api/mark_messages_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ sender_id: senderId })
            });
            // Update badges locally
            const badge = document.getElementById(`unread-badge-${senderId}`);
            if (badge) badge.innerHTML = '';
        } catch (e) {
            console.error("Error marking read:", e);
        }
    }

    function resetFileInput() {
        selectedFiles = [];
        fileInput.value = '';
        filePreviewArea.classList.add('hidden');
        
        // Reset UI
        const listContainer = document.getElementById('file-list-container');
        if (listContainer) listContainer.innerHTML = '';
        
        // Hide progress
        uploadProgress.classList.add('hidden');
        progressBar.style.width = '0%';
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        renderFilePreview();
        if (selectedFiles.length === 0) {
            resetFileInput();
        }
    }

    function renderFilePreview() {
        if (selectedFiles.length === 0) {
            filePreviewArea.classList.add('hidden');
            return;
        }

        filePreviewArea.classList.remove('hidden');
        
        let listContainer = document.getElementById('file-list-container');
        if (!listContainer) {
            listContainer = document.createElement('div');
            listContainer.id = 'file-list-container';
            listContainer.className = 'flex flex-col space-y-1 mb-2';
            filePreviewArea.insertBefore(listContainer, uploadProgress);
            
            // Hide the old single file elements
            if(fileNameDisplay) fileNameDisplay.style.display = 'none';
            if(cancelUploadBtn) cancelUploadBtn.style.display = 'none';
        }
        
        listContainer.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'flex justify-between items-center bg-white p-1 rounded border border-indigo-100';
            row.innerHTML = `
                <span class="text-xs text-gray-700 truncate max-w-[200px]">${file.name}</span>
                <button class="text-red-500 hover:text-red-700 ml-2 remove-file-btn" data-index="${index}">
                    <i class="fas fa-times"></i>
                </button>
            `;
            row.querySelector('.remove-file-btn').addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent bubbling
                removeFile(index);
            });
            listContainer.appendChild(row);
        });
    }

    // --- Event Listeners ---

    cancelReplyBtn.addEventListener('click', cancelReply);

    confirmationConfirmBtn.addEventListener('click', confirmDelete);
    confirmationCancelBtn.addEventListener('click', closeDeleteModal);
    
    promptConfirmBtn.addEventListener('click', confirmEdit);
    promptCancelBtn.addEventListener('click', closeEditModal);

    sendBtn.addEventListener('click', sendMessage);

    let lastTypingTime = 0;

    messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
            return;
        }
        
        // Typing Indicator
        const now = Date.now();
        
        // Send typing signal if not sent recently (every 2.5s) to keep backend status alive
        if (now - lastTypingTime > 2500 && currentRecipient) {
             fetch('api/update_typing_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ recipient_id: currentRecipient.user_id, is_typing: 1 })
            });
            lastTypingTime = now;
        }
        
        clearTimeout(typingTimeout);
        
        typingTimeout = setTimeout(() => {
             if (currentRecipient) {
                fetch('api/update_typing_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ recipient_id: currentRecipient.user_id, is_typing: 0 })
                });
                lastTypingTime = 0; // Reset so next keypress sends immediately
             }
        }, 3000);
    });

    attachFileBtn.addEventListener('click', () => {
        // Use setTimeout to ensure mobile browsers handle the click event correctly
        setTimeout(() => {
            fileInput.click();
        }, 50);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelection(e.target.files);
        }
    });

    function handleFileSelection(files) {
        if (!files || files.length === 0) return;
        
        // Convert FileList to Array and append
        Array.from(files).forEach(file => {
            // Check for duplicates by name and size
            if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
            }
        });
        
        renderFilePreview();
    }

    // --- Drag and Drop Logic ---
    
    // Prevent default drag behaviors on window
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Show overlay when dragging over chat window
    chatWindowContainer.addEventListener('dragenter', (e) => {
        if (currentRecipient) { // Only if chat is open
            dragDropOverlay.classList.remove('hidden');
        }
    });

    // Hide overlay when leaving the overlay (cancelling drag)
    dragDropOverlay.addEventListener('dragleave', (e) => {
        dragDropOverlay.classList.add('hidden');
    });

    // Handle Drop
    dragDropOverlay.addEventListener('drop', (e) => {
        dragDropOverlay.classList.add('hidden');
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            handleFileSelection(files);
        }
    });

    cancelUploadBtn.addEventListener('click', resetFileInput);

    userSearchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = allUsers.filter(u => u.full_name.toLowerCase().includes(term));
        renderUserList(filtered);
    });

    backToUsersBtn.addEventListener('click', () => {
        if (window.innerWidth < 768) {
            userListContainer.classList.remove('hidden');
            chatWindowContainer.classList.add('hidden');
            mobilePrompt.classList.remove('hidden');
            currentRecipient = null;
            if (pollingInterval) clearInterval(pollingInterval);
        }
    });

    // --- Init ---
    
    // Close menus when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.action-menu-container')) {
            document.querySelectorAll('.action-menu-dropdown').forEach(el => el.classList.add('hidden'));
        }
        if (!e.target.closest('.reaction-menu-container')) {
            document.querySelectorAll('.reaction-picker').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('flex');
            });
        }
    });

    fetchUserList();
    
    // Poll user list every 30 seconds to update online status
    setInterval(fetchUserList, 30000);
});
