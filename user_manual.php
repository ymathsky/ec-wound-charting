<?php
// Filename: user_manual.php

require_once 'templates/header.php';
require_once 'db_connect.php';
// Define the available guides and their iframe sources
$guides = [
    'patient_management' => [ // PARENT ITEM
        'title' => 'Patient Management',
        'src' => '#', // Parent is not directly clickable
        'parent' => null
    ],
    'patient_management_overview' => [ // SUBMENU ITEM - Overview
        'title' => 'Overview',
        'src' => 'https://app.guidemaker.com/guide/8196b61c-6f45-4615-954f-a8af96911dd0?layout=PAGED',
        'parent' => 'patient_management' // Child of 'patient_management'
    ],
    'add_patient_guide' => [ // SUBMENU ITEM - Add New Patient
        'title' => 'Add New Patient',
        'src' => 'https://app.guidemaker.com/guide/8c0b7cdd-61c8-4063-9ef6-13f950946be5?layout=PAGED',
        'parent' => 'patient_management' // Child of 'patient_management'
    ],
    'view_patient_profile_guide' => [ // RENAMED & UPDATED SUBMENU ITEM
        'title' => 'Profile and Wounds', // Renamed from "View Patient Profile"
        'src' => 'https://app.guidemaker.com/guide/1809df0b-da6f-4dab-a6cf-6bc4873dda63', // Corrected src from user query
        'parent' => 'patient_management' // Child of 'patient_management'
    ],
    'patient_management_emr' => [ // UPDATED SUBMENU ITEM - EMR
        'title' => 'EMR',
        'src' => 'https://app.guidemaker.com/guide/9d8a97ac-1821-420e-be9c-988f2bb3ec00', // Updated src
        'parent' => 'patient_management' // Child of 'patient_management'
    ],
    'patient_management_past_appt' => [ // SUBMENU ITEM - Past Appt
        'title' => 'Past Appt',
        'src' => 'https://app.guidemaker.com/guide/bd7577ba-5a0a-4899-b1f6-d5419886f7d0',
        'parent' => 'patient_management' // Child of 'patient_management'
    ],
    'user_management' => [ // PARENT ITEM
        'title' => 'User Management',
        'src' => '#', // Parent is not directly clickable
        'parent' => null
    ],
    'user_management_overview' => [ // SUBMENU ITEM - Overview
        'title' => 'Overview',
        'src' => 'https://app.guidemaker.com/guide/797c641c-f2f5-4a55-a5bf-375f430870a6?layout=PAGED',
        'parent' => 'user_management'
    ],
    'add_user_guide' => [ // SUBMENU ITEM - Add New User
        'title' => 'Add New User',
        'src' => 'https://app.guidemaker.com/guide/9d5e15a7-bfac-44da-b738-2eb2265b3ed2?layout=PAGED',
        'parent' => 'user_management'
    ],
    'edit_user_guide' => [ // SUBMENU ITEM - Edit User
        'title' => 'Edit User',
        'src' => 'https://app.guidemaker.com/guide/39cf2fa6-5636-4ad9-83b3-0736d05e4014?layout=PAGED',
        'parent' => 'user_management'
    ],
    'new_guide' => [
        'title' => 'New Guide',
        'src' => 'https://app.guidemaker.com/guide/db19e8e4-e5c2-4adc-a170-8c0d96cf0174?layout=PAGED',
        'parent' => null
    ]
    // Add more guides here. For submenus, set the 'parent' key to the key of the parent guide.
];

// Determine which guide to display
// Default to the first guide if none is selected or the selected one is invalid
$first_guide_key = !empty($guides) ? array_key_first($guides) : null;
// If the first guide is a parent, default to its first child
if ($first_guide_key && isset($guides[$first_guide_key]) && $guides[$first_guide_key]['src'] === '#') {
    foreach ($guides as $key => $guide) {
        // Ensure 'parent' key exists before comparing
        if (isset($guide['parent']) && $guide['parent'] === $first_guide_key) {
            $first_guide_key = $key;
            break;
        }
    }
}

$current_guide_key = isset($_GET['guide']) && isset($guides[$_GET['guide']]) ? $_GET['guide'] : $first_guide_key;
$current_guide = $guides[$current_guide_key] ?? null; // Handle case where guides might be empty

// Find the parent of the current guide, if it exists
$current_parent_key = isset($current_guide_key) && isset($guides[$current_guide_key]['parent']) ? $guides[$current_guide_key]['parent'] : null;


?>

    <style>
        /* Style for the right-side menu */
        .manual-menu-item { /* Changed from .manual-menu a */
            display: block;
            border-radius: 0.375rem;
            font-weight: 500;
            color: #374151; /* gray-700 */
            text-decoration: none;
        }
        .manual-menu-link { /* Style for the actual link inside the item */
            display: flex; /* Use flex to align icon and text */
            justify-content: space-between; /* Push icon to the right */
            align-items: center; /* Center items vertically */
            padding: 0.75rem 1rem;
            transition: background-color 0.2s ease;
            cursor: pointer; /* Indicate clickability */
        }
        .manual-menu-link:hover {
            background-color: #f3f4f6; /* gray-100 */
        }
        .manual-menu-item.active > .manual-menu-link { /* Apply active style to the link within the active item */
            background-color: #3b82f6; /* blue-500 */
            color: white;
            font-weight: 600;
        }
        /* Style for submenu container */
        .manual-submenu {
            margin-left: 1rem; /* Indentation */
            padding-left: 0.5rem;
            border-left: 2px solid #e5e7eb; /* Subtle left border */
            margin-top: 0.25rem;
            margin-bottom: 0.5rem;
            max-height: 0; /* Initially hidden */
            overflow: hidden; /* Hide content when collapsed */
            transition: max-height 0.3s ease-out; /* Smooth transition */
        }
        .manual-submenu.open {
            max-height: 500px; /* Allow space for content when open */
        }
        .manual-submenu a {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            font-size: 0.875rem; /* Slightly smaller text */
            color: #4b5563; /* gray-600 */
            display: block; /* Ensure links take full width */
            border-radius: 0.375rem;
            padding-left: 1rem; /* Indent submenu links */
        }
        .manual-submenu a.active {
            background-color: #60a5fa; /* lighter blue for active submenu */
            color: white;
            font-weight: 600;
        }
        .manual-menu-link .submenu-toggle-icon {
            transition: transform 0.3s ease-out;
            width: 1rem; /* Adjust size */
            height: 1rem; /* Adjust size */
        }
        .manual-menu-link .submenu-toggle-icon.open {
            transform: rotate(90deg);
        }

        /* Ensure the iframe container takes full height */
        .iframe-container {
            height: calc(100vh - 112px); /* Adjust based on header/padding height */
        }
    </style>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="book-open" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        User Manual & Guides
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Documentation for using the EC Wound Charting system.</p>
                </div>
                <div>
                    <button onclick="startOnboardingTour()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all flex items-center gap-2">
                        <i data-lucide="play-circle" class="w-4 h-4"></i>
                        Start Tour
                    </button>
                </div>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 flex overflow-hidden bg-gray-100 p-6 gap-6">
                <!-- Main Iframe Content (Takes up most space) -->
                <div class="flex-grow bg-white rounded-lg shadow-lg overflow-hidden relative iframe-container">
                    <?php if ($current_guide && $current_guide['src'] !== '#'): // Only show iframe if src is valid ?>
                        <iframe
                                src="<?php echo htmlspecialchars($current_guide['src']); ?>"
                                style="
                        position: absolute;
                        top: -56px; /* Adjust this value to hide the header */
                        left: 0;
                        width: 100%;
                        height: calc(100% + 112px); /* Increased to compensate for top and bottom cropping */
                        border: none;
                        bottom: -56px; /* Adjust this value to hide the footer/controls */
                    "
                                frameborder="0">
                        </iframe>
                    <?php elseif (!$current_guide): ?>
                        <p class="p-8 text-center text-red-600">The selected guide could not be loaded.</p>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-center p-8">
                            <div class="bg-indigo-50 p-6 rounded-full mb-6">
                                <i data-lucide="compass" class="w-16 h-16 text-indigo-600"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome to EC Wound Charting</h2>
                            <p class="text-gray-600 mb-8 max-w-md">
                                Explore our comprehensive guides or take a quick interactive tour to get familiar with the system.
                            </p>
                            
                            <button onclick="startOnboardingTour()" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all flex items-center gap-2">
                                <i data-lucide="play-circle" class="w-5 h-5"></i>
                                Start Interactive Tour
                            </button>

                            <div class="mt-12 grid grid-cols-2 gap-4 w-full max-w-lg">
                                <div class="p-4 border border-gray-200 rounded-lg hover:border-indigo-300 hover:bg-indigo-50 transition-colors cursor-pointer" onclick="document.querySelector('.manual-menu-link').click()">
                                    <i data-lucide="book" class="w-6 h-6 text-indigo-500 mb-2 mx-auto"></i>
                                    <h3 class="font-medium text-gray-900">Browse Guides</h3>
                                </div>
                                <div class="p-4 border border-gray-200 rounded-lg hover:border-indigo-300 hover:bg-indigo-50 transition-colors cursor-pointer" onclick="window.location.href='index.php'">
                                    <i data-lucide="home" class="w-6 h-6 text-indigo-500 mb-2 mx-auto"></i>
                                    <h3 class="font-medium text-gray-900">Go to Dashboard</h3>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Sidebar Menu for Guides -->
                <div class="w-64 flex-shrink-0 bg-white rounded-lg shadow-lg p-4">
                    <div class="mb-4 pb-4 border-b border-gray-100">
                        <button onclick="startOnboardingTour()" class="w-full px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-semibold rounded-lg hover:bg-indigo-100 transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="play-circle" class="w-4 h-4"></i>
                            Start Tour
                        </button>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Available Guides</h2>
                    <nav id="manual-menu-nav" class="manual-menu space-y-1">
                        <?php
                        // Render top-level items first
                        foreach ($guides as $key => $guide) {
                            if (empty($guide['parent'])) { // Check if parent is null or empty
                                $is_active_parent_link = ($key === $current_guide_key); // Is the parent link itself active?
                                $is_parent_of_active_child = ($current_parent_key === $key); // Is it the parent of the active child?
                                $active_class = ($is_active_parent_link || $is_parent_of_active_child) ? 'active' : '';

                                // Check if this item has children
                                $children = [];
                                foreach ($guides as $child_key => $child_guide) {
                                    if (isset($child_guide['parent']) && $child_guide['parent'] === $key) {
                                        $children[$child_key] = $child_guide;
                                    }
                                }
                                $has_children = count($children) > 0;

                                echo "<div class='manual-menu-item {$active_class}'>"; // Wrap in a div

                                // Add data attributes and icon if it has children
                                $toggle_attr = $has_children ? " data-toggle-target='submenu-{$key}'" : "";
                                $toggle_icon = $has_children ? "<i data-lucide='chevron-right' class='submenu-toggle-icon w-4 h-4 " . ($is_parent_of_active_child ? 'open' : '') . "'></i>" : "";
                                // Make parent non-navigable if children exist OR link to its own guide if src is not '#'
                                $href = ($has_children || $guide['src'] === '#') ? "#" : "user_manual.php?guide={$key}";

                                echo "<a href='{$href}' class='manual-menu-link {$active_class}' {$toggle_attr}>"
                                    . htmlspecialchars($guide['title'])
                                    . $toggle_icon
                                    . "</a>";

                                if ($has_children) {
                                    $submenu_open_class = $is_parent_of_active_child ? 'open' : '';
                                    echo "<div id='submenu-{$key}' class='manual-submenu {$submenu_open_class}'>";
                                    foreach ($children as $child_key => $child_guide) {
                                        $is_child_active = ($child_key === $current_guide_key);
                                        $child_active_class = $is_child_active ? 'active' : '';
                                        echo "<a href='user_manual.php?guide={$child_key}' class='{$child_active_class}'>" . htmlspecialchars($child_guide['title']) . "</a>";
                                    }
                                    echo "</div>";
                                }
                                echo "</div>"; // Close manual-menu-item div
                            }
                        }
                        ?>
                    </nav>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuNav = document.getElementById('manual-menu-nav');

            menuNav.addEventListener('click', function(e) {
                // Find the closest link element that might have toggle data
                const toggleLink = e.target.closest('.manual-menu-link[data-toggle-target]');

                if (toggleLink) {
                    e.preventDefault(); // Prevent default link behavior ONLY if it's a toggle
                    const targetId = toggleLink.getAttribute('data-toggle-target');
                    const submenu = document.getElementById(targetId);
                    const icon = toggleLink.querySelector('.submenu-toggle-icon');

                    if (submenu) {
                        const isOpen = submenu.classList.contains('open');

                        // Close all other submenus first
                        menuNav.querySelectorAll('.manual-submenu.open').forEach(openSubmenu => {
                            if (openSubmenu !== submenu) {
                                openSubmenu.style.maxHeight = '0px';
                                openSubmenu.classList.remove('open');
                                const otherIcon = openSubmenu.previousElementSibling?.querySelector('.submenu-toggle-icon'); // Added safe navigation
                                if (otherIcon) {
                                    otherIcon.classList.remove('open');
                                }
                            }
                        });

                        // Toggle the clicked submenu
                        if (isOpen) {
                            submenu.style.maxHeight = '0px'; // Collapse
                            submenu.classList.remove('open');
                            if (icon) icon.classList.remove('open');
                        } else {
                            submenu.style.maxHeight = submenu.scrollHeight + 'px'; // Expand
                            submenu.classList.add('open');
                            if (icon) icon.classList.add('open');
                        }
                    }
                }
                // If the click was on a regular link (not a toggle), allow default navigation
            });

            // Ensure the active submenu is open on page load
            const activeSubmenu = menuNav.querySelector('.manual-submenu.open');
            if (activeSubmenu) {
                activeSubmenu.style.maxHeight = activeSubmenu.scrollHeight + 'px';
            }

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>

<?php
require_once 'templates/footer.php';
?>