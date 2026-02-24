<?php
// Filename: ec/patient_orders.php
require_once 'templates/header.php';
require_once 'db_connect.php';

// Ensure patient ID is present
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="flex h-screen bg-gray-100 items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md text-center">
                <h2 class="text-2xl font-bold text-red-600 mb-4">Error: Missing Patient ID</h2>
                <p class="text-gray-600 mb-6">Please select a patient from the patient list.</p>
                <a href="view_patients.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Go to Patient List</a>
            </div>
          </div>';
    require_once 'templates/footer.php';
    exit;
}

$patient_id = intval($_GET['id']);

// Fetch Wounds for the Dropdown (Server-side pre-fetch for performance)
$wounds = [];
$w_stmt = $conn->prepare("SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ? AND status = 'Active'");
if ($w_stmt) {
    $w_stmt->bind_param("i", $patient_id);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    while($row = $w_res->fetch_assoc()) {
        $wounds[] = $row;
    }
}
?>
    <!-- Include Lucide Icons for UI consistency -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- Include FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <div class="flex h-screen bg-gray-100 font-sans">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE (Matching Patient Chart History) -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 id="patient-name-header" class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="beaker" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        <span id="header-title-text">Labs & Orders</span>
                    </h1>
                    <p id="patient-subheader" class="text-sm text-gray-500 mt-1 ml-10">Manage diagnostics and treatment orders for this patient.</p>
                </div>
                <!-- Header Actions -->
                <div class="flex gap-3">
                    <a href="patient_profile.php?id=<?php echo $patient_id; ?>" class="bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-200 hover:text-gray-900 flex items-center shadow-sm transition-all text-sm font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Profile
                    </a>
                    <button onclick="openOrderModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 flex items-center shadow-md transition-all text-sm font-medium transform hover:-translate-y-0.5">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i> New Order
                    </button>
                </div>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">

                <!-- Filter Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                        <li class="mr-2">
                            <button onclick="filterOrders('all')" class="tab-btn inline-block p-4 text-indigo-600 border-b-2 border-indigo-600 rounded-t-lg active focus:outline-none transition-colors" id="tab-all">All Orders</button>
                        </li>
                        <li class="mr-2">
                            <button onclick="filterOrders('pending')" class="tab-btn inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 focus:outline-none transition-colors" id="tab-pending">Pending / Active</button>
                        </li>
                        <li class="mr-2">
                            <button onclick="filterOrders('completed')" class="tab-btn inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 focus:outline-none transition-colors" id="tab-completed">Results Received</button>
                        </li>
                    </ul>
                </div>

                <!-- Orders Table Container -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Date / Ordered By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wound Link</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="orders-table-body">
                            <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div id="loading-spinner" class="p-12 text-center text-gray-500">
                        <i data-lucide="loader" class="w-8 h-8 mx-auto animate-spin mb-2 text-indigo-500"></i>
                        <p class="text-sm font-medium">Loading orders data...</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Order Modal -->
    <div id="createOrderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md transform transition-all scale-95 opacity-0" id="createOrderModalContent">
            <div class="flex justify-between items-center mb-5 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="file-plus" class="w-5 h-5 mr-2 text-indigo-600"></i>
                    Create New Order
                </h3>
                <button onclick="closeOrderModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form id="newOrderForm">
                <!-- CRITICAL FIX: Ensure patient_id is populated -->
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="action" value="create_order">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Order Name <span class="text-red-500">*</span></label>
                    <input type="text" id="orderNameInput" name="order_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border transition-shadow" placeholder="e.g. WBC, Culture, X-Ray" required oninput="detectOrderType(this.value)">

                    <!-- Quick Suggestions -->
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span onclick="fillOrderName('Wound Culture & Sensitivity')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">Wound Culture</span>
                        <span onclick="fillOrderName('CBC with Diff')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">CBC</span>
                        <span onclick="fillOrderName('WBC')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">WBC</span>
                        <span onclick="fillOrderName('X-Ray Left Foot')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">X-Ray</span>
                        <span onclick="fillOrderName('MRI Lower Extremity')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">MRI</span>
                        <span onclick="fillOrderName('Dressing Supplies')" class="cursor-pointer px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors font-medium">Supplies</span>
                    </div>
                </div>

                <div class="mb-4 relative">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Order Type <span class="text-red-500">*</span></label>
                    <select name="order_type" id="orderTypeSelect" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border bg-gray-50 transition-colors">
                        <option value="Lab">Lab (Blood/Urine/Swab)</option>
                        <option value="Imaging">Imaging (X-Ray/MRI)</option>
                        <option value="Consult">Referral/Consult</option>
                        <option value="Treatment">Treatment</option>
                        <option value="Supply">DME / Supplies</option>
                        <option value="Other">Other</option>
                    </select>
                    <p class="text-xs text-green-600 mt-1 font-medium hidden flex items-center" id="type-hint">
                        <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i> Type auto-selected
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Specific Wound (Optional)</label>
                    <select name="wound_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border">
                        <option value="">-- General Order / No Specific Wound --</option>
                        <?php foreach($wounds as $w): ?>
                            <option value="<?php echo $w['wound_id']; ?>">
                                <?php echo htmlspecialchars($w['location'] . " (" . $w['wound_type'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Priority</label>
                    <select name="priority" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border">
                        <option value="Routine">Routine</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Stat">STAT</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                    <button type="button" onclick="closeOrderModal()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 font-medium text-sm transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 font-medium text-sm shadow-sm transition-colors flex items-center">
                        <span class="btn-text">Create Order</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Result Modal -->
    <div id="resultModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md transform transition-all scale-95 opacity-0" id="resultModalContent">
            <div class="flex justify-between items-center mb-5 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="upload-cloud" class="w-5 h-5 mr-2 text-indigo-600"></i>
                    Upload Results
                </h3>
                <button onclick="closeResultModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form id="resultForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_result">
                <input type="hidden" name="order_id" id="result_order_id">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Result File (PDF/Image)</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:bg-gray-50 transition-colors cursor-pointer relative">
                        <div class="space-y-1 text-center">
                            <i data-lucide="file-up" class="mx-auto h-12 w-12 text-gray-400"></i>
                            <div class="flex text-sm text-gray-600">
                                <label for="result_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                    <span>Upload a file</span>
                                    <input id="result_file" name="result_file" type="file" class="sr-only" required>
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PDF, PNG, JPG up to 10MB</p>
                            <p id="file-name-display" class="text-sm font-semibold text-gray-800 mt-2 hidden"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Result Notes / Values</label>
                    <textarea name="result_notes" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border" rows="3" placeholder="e.g. WBC: 11.5, Growth: Staph Aureus"></textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                    <button type="button" onclick="closeResultModal()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none font-medium text-sm transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 font-medium text-sm shadow-sm transition-colors flex items-center">
                        <span class="btn-text">Save Results</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- NEW: View Result Modal with iFrame -->
    <div id="viewResultModal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden transform transition-all scale-95 opacity-0" id="viewResultModalContent">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 mr-2 text-indigo-600"></i>
                    View Result Document
                </h3>
                <div class="flex gap-3 items-center">
                    <a id="downloadResultBtn" href="#" download class="text-indigo-600 hover:text-indigo-800 font-semibold text-sm flex items-center transition-colors bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded border border-indigo-200">
                        <i data-lucide="download" class="w-4 h-4 mr-1.5"></i> Download
                    </a>
                    <button onclick="closeViewResultModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors p-1 hover:bg-gray-100 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body (iFrame) -->
            <div class="flex-1 bg-gray-100 relative">
                <div class="absolute inset-0 flex items-center justify-center text-gray-400" id="iframe-loader">
                    <i data-lucide="loader" class="w-8 h-8 animate-spin"></i>
                </div>
                <iframe id="resultFrame" src="" class="w-full h-full border-none relative z-10" onload="$('#iframe-loader').hide()"></iframe>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Parse the ID carefully
        const patientId = parseInt("<?php echo $patient_id; ?>", 10);
        let allOrders = [];

        // --- ORDER ENCODING MAP ---
        const orderEncodings = {
            'Lab': ['wbc', 'cbc', 'cmp', 'bmp', 'culture', 'swab', 'blood', 'urine', 'test', 'panel', 'a1c', 'albumin', 'esr', 'crp'],
            'Imaging': ['x-ray', 'xray', 'mri', 'ct', 'scan', 'ultrasound', 'doppler', 'bone scan'],
            'Consult': ['referral', 'consult', 'pt', 'ot', 'nutrition', 'dietician', 'podiatry', 'vascular', 'infectious disease'],
            'Supply': ['dressing', 'supply', 'gauze', 'tape', 'bandage', 'foam', 'hydrogel', 'alginate', 'boot', 'offloading'],
            'Treatment': ['debridement', 'npwt', 'vac', 'compression']
        };

        function detectOrderType(inputName) {
            const name = inputName.toLowerCase();
            let detectedType = null;

            for (const [type, keywords] of Object.entries(orderEncodings)) {
                for (const keyword of keywords) {
                    if (name.includes(keyword)) {
                        detectedType = type;
                        break;
                    }
                }
                if (detectedType) break;
            }

            if (detectedType) {
                const currentVal = $('#orderTypeSelect').val();
                if (currentVal !== detectedType) {
                    $('#orderTypeSelect').val(detectedType);
                    $('#type-hint').removeClass('hidden').fadeIn().delay(2000).fadeOut();
                    $('#orderTypeSelect').addClass('bg-yellow-50 ring-2 ring-yellow-200 transition duration-500');
                    setTimeout(() => $('#orderTypeSelect').removeClass('bg-yellow-50 ring-2 ring-yellow-200'), 800);
                }
            }
        }

        // --- Initialize ---
        $(document).ready(function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // File Input UX
            $('#result_file').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $('#file-name-display').text(fileName).removeClass('hidden');
                } else {
                    $('#file-name-display').addClass('hidden');
                }
            });

            // --- Header Name Logic ---
            // Checks invalid IDs first
            if (!patientId || isNaN(patientId)) {
                $('#orders-table-body').html('<tr><td colspan="6" class="px-6 py-8 text-center text-red-500">Error: Valid Patient ID is required.</td></tr>');
                $('#loading-spinner').hide();
                return;
            }

            if (patientId > 0) {
                // Updated API call to use the new specialized endpoint
                $.post('api/get_patient_details_for_labs.php', { patient_id: patientId }, function(response) {
                    if(response.success && response.data && response.data.first_name) {
                        // Set the Title: "John Doe's Labs & Orders"
                        $('#header-title-text').text(response.data.first_name + ' ' + response.data.last_name + '\'s Labs & Orders');
                    } else {
                        $('#header-title-text').text('Patient Labs & Orders');
                    }
                }, 'json').fail(function() {
                    $('#header-title-text').text('Patient Labs & Orders');
                });
            } else {
                $('#header-title-text').text('Labs & Orders');
            }

            loadOrders();

            // --- Form Handlers ---

            // Create Order
            $('#newOrderForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const btnText = btn.find('.btn-text');
                const originalText = btnText.text();

                btn.prop('disabled', true);
                btnText.html('<i data-lucide="loader" class="w-4 h-4 animate-spin mr-2 inline"></i> Saving...');
                if (typeof lucide !== 'undefined') lucide.createIcons();

                $.post('api/manage_order.php', $(this).serialize(), function(res) {
                    btn.prop('disabled', false);
                    btnText.text(originalText);

                    if(res.success) {
                        closeOrderModal();
                        $('#newOrderForm')[0].reset();
                        loadOrders();
                    } else {
                        alert('Error: ' + res.message);
                    }
                }, 'json').fail(function() {
                    btn.prop('disabled', false);
                    btnText.text(originalText);
                    alert('Server error occurred.');
                });
            });

            // Upload Results
            $('#resultForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const btnText = btn.find('.btn-text');
                const originalText = btnText.text();

                btn.prop('disabled', true);
                btnText.html('<i data-lucide="loader" class="w-4 h-4 animate-spin mr-2 inline"></i> Uploading...');
                if (typeof lucide !== 'undefined') lucide.createIcons();

                const formData = new FormData(this);
                $.ajax({
                    url: 'api/manage_order.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        btn.prop('disabled', false);
                        btnText.text(originalText);
                        if(res.success) {
                            closeResultModal();
                            $('#resultForm')[0].reset();
                            $('#file-name-display').addClass('hidden').text('');
                            loadOrders();
                        } else {
                            alert('Error: ' + res.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false);
                        btnText.text(originalText);
                        alert('Upload failed.');
                    }
                });
            });
        });

        function loadOrders() {
            $('#loading-spinner').removeClass('hidden');
            $('#orders-table-body').empty();

            $.post('api/manage_order.php', { action: 'get_orders', patient_id: patientId }, function(res) {
                $('#loading-spinner').addClass('hidden');
                if(res.success) {
                    allOrders = res.data;
                    renderOrders(allOrders);
                } else {
                    $('#orders-table-body').html('<tr><td colspan="6" class="px-6 py-8 text-center text-red-500 bg-red-50 rounded-lg m-4">Error loading orders: ' + (res.message || 'Unknown error') + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#loading-spinner').addClass('hidden');
                $('#orders-table-body').html('<tr><td colspan="6" class="px-6 py-8 text-center text-red-500 bg-red-50 rounded-lg m-4">Connection error. Please try again later.</td></tr>');
            });
        }

        function renderOrders(orders) {
            const tbody = $('#orders-table-body');
            tbody.empty();

            if(!orders || orders.length === 0) {
                tbody.append('<tr><td colspan="6" class="px-6 py-8 text-center text-red-500 bg-red-50 rounded-lg m-4"><div class="flex flex-col items-center justify-center w-full pt-4"><i data-lucide="clipboard-list" class="w-10 h-10 mb-2 opacity-50"></i>No orders found for this patient.</div></td></tr>');
                // Force Lucide render on the appended icon immediately
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }

            orders.forEach(order => {
                let statusColor = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                let statusIcon = 'clock';

                if(order.status === 'Results Received') { statusColor = 'bg-blue-100 text-blue-800 border-blue-200'; statusIcon = 'file-check'; }
                if(order.status === 'Reviewed') { statusColor = 'bg-green-100 text-green-800 border-green-200'; statusIcon = 'check-circle'; }
                if(order.status === 'Pending') { statusColor = 'bg-orange-100 text-orange-800 border-orange-200'; statusIcon = 'hourglass'; }
                if(order.status === 'Cancelled') { statusColor = 'bg-red-100 text-red-800 border-red-200'; statusIcon = 'ban'; }

                let priorityColor = 'text-gray-600';
                let priorityBadge = '';
                if(order.priority === 'Stat') {
                    priorityColor = 'text-red-600 font-bold';
                    priorityBadge = '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800 animate-pulse">STAT</span>';
                }
                if(order.priority === 'Urgent') priorityColor = 'text-orange-600 font-semibold';

                let actionBtn = '';
                if(order.status === 'Ordered' || order.status === 'Pending') {
                    actionBtn = `<div class="flex space-x-2 justify-end">
                <button onclick="openResultModal(${order.order_id})" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold border border-indigo-200 hover:border-indigo-400 bg-indigo-50 px-2.5 py-1.5 rounded transition-colors shadow-sm flex items-center"><i data-lucide="upload" class="w-3 h-3 mr-1.5"></i> Upload</button>`;

                    if(order.status === 'Ordered') {
                        actionBtn += `<button onclick="updateStatus(${order.order_id}, 'Pending')" class="text-orange-600 hover:text-orange-900 text-xs font-semibold border border-orange-200 hover:border-orange-400 bg-orange-50 px-2.5 py-1.5 rounded transition-colors shadow-sm flex items-center" title="Mark as Specimen Collected"><i data-lucide="test-tube" class="w-3 h-3 mr-1.5"></i> Collected</button>`;
                    }
                    actionBtn += '</div>';
                } else if (order.result_document_path) {
                    // View Result Button
                    const safePath = order.result_document_path.replace(/'/g, "\\'");
                    actionBtn = `<div class="flex justify-end"><button onclick="viewResult('${safePath}')" class="text-blue-600 hover:text-blue-800 flex items-center text-xs font-semibold border border-blue-200 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded transition-colors shadow-sm">
                <i data-lucide="eye" class="w-3 h-3 mr-1.5"></i> View Result
            </button></div>`;
                } else if (order.status === 'Results Received') {
                    actionBtn = `<div class="flex justify-end"><button onclick="openResultModal(${order.order_id})" class="text-gray-600 hover:text-gray-900 text-xs border border-gray-300 hover:bg-gray-50 px-2.5 py-1.5 rounded transition-colors">Edit Notes</button></div>`;
                }

                // Format date safely
                const dateObj = new Date(order.created_at);
                const dateStr = dateObj.toLocaleDateString();
                const timeStr = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const orderer = order.ordered_by ? `<div class="text-xs text-gray-400 mt-1 flex items-center"><i data-lucide="user" class="w-3 h-3 mr-1"></i> ${order.ordered_by}</div>` : '';

                // Wound info - Clickable Link
                const woundInfo = order.wound_location ?
                    `<a href="patient_profile.php?id=${order.patient_id}" target="_blank" class="group inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-100 hover:border-blue-300 transition-all shadow-sm" title="View in Patient Profile">
                <i data-lucide="target" class="w-3 h-3 mr-1.5 text-blue-500"></i> ${order.wound_location} <span class="text-blue-300 mx-1">|</span> ${order.wound_type_desc || 'Wound'}
            </a>` :
                    '<span class="text-gray-400 text-xs italic flex items-center"><i data-lucide="globe" class="w-3 h-3 mr-1"></i> General Order</span>';

                const row = `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="font-semibold text-gray-900">${dateStr}</div>
                    <div class="text-xs text-gray-400">${timeStr}</div>
                    ${orderer}
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm font-bold text-gray-900">${order.order_name}</div>
                    <div class="text-xs text-gray-500 mt-1 inline-block px-2 py-0.5 rounded bg-gray-100 border border-gray-200 font-mono">${order.order_type}</div>
                    ${order.result_notes ? `<div class="mt-2 text-xs text-gray-600 bg-yellow-50 p-2 rounded border border-yellow-100"><span class="font-bold text-yellow-700">Notes:</span> ${order.result_notes}</div>` : ''}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${woundInfo}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm ${priorityColor}">
                    ${order.priority} ${priorityBadge}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 inline-flex items-center text-xs leading-4 font-semibold rounded-full ${statusColor} border">
                        <i data-lucide="${statusIcon}" class="w-3 h-3 mr-1.5"></i>
                        ${order.status}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    ${actionBtn}
                </td>
            </tr>
        `;
                tbody.append(row);
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function updateStatus(orderId, newStatus) {
            if(!confirm('Are you sure you want to mark this order as ' + newStatus + '?')) return;

            $.post('api/manage_order.php', {
                action: 'update_status',
                order_id: orderId,
                status: newStatus
            }, function(res) {
                if(res.success) {
                    loadOrders();
                } else {
                    alert('Error: ' + res.message);
                }
            }, 'json');
        }

        function filterOrders(type) {
            // Update UI state
            $('.tab-btn').removeClass('text-indigo-600 border-indigo-600 active bg-indigo-50').addClass('border-transparent hover:text-gray-600 text-gray-500');
            $(`#tab-${type}`).addClass('text-indigo-600 border-indigo-600 active bg-indigo-50').removeClass('border-transparent hover:text-gray-600 text-gray-500');

            if(type === 'all') {
                renderOrders(allOrders);
            } else if (type === 'pending') {
                renderOrders(allOrders.filter(o => o.status === 'Ordered' || o.status === 'Pending'));
            } else if (type === 'completed') {
                renderOrders(allOrders.filter(o => o.status === 'Results Received' || o.status === 'Reviewed'));
            }
        }

        // Modal Functions with Transitions
        function openOrderModal() {
            $('#createOrderModal').removeClass('hidden').addClass('flex');
            // Small timeout to allow display:flex to apply before opacity transition
            setTimeout(() => {
                $('#createOrderModalContent').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
            $('#orderNameInput').focus();
        }

        function closeOrderModal() {
            $('#createOrderModalContent').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => {
                $('#createOrderModal').addClass('hidden').removeClass('flex');
            }, 300);
        }

        function fillOrderName(name) {
            $('input[name="order_name"]').val(name);
            detectOrderType(name);
        }

        function openResultModal(id) {
            $('#result_order_id').val(id);
            $('#resultModal').removeClass('hidden').addClass('flex');
            setTimeout(() => {
                $('#resultModalContent').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
        }

        function closeResultModal() {
            $('#resultModalContent').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => {
                $('#resultModal').addClass('hidden').removeClass('flex');
            }, 300);
        }

        function viewResult(url) {
            $('#resultFrame').attr('src', url);
            $('#downloadResultBtn').attr('href', url);

            $('#viewResultModal').removeClass('hidden').addClass('flex');
            setTimeout(() => {
                $('#viewResultModalContent').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
        }

        function closeViewResultModal() {
            $('#viewResultModalContent').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => {
                $('#viewResultModal').addClass('hidden').removeClass('flex');
                $('#resultFrame').attr('src', '');
            }, 300);
        }
    </script>

<?php require_once 'templates/footer.php'; ?>