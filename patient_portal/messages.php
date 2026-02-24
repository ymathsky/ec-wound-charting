<?php
// Filename: ec/patient_portal/messages.php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_connect.php';
$patient_id = $_SESSION['portal_patient_id'];
$patient_name = $_SESSION['portal_patient_name'];

// --- Fetch Messages ---
$messages = [];
$check_table = $conn->query("SHOW TABLES LIKE 'patient_messages'");
if ($check_table->num_rows > 0) {
    // Note: Direction 'inbound' means the message was sent TO the clinic (FROM the patient).
    // Direction 'outbound' means the message was sent FROM the clinic (TO the patient).
    $sql = "SELECT * FROM patient_messages WHERE patient_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();

$active_page = 'messages'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Patient Portal</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- 1. Navigation Panel (Mobile Header and Sidebar) -->
<?php require_once 'nav_panel.php'; ?>

<!-- 2. Main Page Wrapper -->
<div class="page-wrapper">
    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container max-w-screen-lg">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="message-square" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Secure Messages
                </h1>
                <button onclick="openMessageModal()" class="btn-primary btn-base w-auto px-4 py-2 text-sm font-medium">
                    <i data-lucide="mail-plus" class="w-4 h-4 mr-1"></i> New Message
                </button>
            </div>

            <?php if (empty($messages)): ?>
                <div class="card-base text-center text-muted py-12 bg-gray-50 border-dashed">
                    <i data-lucide="mail" class="w-12 h-12 mx-auto mb-3 text-gray-400"></i>
                    <p>No messages found. Start a conversation with your care team.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($messages as $msg):
                        $is_patient_outbound = $msg['direction'] === 'inbound'; // Inbound to clinic = Outbound from patient

                        // Styling based on message direction
                        if ($is_patient_outbound) {
                            $card_classes = 'bg-indigo-50 border-indigo-400 border-l-4';
                            $icon_bg = 'bg-indigo-200';
                            $icon_color = 'text-indigo-700';
                            $sender = 'You (Patient)';
                            $subject_icon = 'corner-up-right';
                        } else {
                            $card_classes = 'bg-white border-green-400 border-l-4';
                            $icon_bg = 'bg-green-100';
                            $icon_color = 'text-green-700';
                            $sender = 'Care Team';
                            $subject_icon = 'stethoscope';
                        }

                        // Unread badge logic
                        $is_unread = !$is_patient_outbound && !$msg['is_read'];
                        ?>
                        <div class="card-base p-4 <?php echo $card_classes; ?> transition hover:shadow-md">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center">
                                    <!-- Sender Icon/Identity -->
                                    <div class="icon-circle-box <?php echo $icon_bg; ?> mr-3 <?php echo $icon_color; ?> flex-shrink-0">
                                        <i data-lucide="<?php echo $subject_icon; ?>" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($msg['subject']); ?></h3>
                                        <p class="text-xs text-muted">
                                            <span class="font-semibold <?php echo $icon_color; ?>"><?php echo $sender; ?></span>
                                            <span class="mx-1">•</span>
                                            <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($is_unread): ?>
                                    <span class="bg-red-50 text-red-700 text-xs font-bold px-2 py-1 rounded-full border border-red-200 animate-pulse">NEW</span>
                                <?php endif; ?>
                            </div>
                            <!-- Message Body -->
                            <p class="text-gray-700 text-sm whitespace-pre-wrap pl-[44px] pt-2 mt-2 border-t border-gray-100">
                                <?php echo htmlspecialchars($msg['body']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- New Message Modal -->
<div id="messageModal" class="modal-overlay hidden items-center justify-center">
    <div class="modal-content">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                <i data-lucide="send" class="w-5 h-5 mr-2 text-indigo-600"></i>
                Send a Secure Message
            </h3>
            <button onclick="closeMessageModal()" class="text-gray-500 hover:text-gray-800 text-2xl p-1 rounded-full hover:bg-gray-100 transition-colors">&times;</button>
        </div>

        <!-- Submission/Status Message Box -->
        <div id="message-modal-message" class="hidden p-3 mb-4 rounded-lg text-sm border"></div>

        <form id="messageForm">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <select name="subject" class="form-input w-full bg-white" required>
                        <option value="Appointment Question">Appointment Question</option>
                        <option value="Medication Refill">Medication Refill</option>
                        <option value="Wound Concern">Wound Concern</option>
                        <option value="Billing Question">Billing Question</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="body" rows="5" class="form-input w-full" required placeholder="Type your message here..."></textarea>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="btn-primary flex items-center justify-center">
                    <i data-lucide="send" class="w-4 h-4 mr-2"></i>
                    Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    function openMessageModal() {
        document.getElementById('messageModal').classList.remove('hidden');
        document.getElementById('messageModal').classList.add('flex');
        document.getElementById('messageForm').reset();
        document.getElementById('message-modal-message').classList.add('hidden');
    }

    function closeMessageModal() {
        document.getElementById('messageModal').classList.add('hidden');
        document.getElementById('messageModal').classList.remove('flex');
    }

    function displayMessageModalMessage(message, isSuccess = true) {
        const msgBox = document.getElementById('message-modal-message');
        msgBox.textContent = message;
        if (isSuccess) {
            msgBox.className = 'p-3 mb-4 rounded-lg text-sm bg-green-50 border-green-200 text-green-700 border';
        } else {
            msgBox.className = 'p-3 mb-4 rounded-lg text-sm bg-red-50 border-red-200 text-red-700 border';
        }
        msgBox.classList.remove('hidden');
    }


    document.getElementById('messageForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const originalContent = btn.innerHTML;

        displayMessageModalMessage("", true);

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 animate-spin"></i> Sending...';
        lucide.createIcons();

        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (res.ok) {
                displayMessageModalMessage('Message sent successfully.', true);

                // Disable form inputs after success, keeping the success message visible
                document.getElementById('messageForm').querySelectorAll('select, textarea').forEach(el => el.disabled = true);

                setTimeout(() => {
                    window.location.reload();
                }, 2000);

            } else {
                displayMessageModalMessage(result.message || 'Failed to send message.', false);
            }
        } catch (error) {
            displayMessageModalMessage('Failed to connect to server.', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            lucide.createIcons();
        }
    });
</script>
</body>
</html>