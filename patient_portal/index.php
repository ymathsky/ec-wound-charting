<?php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_connect.php';
$patient_id = $_SESSION['portal_patient_id'];
$patient_name = $_SESSION['portal_patient_name'];

// --- Fetch Data ---

// 1. Next Appointment (Confirmed/Scheduled)
$appt_sql = "SELECT appointment_date, appointment_type, u.full_name as doctor, a.status 
             FROM appointments a 
             LEFT JOIN users u ON a.user_id = u.user_id
             WHERE patient_id = ? AND appointment_date >= CURDATE() 
             AND a.status IN ('Scheduled', 'Confirmed')
             ORDER BY appointment_date ASC LIMIT 1";
$stmt = $conn->prepare($appt_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$next_appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 1b. Check for Pending Requests
$pending_sql = "SELECT appointment_date FROM appointments WHERE patient_id = ? AND status = 'Pending' AND appointment_date >= CURDATE() LIMIT 1";
$stmt = $conn->prepare($pending_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$pending_req = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Wounds Summary (Active & Healed)
$wound_sql = "
    SELECT w.*, 
    (
        SELECT image_path 
        FROM (
            SELECT image_path, uploaded_at, wound_id FROM wound_images
            UNION ALL
            SELECT image_path, uploaded_at, wound_id FROM patient_wound_photos
        ) as all_images 
        WHERE all_images.wound_id = w.wound_id 
        ORDER BY uploaded_at DESC LIMIT 1
    ) as latest_image
    FROM wounds w 
    WHERE patient_id = ? 
    ORDER BY status, created_at DESC";

$stmt = $conn->prepare($wound_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$wounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Latest Progress Note
$note_sql = "SELECT assessment, plan, note_date FROM visit_notes WHERE patient_id = ? ORDER BY note_date DESC LIMIT 1";
$stmt = $conn->prepare($note_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$latest_note = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

// REMOVED: getInitials() definition is now in nav_panel.php
$active_page = 'index'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Health Dashboard | EC Wound Charting</title>
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
        <div class="container">

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Welcome, <?php echo explode(' ', $patient_name)[0]; ?></h1>
                <p class="opacity-90 text-indigo-100">Your health journey at a glance.</p>
            </div>

            <!-- New Summary Grid (3 Columns) -->
            <div class="dashboard-grid summary-grid gap-6 mb-8">

                <!-- 1. Next Appointment Card -->
                <div class="min-h-[150px]">
                    <div class="section-header justify-between">
                        <div class="flex items-center">
                            <i data-lucide="calendar-clock" class="w-5 h-5 mr-2 text-indigo-600"></i>
                            <h2 class="section-title mb-0">Next Visit</h2>
                        </div>
                        <?php if (!$pending_req): ?>
                            <!-- LINK TO NEW APPOINTMENTS PAGE -->
                            <a href="appointments.php" class="btn-primary btn-base w-auto px-3 py-1.5 text-xs font-medium hover:ring-2 ring-indigo-300">
                                <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Request Appt
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($pending_req): ?>
                        <div class="card bg-yellow-50 border-yellow-200 p-4 flex items-start h-full shadow-md">
                            <i data-lucide="clock" class="w-5 h-5 text-yellow-600 mr-3 mt-0.5 flex-shrink-0"></i>
                            <div>
                                <h3 class="text-sm font-bold text-yellow-800">Request Pending</h3>
                                <p class="text-xs text-yellow-700 mt-1">An appointment for <strong><?php echo date('M d, Y', strtotime($pending_req['appointment_date'])); ?></strong> is awaiting confirmation.</p>
                                <a href="appointments.php" class="text-yellow-600 font-semibold text-xs mt-2 hover:underline">View details</a>
                            </div>
                        </div>
                    <?php elseif ($next_appt):
                        $date = new DateTime($next_appt['appointment_date']);
                        ?>
                        <!-- Highlighted Scheduled Appointment -->
                        <div class="card appt-box bg-indigo-50 border-indigo-400 shadow-md h-full">
                            <div class="appt-date bg-white border-indigo-200 shadow-md">
                                <div class="appt-day"><?php echo $date->format('d'); ?></div>
                                <div class="appt-month text-indigo-600"><?php echo $date->format('M'); ?></div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-900 text-lg flex items-center">
                                    <i data-lucide="check-circle" class="w-4 h-4 mr-1 text-success"></i>
                                    <?php echo htmlspecialchars($next_appt['appointment_type']); ?>
                                </div>
                                <div class="text-sm text-gray-700 mt-1 flex items-center">
                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                    <?php echo $date->format('l, h:i A'); ?>
                                </div>
                                <?php if($next_appt['doctor']): ?>
                                    <div class="text-sm text-gray-600 mt-1 flex items-center">
                                        <i data-lucide="user" class="w-3 h-3 mr-1"></i>
                                        Dr. <?php echo htmlspecialchars($next_appt['doctor']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Enhanced No Appointment State -->
                        <div class="card flex flex-col items-center justify-center py-6 text-center h-full border-dashed border-gray-400 border-2">
                            <div class="bg-gray-50 p-3 rounded-full mb-3">
                                <i data-lucide="calendar-check" class="w-6 h-6 text-gray-400"></i>
                            </div>
                            <p class="text-gray-500 font-medium text-sm mb-3">You currently have no upcoming visits.</p>
                            <a href="appointments.php" class="btn-primary btn-base w-auto px-4 py-2 text-sm">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-1"></i> Schedule a Visit Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. Latest Care Instructions Card -->
                <div class="min-h-[150px]">
                    <div class="section-header">
                        <i data-lucide="clipboard-list" class="w-5 h-5 mr-2 text-green-600"></i>
                        <h2 class="section-title mb-0">Latest Instructions</h2>
                    </div>
                    <?php if ($latest_note && !empty($latest_note['plan'])): ?>
                        <div class="care-note-card h-full relative">
                            <p class="text-xs font-bold text-green-800 uppercase tracking-wide mb-2">
                                From visit on <?php echo date('F j, Y', strtotime($latest_note['note_date'])); ?>
                            </p>
                            <div class="prose max-h-20 overflow-hidden relative">
                                <?php echo nl2br(htmlspecialchars($latest_note['plan'])); ?>
                                <!-- Gradient fade to hide truncated text -->
                                <div class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-green-50 to-transparent"></div>
                            </div>
                            <a href="documents.php" class="text-xs text-green-700 font-semibold mt-2 block hover:underline">View All Notes</a>
                        </div>
                    <?php else: ?>
                        <div class="card flex flex-col items-center justify-center py-6 text-center h-full">
                            <div class="bg-gray-50 p-2 rounded-full mb-3">
                                <i data-lucide="file-text" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <p class="text-gray-500 font-medium text-sm">No recent care instructions.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Quick Action Card: Upload Photo -->
                <a href="upload_photo.php" class="link-card card-interactive flex flex-col items-start justify-center p-6 h-full min-h-[150px] relative">
                    <div class="icon-box bg-indigo-light mb-3">
                        <i data-lucide="camera" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg">Upload Progress Photo</h3>
                        <p class="text-sm text-gray-500 mt-1">Securely share wound status with your care team.</p>
                    </div>
                    <!-- Right arrow for interaction feedback -->
                    <i data-lucide="arrow-right" class="w-5 h-5 text-indigo-400 absolute right-4 top-1/2 -translate-y-1/2"></i>
                </a>

            </div> <!-- End of Summary Grid -->

            <!-- Wounds Section -->
            <div class="mb-8">
                <div class="section-header">
                    <i data-lucide="activity" class="w-5 h-5 mr-2 text-indigo-600"></i>
                    <h2 class="section-title mb-0">My Wounds</h2>
                </div>

                <?php if (empty($wounds)): ?>
                    <div class="card text-center text-gray-500 py-12">
                        <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 text-green-500 opacity-20"></i>
                        <p class="font-medium">No active wound records found.</p>
                    </div>
                <?php else: ?>
                    <div class="wound-grid">
                        <?php foreach ($wounds as $wound): ?>
                            <!-- Updated Wound Card HTML -->
                            <div class="wound-item group hover:shadow-lg transition-all">
                                <div class="wound-img-container">
                                    <?php if ($wound['latest_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($wound['latest_image']); ?>" alt="Wound Photo">
                                    <?php else: ?>
                                        <div class="text-gray-400 flex flex-col items-center">
                                            <i data-lucide="image-off" class="w-8 h-8 mb-2 opacity-50"></i>
                                            <span class="text-xs">No Recent Photo</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="wound-info">

                                    <!-- Status Badge (Absolute position defined in CSS) -->
                                    <span class="status-badge <?php echo $wound['status'] === 'Healed' ? 'status-healed' : 'status-active'; ?>">
                                        <?php echo htmlspecialchars($wound['status']); ?>
                                    </span>

                                    <!-- Location and Details -->
                                    <div class="mb-3 pr-16">
                                        <h3 class="font-bold text-lg text-gray-900 leading-tight">
                                            <?php echo htmlspecialchars($wound['location']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-1 flex items-center gap-1">
                                            <i data-lucide="tag" class="w-3 h-3 flex-shrink-0"></i>
                                            <?php echo htmlspecialchars($wound['wound_type']); ?>
                                        </p>
                                    </div>

                                    <div class="text-xs text-gray-500 flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3 h-3 flex-shrink-0"></i>
                                        <span class="font-medium">Started:</span> <?php echo date('M d, Y', strtotime($wound['date_onset'])); ?>
                                    </div>
                                </div>
                            </div>
                            <!-- End Updated Wound Card HTML -->
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>


<!-- REMOVED: Request Appointment Modal and associated JS functions -->

<script>
    lucide.createIcons();
    // Only lucide.createIcons() remains as all other modal logic was moved to appointments.php
</script>
</body>
</html>