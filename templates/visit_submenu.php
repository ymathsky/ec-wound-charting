<?php
// Filename: templates/visit_submenu.php
// This is the responsive, scrolling sub-navigation for the "Today's Visit" workflow.

// Get the current page filename to highlight the active link
$current_page = basename($_SERVER['PHP_SELF']);

// Get the required IDs from the URL to build the links
// These variables are expected to be set by the parent page (e.g., visit_vitals.php, visit_hpi.php, etc.)
$patient_id = isset($patient_id) ? $patient_id : (isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0);
$appointment_id = isset($appointment_id) ? $appointment_id : (isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0);
$user_id = isset($user_id) ? $user_id : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0); // Pass user_id along

// Helper function to build the links
function visit_nav_link($href, $text, $icon, $current_page) {
    // Extract the filename from the href
    $href_parts = parse_url($href);
    $file_name = basename($href_parts['path']);
    $is_active = ($file_name == $current_page);

    $active_class = $is_active
        ? 'border-indigo-600 text-indigo-600'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';

    return "
    <a href=\"$href\" 
       class=\"$active_class flex items-center whitespace-nowrap py-3 px-4 border-b-4 font-medium text-sm transition-colors\">
       <i data-lucide=\"$icon\" class=\"w-4 h-4 mr-2\"></i>
       <span class=\"hidden sm:inline\">$text</span>
    </a>";
}

?>

<!--
  This navigation bar is included on all 'visit_*.php' pages.
  It provides a consistent workflow menu for the clinician.
-->
<div class="bg-white border-b border-gray-200 sticky top-0 lg:top-auto z-20">
    <div class="flex overflow-x-auto flex-nowrap lg:justify-center">
        <?php echo visit_nav_link("visit_vitals.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Vitals', 'heart-pulse', $current_page); ?>
        <?php echo visit_nav_link("visit_hpi.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'HPI', 'clipboard-list', $current_page); ?>
        <?php echo visit_nav_link("visit_wounds.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Wounds', 'bandage', $current_page); ?>
        <?php echo visit_nav_link("visit_diagnosis.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Diagnosis', 'stethoscope', $current_page); ?>
        <?php echo visit_nav_link("visit_medications.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Meds', 'pilcrow', $current_page); ?>
        <!-- === NEWLY ADDED PROCEDURES LINK === -->
        <?php echo visit_nav_link("visit_procedure.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Procedures', 'dollar-sign', $current_page); ?>
        <?php echo visit_nav_link("visit_notes.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Advanced Note', 'file-text', $current_page); ?>
        <?php echo visit_nav_link("visit_summary.php?appointment_id=$appointment_id&patient_id=$patient_id&user_id=$user_id", 'Simplified Note', 'check-square', $current_page); ?>

        <!-- This link is special, it's not a step but a final output -->
        <a href="visit_report.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>"
           target="_blank"
           class="border-transparent text-green-600 hover:text-green-700 hover:border-gray-300 flex items-center whitespace-nowrap py-3 px-4 border-b-4 font-medium text-sm transition-colors ml-auto lg:ml-4">
            <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
            <span class="hidden sm:inline">View Report</span>
        </a>
    </div>
</div>