<?php
// Filename: ec/patient_portal/profile.php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_connect.php';
$patient_id = $_SESSION['portal_patient_id'];
$patient_name = $_SESSION['portal_patient_name'];

// Fetch current details
$sql = "SELECT first_name, last_name, date_of_birth, contact_number, email, address, 
        emergency_contact_name, emergency_contact_relationship, emergency_contact_phone 
        FROM patients WHERE patient_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// Helper for Initials
function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
$active_page = 'profile'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Patient Portal</title>
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
                    <i data-lucide="user-cog" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Profile Settings
                </h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Read-Only ID Card -->
                <div class="lg:col-span-1">
                    <div class="card-base text-center p-6">
                        <div class="w-20 h-20 bg-indigo-100 text-brand rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold border border-indigo-200">
                            <?php echo getInitials($patient_name); ?>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                        <p class="text-sm text-muted mb-4">DOB: <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></p>

                        <div class="bg-yellow-50 text-yellow-800 p-3 rounded-lg text-xs text-left border border-yellow-200 mt-4">
                            <strong>Note:</strong> To change your Name or Date of Birth, please contact the clinic reception directly.
                        </div>
                    </div>
                </div>

                <!-- Right Column: Editable Form -->
                <div class="lg:col-span-2">
                    <form id="profileForm" class="card-base p-6">
                        <div id="form-message" class="hidden p-3 mb-4 rounded-lg text-sm border"></div>

                        <h3 class="font-bold text-lg text-gray-800 border-b pb-2 mb-4 flex items-center">
                            <i data-lucide="contact" class="w-4 h-4 mr-2 text-gray-600"></i>
                            Contact Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" name="contact_number" class="form-input w-full" value="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" name="email" class="form-input w-full" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Home Address</label>
                                <input type="text" name="address" class="form-input w-full" value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                            </div>
                        </div>

                        <h3 class="font-bold text-lg text-gray-800 border-b pb-2 mb-4 mt-6 flex items-center">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-2 text-red-500"></i>
                            Emergency Contact
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-input w-full" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Relationship</label>
                                <input type="text" name="emergency_contact_relationship" class="form-input w-full" value="<?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Emergency Phone</label>
                                <input type="tel" name="emergency_contact_phone" class="form-input w-full" value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mt-6 border-t pt-4 flex justify-end">
                            <button type="submit" class="btn-primary btn-base w-auto px-6">
                                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();

    const form = document.getElementById('profileForm');
    const msgBox = document.getElementById('form-message');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML; // Store full HTML content

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 animate-spin"></i> Saving...'; // Update to show loading icon
        lucide.createIcons(); // Re-render icon
        msgBox.classList.add('hidden');

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('api/update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (res.ok) {
                msgBox.textContent = result.message;
                msgBox.className = 'p-3 mb-4 rounded-lg text-sm bg-green-50 border-green-200 text-green-700 border';
                msgBox.classList.remove('hidden');
                setTimeout(() => msgBox.classList.add('hidden'), 3000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            msgBox.textContent = error.message || 'Failed to update profile.';
            msgBox.className = 'p-3 mb-4 rounded-lg text-sm bg-red-50 border-red-200 text-red-700 border';
            msgBox.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            lucide.createIcons();
        }
    });
</script>
</body>
</html>