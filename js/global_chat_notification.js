// Filename: js/global_chat_notification.js

document.addEventListener('DOMContentLoaded', () => {
    const chatLink = document.getElementById('sidebar-chat-link');
    const chatBadge = document.getElementById('sidebar-chat-badge');
    const originalTitle = document.title;
    let unreadPollInterval = null;

    // Function to fetch and update unread counts
    async function updateGlobalUnreadCount() {
        try {
            const response = await fetch('api/get_unread_counts.php');
            const data = await response.json();

            if (data.status === 'success') {
                let totalUnread = 0;
                for (const count of Object.values(data.counts)) {
                    totalUnread += count;
                }

                // Update Sidebar Badge
                if (chatBadge) {
                    if (totalUnread > 0) {
                        chatBadge.textContent = totalUnread;
                        chatBadge.classList.remove('hidden');
                    } else {
                        chatBadge.classList.add('hidden');
                    }
                }

                // Update Document Title
                if (totalUnread > 0) {
                    document.title = `(${totalUnread}) ${originalTitle}`;
                } else {
                    document.title = originalTitle;
                }
            }
        } catch (error) {
            console.error("Error fetching global unread counts:", error);
        }
    }

    // Start polling if the user is logged in (we assume the API handles auth check)
    // Poll every 10 seconds to avoid excessive load
    updateGlobalUnreadCount();
    unreadPollInterval = setInterval(updateGlobalUnreadCount, 10000);
});
