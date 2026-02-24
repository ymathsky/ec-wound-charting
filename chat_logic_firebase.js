// Filename: ec/chat_logic.js
// This file handles all client-side logic for the real-time chat.

// Wait for Firebase Auth to be ready before doing anything
window.authReadyPromise.then(() => {
    // --- Global Variables & DOM Elements ---
    const db = window.db;
    const auth = window.auth;
    // --- UPDATED: Added 'getDocs' and 'updateDoc' ---
    const { getDoc, setDoc, onSnapshot, collection, query, orderBy, where, doc, updateDoc, getDocs } = window.FS_API;

    // --- Global State ---
    let currentRecipient = null;
    let allUsers = []; // Cache for user search
    let unsubscribeMessages = null; // Function to stop listening to a specific chat
    let unsubscribeUnread = null; // Function to stop listening for all unread messages
    let unreadCounts = {}; // Object to store unread counts, e.g., { "user_id": 3 }

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

    // File Upload Elements
    const attachFileBtn = document.getElementById('attach-file-btn');
    const fileInput = document.getElementById('file-input');
    const filePreviewArea = document.getElementById('file-preview-area');
    const fileNameDisplay = document.getElementById('file-name-display');
    const cancelUploadBtn = document.getElementById('cancel-upload');
    const uploadProgress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    let selectedFile = null;

    // Mobile Elements
    const userListContainer = document.getElementById('user-list-container');
    const chatWindowContainer = document.getElementById('chat-window-container');
    const mobilePrompt = document.getElementById('mobile-prompt');
    const backToUsersBtn = document.getElementById('back-to-users-btn');

    // --- Utility Functions ---

    /**
     * Formats a Firestore timestamp into a human-readable time (e.g., "10:30 AM")
     */
    function formatMessageTime(firestoreTimestamp) {
        if (!firestoreTimestamp) return '';
        const date = firestoreTimestamp.toDate();
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    /**
     * Creates a canonical chat room ID from two user IDs.
     */
    function getChatRoomId(userId1, userId2) {
        const id1 = parseInt(userId1, 10);
        const id2 = parseInt(userId2, 10);
        return id1 < id2 ? `${id1}-${id2}` : `${id2}-${id1}`;
    }

    /**
     * Builds the full URL for a profile picture.
     */
    function buildProfilePicUrl(picUrl) {
        if (!picUrl) {
            return null;
        }
        // Check if it's already a full path (which it shouldn't be, but good to check)
        if (picUrl.startsWith('http') || picUrl.startsWith('uploads/')) {
            return picUrl;
        }
        // Build the correct relative path from the root
        return `uploads/profile_pictures/${picUrl}`;
    }

    /**
     * Renders either a user's profile picture or a placeholder icon.
     */
    function renderProfilePic(picUrl, cssClass = 'w-10 h-10') {
        const fullPicUrl = buildProfilePicUrl(picUrl);
        if (fullPicUrl) {
            return `<img src="${fullPicUrl}" alt="Profile" class="${cssClass} rounded-full object-cover">`;
        }
        // Default placeholder
        return `
            <span class="${cssClass} rounded-full bg-indigo-500 flex items-center justify-center text-white font-semibold text-xl">
                <i class="fas fa-user-alt"></i>
            </span>
        `;
    }

    // --- Core Functions ---

    /**
     * Fetches the user list from the PHP API and renders it.
     */
    async function fetchUserList() {
        try {
            const response = await fetch('api/get_user_list_for_chat.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();

            if (data.status === 'success' && data.users.length > 0) {
                allUsers = data.users; // Cache for search
                renderUserList(allUsers);
                // --- NEW: Start listening for unread messages AFTER user list is fetched ---
                listenForUnreadMessages();
            } else if (data.users.length === 0) {
                userListEl.innerHTML = '<div class="p-4 text-center text-gray-500">No other users found.</div>';
            } else {
                userListEl.innerHTML = `<div class="p-4 text-center text-red-500">Error: ${data.message}</div>`;
            }
        } catch (error) {
            console.error("Error fetching user list:", error);
            userListEl.innerHTML = '<div class="p-4 text-center text-red-500">Could not load users.</div>';
        }
    }

    /**
     * Renders the user list in the sidebar.
     * --- UPDATED: Now includes unread badge placeholder ---
     */
    function renderUserList(users) {
        userListEl.innerHTML = ''; // Clear "Loading..."
        if (users.length === 0) {
            userListEl.innerHTML = '<div class="p-4 text-center text-gray-500">No users match your search.</div>';
            return;
        }

        users.forEach(user => {
            const userEl = document.createElement('div');
            // --- UPDATED: Added 'relative' and 'items-center' ---
            userEl.className = 'relative flex items-center p-4 cursor-pointer hover:bg-indigo-100 transition duration-150';
            userEl.setAttribute('data-user-id', user.user_id);
            userEl.setAttribute('data-user-name', user.full_name);
            userEl.setAttribute('data-user-pic', user.profile_image_url || '');

            // --- UPDATED: Added placeholder for unread badge ---
            userEl.innerHTML = `
                <div class="relative">
                    ${renderProfilePic(user.profile_image_url, 'w-12 h-12')}
                </div>
                <div class="ml-3 flex-1 min-w-0">
                    <h3 class="text-md font-semibold text-gray-800 truncate">${user.full_name}</h3>
                </div>
                <!-- This div will hold the unread count badge -->
                <div class="ml-2" id="unread-badge-${user.user_id}"></div>
            `;

            // Add click event listener to start chat
            userEl.addEventListener('click', () => {
                currentRecipient = {
                    id: user.user_id.toString(),
                    name: user.full_name,
                    pic_url: user.profile_image_url || ''
                };

                // Highlight the selected user
                document.querySelectorAll('#user-list .bg-indigo-200').forEach(el => el.classList.remove('bg-indigo-200'));
                userEl.classList.add('bg-indigo-200');

                // Start the chat
                startChat(currentRecipient);

                // --- Mobile View ---
                if (window.innerWidth < 768) {
                    userListContainer.classList.add('hidden');
                    mobilePrompt.classList.add('hidden');
                    chatWindowContainer.classList.remove('hidden');
                    chatWindowContainer.classList.add('flex');
                }
            });

            userListEl.appendChild(userEl);
        });

        // --- NEW: Apply existing unread counts to the newly rendered list ---
        updateUnreadBadgesUI();
    }

    /**
     * --- NEW: Global listener for all incoming unread messages ---
     * This uses a collectionGroup query, which is highly efficient.
     */
    function listenForUnreadMessages() {
        if (unsubscribeUnread) {
            unsubscribeUnread(); // Stop previous listener
        }

        const currentUserId = window.PHP_USER_ID.toString();

        try {
            // This query searches ALL 'messages' subcollections across the entire database.
            const messagesGroupRef = collection(db, window.FS_MESSAGES_SUBCOLLECTION);

            const q = query(messagesGroupRef,
                where("recipient_id", "==", currentUserId),
                where("read_at", "==", null)
            );

            unsubscribeUnread = onSnapshot(q, (querySnapshot) => {
                const newCounts = {};

                querySnapshot.forEach((doc) => {
                    const message = doc.data();
                    const senderId = message.sender_id.toString();

                    if (!newCounts[senderId]) {
                        newCounts[senderId] = 0;
                    }
                    newCounts[senderId]++;
                });

                unreadCounts = newCounts;
                updateUnreadBadgesUI();

            }, (error) => {
                console.error("Error listening for unread messages:", error);
                if (error.code === 'failed-precondition') {
                    // This is a common, fixable error.
                    console.warn("Firestore index missing for unread messages query.");
                    console.warn("Please go to the Firebase console and create a new index for the 'messages' collection group with fields: recipient_id (ASC) and read_at (ASC).");
                    // The error message in the console will often include a direct link to create this index.
                }
            });
        } catch (e) {
            console.error("Could not set up unread listener:", e);
        }
    }

    /**
     * --- NEW: Updates the UI with red badges based on the unreadCounts object ---
     */
    function updateUnreadBadgesUI() {
        // First, clear all existing badges on the user list
        userListEl.querySelectorAll('.unread-badge').forEach(badge => badge.remove());

        // Then, add new ones
        for (const userId in unreadCounts) {
            const count = unreadCounts[userId];
            if (count > 0) {
                const placeholder = document.getElementById(`unread-badge-${userId}`);
                if (placeholder) {
                    placeholder.innerHTML = `<span class="unread-badge">${count}</span>`;
                }
            }
        }
    }

    /**
     * --- NEW & FIXED: Marks all messages in a chat as read ---
     * This now uses the 'getDocs' function provided by header_chat.php
     */
    async function markMessagesAsRead(recipientId) {
        if (!recipientId) return;
        const currentUserId = window.PHP_USER_ID.toString();
        const roomId = getChatRoomId(currentUserId, recipientId);
        const messagesColRef = collection(db, window.FS_COLLECTION_ROOT, roomId, window.FS_MESSAGES_SUBCOLLECTION);

        // Find all messages sent TO ME that are unread
        const q = query(messagesColRef,
            where("recipient_id", "==", currentUserId),
            where("read_at", "==", null)
        );

        try {
            // Use getDocs for a one-time read of all unread messages
            const unreadSnapshot = await getDocs(q);

            // Use a batch write for efficiency if there are many unread messages
            // Note: For simplicity, we'll update them one by one.
            // A batched write would be better for performance.
            unreadSnapshot.forEach(async (docToUpdate) => {
                try {
                    // We can't use docToUpdate.ref here as it's from a collectionGroup
                    // We must build the ref manually.
                    const docRef = doc(db, window.FS_COLLECTION_ROOT, roomId, window.FS_MESSAGES_SUBCOLLECTION, docToUpdate.id);
                    await updateDoc(docRef, {
                        read_at: new Date()
                    });
                } catch (e) {
                    console.error("Error updating single doc as read:", e);
                }
            });

        } catch (e) {
            console.error("Failed to query and update unread messages:", e);
        }
    }

    /**
     * Renders a single message in the chat window.
     */
    function renderMessage(message, isSender) {
        const messageWrapper = document.createElement('div');
        messageWrapper.className = `flex ${isSender ? 'justify-end' : 'justify-start'}`;

        const pic = isSender ? window.PHP_USER_PROFILE_PIC : currentRecipient.pic_url;

        let fileContent = '';
        if (message.message_type === 'photo') {
            fileContent = `
                <a href="${message.file_url}" target="_blank" rel="noopener noreferrer" class="mt-2 block">
                    <img src="${message.file_url}" alt="Uploaded photo" class="max-w-xs h-auto rounded-lg shadow-md">
                </a>
            `;
        } else if (message.message_type === 'file') {
            fileContent = `
                <a href="${message.file_url}" target="_blank" rel="noopener noreferrer" class="mt-2 flex items-center p-3 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition">
                    <i class="fas fa-file-alt text-indigo-600 text-2xl mr-3"></i>
                    <span class="text-indigo-800 font-medium truncate">${message.file_name || 'Attached File'}</span>
                </a>
            `;
        }

        const textContent = message.message_text ? `<p class="text-sm">${message.message_text.replace(/\n/g, '<br>')}</p>` : '';

        messageWrapper.innerHTML = `
            <div class="flex items-start max-w-lg ${isSender ? 'flex-row-reverse' : 'flex-row'}">
                ${renderProfilePic(pic, 'w-10 h-10')}
                <div class="mx-3">
                    <div class="p-3 rounded-lg shadow-sm ${isSender ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border'}">
                        ${textContent}
                        ${fileContent}
                    </div>
                    <time class="text-xs text-gray-500 mt-1 px-1 ${isSender ? 'text-right' : 'text-left'} block">
                        ${formatMessageTime(message.sent_at)}
                    </time>
                </div>
            </div>
        `;
        messagesArea.appendChild(messageWrapper);
    }


    /**
     * Initiates a chat with the selected recipient.
     * --- UPDATED: Now marks messages as read ---
     */
    function startChat(recipient) {
        // Stop listening to any previous chat
        if (unsubscribeMessages) {
            unsubscribeMessages();
            unsubscribeMessages = null;
        }

        // --- NEW: Mark messages as read when chat is opened ---
        markMessagesAsRead(recipient.id);

        // 1. Update UI
        chatLoadingSpinner.classList.remove('hidden');
        chatStartMessage.classList.add('hidden');
        messagesArea.innerHTML = ''; // Clear old messages
        recipientNameHeader.textContent = recipient.name;
        chatHeader.classList.remove('hidden');
        messageInputArea.classList.remove('hidden');
        messageInput.disabled = false;
        sendBtn.disabled = false;

        // 2. Get the canonical Room ID
        const roomId = getChatRoomId(window.PHP_USER_ID, recipient.id);
        const roomRef = doc(db, window.FS_COLLECTION_ROOT, roomId);
        const messagesColRef = collection(roomRef, window.FS_MESSAGES_SUBCOLLECTION);

        // 3. Create the chat room document (if it doesn't exist)
        try {
            // --- UPDATED: Add last_updated timestamp and user_ids ---
            const roomData = {
                user_ids: [parseInt(window.PHP_USER_ID, 10), parseInt(recipient.id, 10)],
                last_updated: new Date()
            };
            setDoc(roomRef, roomData, { merge: true });
        } catch(error) {
            console.error("Error creating/updating chat room:", error);
        }


        // 4. Listen for new messages
        const q = query(messagesColRef, orderBy("sent_at", "asc"));

        unsubscribeMessages = onSnapshot(q, (querySnapshot) => {
            messagesArea.innerHTML = ''; // Clear messages on each update

            querySnapshot.forEach((doc) => {
                const message = doc.data();
                const isSender = message.sender_id.toString() === window.PHP_USER_ID.toString();
                renderMessage(message, isSender);

                // --- DEFERRED "MARK AS READ" ---
                // This is a fallback in case the one-time query fails
                // If the message is for me and is unread, mark it.
                if (!isSender && message.read_at === null) {
                    try {
                        updateDoc(doc.ref, { read_at: new Date() });
                    } catch (e) {
                        console.error("Error in snapshot markAsRead:", e);
                    }
                }
            });

            // Scroll to bottom
            messagesArea.scrollTop = messagesArea.scrollHeight;
            chatLoadingSpinner.classList.add('hidden');

        }, (error) => {
            console.error("Error listening to messages:", error);
            messagesArea.innerHTML = '<div class="text-center text-red-500">Error loading messages. Please check permissions.</div>';
            chatLoadingSpinner.classList.add('hidden');
        });
    }


    /**
     * Sends a message (text or file) to Firestore.
     * --- UPDATED: Adds 'read_at: null' and updates room doc ---
     */
    async function sendMessage() {
        const messageText = messageInput.value.trim();

        if (messageText === '' && !selectedFile) {
            return; // Don't send empty messages
        }

        if (!currentRecipient) {
            console.error("No recipient selected.");
            return;
        }

        const roomId = getChatRoomId(window.PHP_USER_ID, currentRecipient.id);
        const roomRef = doc(db, window.FS_COLLECTION_ROOT, roomId); // Reference to the ROOM
        const messagesColRef = collection(roomRef, window.FS_MESSAGES_SUBCOLLECTION); // Reference to MESSAGES

        // Disable input while sending
        messageInput.disabled = true;
        sendBtn.disabled = true;
        attachFileBtn.disabled = true;

        try {
            let fileUrl = null;
            let fileName = null;
            let messageType = 'text';

            // 1. Handle File Upload if one is attached
            if (selectedFile) {
                uploadProgress.classList.remove('hidden');
                progressBar.style.width = '30%'; // Simulate start

                const formData = new FormData();
                formData.append('chat_file', selectedFile);
                formData.append('recipient_id', currentRecipient.id);

                const response = await fetch('api/upload_chat_file.php', {
                    method: 'POST',
                    body: formData
                });

                progressBar.style.width = '100%';

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const uploadData = await response.json();
                if (uploadData.status !== 'success') {
                    throw new Error(uploadData.message || "File upload failed.");
                }

                fileUrl = uploadData.file_url;
                fileName = uploadData.original_filename;
                messageType = uploadData.mime_type.startsWith('image/') ? 'photo' : 'file';

                resetFileInput();
            }

            // 2. Prepare message data for Firestore
            const messageData = {
                sender_id: window.PHP_USER_ID.toString(),
                sender_name: window.PHP_USER_FULL_NAME || 'Me',
                sender_pic_url: window.PHP_USER_PROFILE_PIC || null,
                recipient_id: currentRecipient.id,
                message_text: messageText,
                sent_at: new Date(),
                file_url: fileUrl,
                file_name: fileName,
                message_type: messageType,
                read_at: null // --- NEW: Mark as unread ---
            };

            // 3. Add the document to Firestore
            const newMsgRef = doc(messagesColRef);
            await setDoc(newMsgRef, messageData);

            // --- NEW: 4. Update the parent room doc with last message info ---
            const roomUpdateData = {
                last_updated: new Date(),
                last_message_text: messageType === 'text' ? messageText : (messageType === 'photo' ? 'Photo' : 'File'),
                last_message_sender_id: window.PHP_USER_ID.toString()
            };
            await updateDoc(roomRef, roomUpdateData); // Use updateDoc to avoid overwriting user_ids

            // 5. Clear input and re-enable
            messageInput.value = '';

        } catch (error) {
            console.error("Error sending message:", error);
            alert(`Error: ${error.message}`);
        } finally {
            // Re-enable inputs
            messageInput.disabled = false;
            sendBtn.disabled = false;
            attachFileBtn.disabled = false;
            resetFileInput();
        }
    }


    /**
     * Handles the file input change event.
     */
    function handleFileSelect(e) {
        if (e.target.files.length > 0) {
            selectedFile = e.target.files[0];
            fileNameDisplay.textContent = selectedFile.name;
            filePreviewArea.classList.remove('hidden');
            sendBtn.disabled = false;
            uploadProgress.classList.add('hidden');
            progressBar.style.width = '0%';
        }
    }

    /**
     * Resets the file input and preview area.
     */
    function resetFileInput() {
        selectedFile = null;
        fileInput.value = ''; // Clear the file input
        filePreviewArea.classList.add('hidden');
        fileNameDisplay.textContent = '';
        uploadProgress.classList.add('hidden');
        progressBar.style.width = '0%';
        // Disable send button only if text input is also empty
        if (messageInput.value.trim() === '') {
            sendBtn.disabled = true;
        }
    }

    // --- Event Listeners ---

    // Send message on button click
    sendBtn.addEventListener('click', sendMessage);

    // Send message on "Enter" key
    messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // Prevent new line
            sendMessage();
        }
    });

    // Enable/disable send button based on input
    messageInput.addEventListener('input', () => {
        if (messageInput.value.trim() !== '' || selectedFile) {
            sendBtn.disabled = false;
        } else {
            sendBtn.disabled = true;
        }
    });

    // Trigger file input click
    attachFileBtn.addEventListener('click', () => fileInput.click());

    // Handle file selection
    fileInput.addEventListener('change', handleFileSelect);

    // Cancel file upload
    cancelUploadBtn.addEventListener('click', resetFileInput);

    // User search/filter
    userSearchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        if (searchTerm === '') {
            renderUserList(allUsers);
        } else {
            const filteredUsers = allUsers.filter(user =>
                user.full_name.toLowerCase().includes(searchTerm)
            );
            renderUserList(filteredUsers);
        }
    });

    // Mobile: Back to user list
    backToUsersBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (window.innerWidth < 768) {
            userListContainer.classList.remove('hidden');
            chatWindowContainer.classList.add('hidden');
            mobilePrompt.classList.remove('hidden'); // Show prompt again
            currentRecipient = null; // Deselect user

            // Stop listening to messages
            if (unsubscribeMessages) {
                unsubscribeMessages();
                unsubscribeMessages = null;
            }
        }
    });

    // --- Initial Load ---
    fetchUserList(); // This now also triggers listenForUnreadMessages

}).catch(error => {
    console.error("Fatal Error: Firebase Auth failed to initialize. Chat cannot start.", error);
    // Display a prominent error to the user
    const chatContainer = document.getElementById('chat-window-container');
    if (chatContainer) {
        chatContainer.innerHTML = `
            <div class="p-8 text-center text-red-600">
                <h2 class="text-2xl font-bold mb-4">Connection Error</h2>
                <p>Could not connect to chat service.</p>
                <p class="text-sm mt-2">Please ensure you have pasted your Firebase config into
                   <br><code>templates/header_chat.php</code>.</p>
            </div>
        `;
    }
});