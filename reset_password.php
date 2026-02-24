<?php
// Filename: ec/chat.php
// Ensure session is started and user is authenticated
session_start();
// CORRECTED: Use 'ec_user_id' for session checks
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// CORRECTED: Use 'ec_user_id' and 'ec_full_name'
$current_user_id = $_SESSION['ec_user_id'];
$current_username = $_SESSION['ec_full_name'] ?? 'User';

// Include header and sidebar
include_once 'templates/header.php';
include_once 'templates/sidebar.php';
?>

<!-- IMPORTANT FIX: Added ID for aggressive CSS targeting and d-flex/flex-column to ensure vertical height distribution -->
<div class="content-wrapper d-flex flex-column" id="chat-content-wrapper">
    <!-- This container needs to fill the space defined by the content-wrapper -->
    <div class="container-fluid pt-3 chat-main-container d-flex flex-column flex-grow-1">

        <h1 class="h3 mb-4 text-gray-800">Secure Messaging Center</h1>

        <!-- The main row for chat, using flex-grow-1 to fill remaining space below the H1 -->
        <div class="row chat-content-row flex-grow-1" id="chat-area" style="min-height: 0;">

            <!-- User List Panel (3/12 width) -->
            <div class="col-md-4 col-lg-3 h-100 d-flex flex-column">
                <!-- Card takes full height and uses flex to manage content -->
                <div class="card shadow-lg h-100 d-flex flex-column mb-0">
                    <div class="card-header py-3 bg-primary">
                        <h6 class="m-0 font-weight-bold text-white">Contacts</h6>
                    </div>
                    <!-- List group takes remaining height and scrolls -->
                    <div class="list-group list-group-flush flex-grow-1" id="user-list-container" style="overflow-y: auto;">
                        <!-- User and Group list will be populated here by JS -->
                    </div>
                </div>
            </div>

            <!-- Chat Panel (9/12 width) -->
            <div class="col-md-8 col-lg-9 h-100 d-flex flex-column">
                <!-- Card takes full height and uses flex to manage content -->
                <div class="card shadow-lg h-100 d-flex flex-column">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white border-bottom" id="chat-header">
                        <h6 class="m-0 font-weight-bold text-primary">Select a contact to start chatting.</h6>
                    </div>

                    <!-- Chat Messages Area - Must flex-grow-1 to fill space and enable scrolling -->
                    <div class="card-body chat-box flex-grow-1" id="chat-messages-container" style="overflow-y: auto;">
                        <!-- Messages will be loaded here -->
                        <div class="text-center text-muted mt-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>Select **All Users** for broadcast messages or an individual user for a private chat.</p>
                        </div>
                    </div>

                    <!-- Message Input Area (Fixed Height Footer) -->
                    <div class="card-footer bg-light">
                        <form id="chat-form" enctype="multipart/form-data">
                            <input type="hidden" id="recipient-id" name="recipient_id" value="">
                            <div class="input-group">
                                <!-- File Upload Button -->
                                <label for="file-attachment" class="btn btn-outline-secondary rounded-l-md m-0" data-toggle="tooltip" title="Attach Document or Photo">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" id="file-attachment" name="chat_file" style="display: none;" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx">

                                <!-- Message Text Input -->
                                <input type="text" id="message-input" name="message_text" class="form-control" placeholder="Type your message..." autocomplete="off">

                                <!-- Attachment Preview -->
                                <div id="file-preview" class="input-group-append d-none align-items-center bg-white border border-secondary border-left-0 px-2 rounded-r-md">
                                    <span class="file-name text-truncate" style="max-width: 150px; font-size: 0.8rem;"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="remove-file-btn" title="Remove Attachment">&times;</button>
                                </div>

                                <!-- Send Button -->
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit" id="send-button">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1" id="loading-indicator" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Sending...</small>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        const CURRENT_USER_ID = <?php echo json_encode($current_user_id); ?>;
        const CHAT_INTERVAL = 3000; // Poll every 3 seconds
        let selectedRecipientId = null;
        let intervalHandle = null;

        // --- Utility Functions ---

        /**
         * Formats the time for display.
         */
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        /**
         * Toggles the loading indicator.
         */
        function toggleLoading(isLoading) {
            $('#send-button').prop('disabled', isLoading);
            $('#message-input').prop('disabled', isLoading);
            $('#file-attachment').prop('disabled', isLoading);
            if (isLoading) {
                $('#loading-indicator').show();
            } else {
                $('#loading-indicator').hide();
            }
        }

        // --- User List Logic ---

        /**
         * Fetches and displays the list of users (and the "All Users" option).
         */
        function loadUserList() {
            $.ajax({
                url: 'api/get_user_list_for_chat.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    const container = $('#user-list-container');
                    container.empty();

                    // 1. Add "All Users" (Group Chat) option
                    const allUsersItem = $('<a>', {
                        href: '#',
                        class: 'list-group-item list-group-item-action d-flex justify-content-between align-items-center',
                        'data-recipient-id': 'group',
                        html: '<div class="font-weight-bold"><i class="fas fa-users mr-2 text-primary"></i> All Users (Broadcast)</div>'
                    });
                    container.append(allUsersItem);

                    // 2. Add Individual Users
                    response.users.forEach(user => {
                        // Skip the current logged-in user
                        if (user.user_id == CURRENT_USER_ID) return;

                        const userItem = $('<a>', {
                            href: '#',
                            class: 'list-group-item list-group-item-action d-flex justify-content-between align-items-center',
                            'data-recipient-id': user.user_id,
                            html: `
                            <div>
                                <img src="${user.profile_picture || 'https://placehold.co/40x40/cccccc/333333?text=${user.username.charAt(0)}'}" onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=${user.username.charAt(0)}';" class="rounded-circle mr-2" style="width: 40px; height: 40px; object-fit: cover;">
                                ${user.username}
                            </div>
                        `
                        });
                        container.append(userItem);
                    });

                    // Attach click handler to list items
                    container.find('a').on('click', handleContactClick);

                    // Re-select the currently active chat
                    if (selectedRecipientId !== null) {
                        container.find(`[data-recipient-id='${selectedRecipientId}']`).addClass('active');
                    } else if (response.users.length > 1) {
                        // Automatically select "All Users" if no chat is active
                        allUsersItem.trigger('click');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading user list:", error);
                }
            });
        }

        /**
         * Handles clicking on a contact in the list.
         */
        function handleContactClick(e) {
            e.preventDefault();
            const listItem = $(this);
            const newRecipientId = listItem.data('recipient-id');

            // Update active class
            $('#user-list-container a').removeClass('active');
            listItem.addClass('active');

            // Update internal state
            selectedRecipientId = newRecipientId;

            // Update chat header
            const recipientName = listItem.text().trim().replace(/(\r\n|\n|\r)/gm, "").split(' ')[0];
            $('#chat-header h6').html('<i class="fas fa-comment-alt mr-2"></i> Chat with ' + recipientName);

            // Update form's hidden input
            $('#recipient-id').val(newRecipientId === 'group' ? '' : newRecipientId);

            // Start/Restart message polling
            clearInterval(intervalHandle);
            loadMessages(); // Load immediately
            intervalHandle = setInterval(loadMessages, CHAT_INTERVAL);
        }


        // --- Message Display Logic ---

        /**
         * Renders a single message element.
         */
        function renderMessage(message) {
            const isSender = message.sender_id == CURRENT_USER_ID;
            const alignmentClass = isSender ? 'justify-content-end' : 'justify-content-start';
            const bubbleClass = isSender ? 'bg-primary text-white' : 'bg-light text-dark';
            const senderInfo = isSender ? 'You' : message.sender_username;

            let messageContent = '';

            if (message.message_text) {
                messageContent += `<p class="mb-1">${message.message_text}</p>`;
            }

            if (message.file_path) {
                const fileName = message.file_name || 'File';
                const filePath = message.file_path;
                const fileIcon = getFileIcon(message.file_type);

                if (message.file_type && message.file_type.startsWith('image/')) {
                    // Image preview
                    messageContent += `
                    <a href="${filePath}" target="_blank" class="d-block mb-1 chat-image-link" title="View Image">
                        <img src="${filePath}" class="img-fluid rounded shadow-sm" style="max-height: 200px; max-width: 100%; object-fit: cover;">
                    </a>
                `;
                } else {
                    // Document/File link
                    messageContent += `
                    <div class="card p-2 mb-1 bg-white border">
                        <i class="${fileIcon} mr-2"></i>
                        <a href="${filePath}" target="_blank" class="text-truncate" style="max-width: 100%;">${fileName}</a>
                    </div>
                `;
                }
            }

            if (!messageContent) return; // Should not happen if data is clean

            const chatBubble = `
            <div class="d-flex ${alignmentClass} mb-4">
                <div class="message-bubble p-3 rounded-lg shadow-sm ${bubbleClass} mw-75">
                    <div class="font-weight-bold mb-1" style="font-size: 0.75rem;">${senderInfo}</div>
                    ${messageContent}
                    <small class="text-muted text-right d-block mt-1" style="font-size: 0.7rem;">${formatTime(message.sent_at)}</small>
                </div>
            </div>
        `;
            return chatBubble;
        }

        /**
         * Determines the appropriate Font Awesome icon based on file type.
         */
        function getFileIcon(fileType) {
            if (!fileType) return 'fas fa-file';
            if (fileType.startsWith('image/')) return 'fas fa-file-image text-info';
            if (fileType.includes('pdf')) return 'fas fa-file-pdf text-danger';
            if (fileType.includes('word') || fileType.includes('doc')) return 'fas fa-file-word text-primary';
            if (fileType.includes('excel') || fileType.includes('xls')) return 'fas fa-file-excel text-success';
            if (fileType.includes('text')) return 'fas fa-file-alt text-secondary';
            return 'fas fa-file-alt text-secondary';
        }


        /**
         * Fetches and displays chat messages for the selected recipient.
         */
        function loadMessages() {
            if (selectedRecipientId === null) return;

            const container = $('#chat-messages-container');
            const isScrolledToBottom = container[0].scrollHeight - container[0].clientHeight <= container[0].scrollTop + 1;
            const recipientIdToSend = selectedRecipientId === 'group' ? '' : selectedRecipientId;

            $.ajax({
                url: 'api/get_messages.php',
                method: 'GET',
                data: { recipient_id: recipientIdToSend },
                dataType: 'json',
                success: function(response) {
                    if (response.messages && response.messages.length > 0) {
                        let chatHtml = '';
                        response.messages.forEach(msg => {
                            chatHtml += renderMessage(msg);
                        });

                        // Only update if content has actually changed to prevent flickering
                        if (container.html().trim() !== chatHtml.trim()) {
                            container.html(chatHtml);
                        }
                    } else {
                        container.html('<div class="text-center text-muted mt-5"><i class="fas fa-comment-dots fa-3x mb-3"></i><p>No messages yet. Be the first to start the conversation!</p></div>');
                    }

                    // Auto-scroll only if it was already near the bottom
                    if (isScrolledToBottom) {
                        container.scrollTop(container[0].scrollHeight);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading messages:", error);
                }
            });
        }

        // --- File Attachment UI/UX ---
        $('#file-attachment').on('change', function() {
            const file = this.files[0];
            if (file) {
                $('#file-preview').removeClass('d-none').addClass('d-flex');
                $('.file-name').text(file.name);
                $('#message-input').prop('placeholder', 'Optional: Add a caption...');
            } else {
                $('#file-preview').removeClass('d-flex').addClass('d-none');
                $('.file-name').text('');
                $('#message-input').prop('placeholder', 'Type your message...');
            }
        });

        $('#remove-file-btn').on('click', function() {
            $('#file-attachment').val(''); // Clear file input
            $('#file-attachment').trigger('change'); // Trigger change event to hide preview
        });


        // --- Message Sending Logic (Form Submit) ---
        $('#chat-form').on('submit', function(e) {
            e.preventDefault();

            if (selectedRecipientId === null) {
                // Should not happen if auto-selection works, but as a safeguard
                alert('Please select a recipient first.');
                return;
            }

            const messageText = $('#message-input').val().trim();
            const fileInput = $('#file-attachment')[0];
            const file = fileInput.files[0];

            // Prevent sending empty message/file
            if (!messageText && !file) {
                return;
            }

            toggleLoading(true);

            const recipientId = $('#recipient-id').val();

            // Step 1: Handle File Upload if present
            if (file) {
                const formData = new FormData();
                formData.append('chat_file', file);

                $.ajax({
                    url: 'api/upload_chat_file.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Step 2a: Send Message with File Data
                            sendMessage(recipientId, messageText, response.file_path, response.file_type, file.name);
                        } else {
                            alert('File upload failed: ' + response.message);
                            toggleLoading(false);
                        }
                    },
                    error: function(xhr) {
                        alert('An error occurred during file upload. Check console for details.');
                        console.error("File upload error:", xhr.responseText);
                        toggleLoading(false);
                    }
                });
            } else {
                // Step 2b: Send plain text message
                sendMessage(recipientId, messageText, null, null, null);
            }
        });

        /**
         * Sends the message data to the backend API.
         */
        function sendMessage(recipientId, messageText, filePath, fileType, fileName) {
            $.ajax({
                url: 'api/send_message.php',
                method: 'POST',
                data: {
                    recipient_id: recipientId,
                    message_text: messageText,
                    file_path: filePath,
                    file_type: fileType,
                    file_name: fileName
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear inputs and reload messages
                        $('#message-input').val('');
                        $('#file-attachment').val('');
                        $('#file-attachment').trigger('change'); // Hide preview
                        loadMessages(); // Reload immediately after sending
                    } else {
                        alert('Failed to send message: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('An error occurred while sending the message. Check console for details.');
                    console.error("Send message error:", xhr.responseText);
                },
                complete: function() {
                    toggleLoading(false);
                }
            });
        }

        // --- Initialization ---
        loadUserList();
        // Tooltips initialization (for attachment icon)
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<style>
    /* Custom styles for the chat interface */

    /* *** FIX: Aggressive height enforcement *** */
    html, body, #wrapper {
        height: 100%;
        min-height: 100vh;
    }

    /* Ensure the wrapper of the chat section takes up the remaining height */
    #chat-content-wrapper {
        display: flex;
        flex-direction: column;
        flex-grow: 1; /* Should take up all available vertical space */
        min-height: 100%;
    }

    /* Force the container fluid to be a flex child that grows, and also a flex parent */
    .chat-main-container {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    /* The row that holds the chat panels must also grow to fill the container, MINUS the H1 height */
    .chat-content-row {
        flex-grow: 1;
        min-height: 0;
        /* Use a viewport calc only if flex-grow fails due to outer container issues,
           but relying on flex-grow: 1 here is cleaner: */
        /* If the page structure has a fixed header, use this fallback: */
        /* height: calc(100vh - 180px); */
    }

    /* Ensure the columns take the full height of the row */
    .chat-content-row .col-md-4,
    .chat-content-row .col-md-8,
    .chat-content-row .col-lg-3,
    .chat-content-row .col-lg-9 {
        height: 100%;
    }

    /* --- General Chat Styling --- */

    .chat-box {
        display: flex;
        flex-direction: column;
        padding: 1rem;
        background-color: #f7f9fc; /* Light background for chat area */
    }

    .chat-box::-webkit-scrollbar {
        width: 6px;
    }
    .chat-box::-webkit-scrollbar-thumb {
        background-color: #ccc;
        border-radius: 3px;
    }

    .list-group-item:hover {
        cursor: pointer;
        background-color: #e9ecef;
    }

    .list-group-item.active {
        background-color: #4e73df;
        border-color: #4e73df;
        color: white !important;
    }

    .list-group-item.active * {
        color: white !important;
    }

    .message-bubble {
        max-width: 75%;
        word-wrap: break-word;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border-radius: 0.5rem;
    }

    /* Sender (right) */
    .justify-content-end .message-bubble {
        background-color: #4e73df;
        color: white;
        border-bottom-right-radius: 0;
    }

    /* Receiver (left) */
    .justify-content-start .message-bubble {
        background-color: #ffffff;
        border: 1px solid #e3e6f0;
        border-bottom-left-radius: 0;
    }

    /* File/Image link styling inside message bubble */
    .chat-image-link img {
        border: 3px solid white;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
    }

    .card-footer .input-group-append .file-name {
        display: inline-block;
        padding: 0.375rem 0;
    }

    .input-group > .rounded-l-md {
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .input-group > .rounded-r-md {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }
</style>