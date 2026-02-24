<?php
// Filename: print_order.php
// Purpose: Generate a printable order report (Lab/Imaging)

require_once 'db_connect.php';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'lab_orders'; // lab_orders or imaging_orders

if ($appointment_id <= 0) {
    die("Invalid Appointment ID");
}

// Fetch Data
$sql = "SELECT 
            p.first_name, p.last_name, p.date_of_birth, p.gender, p.patient_code, p.address, p.contact_number as phone_primary,
            a.appointment_date,
            u.full_name as provider_name, u.email as provider_email,
            fac.full_name as facility_name,
            vn.lab_orders, vn.imaging_orders, vn.signature_data, vn.signed_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN users fac ON p.facility_id = fac.user_id
        LEFT JOIN visit_notes vn ON a.appointment_id = vn.appointment_id
        WHERE a.appointment_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Order not found.");
}

$order_content = ($type === 'imaging_orders') ? $data['imaging_orders'] : $data['lab_orders'];
$order_title = ($type === 'imaging_orders') ? "Imaging Order" : "Lab Order";
$barcode_val = strtoupper(substr(md5($appointment_id . $type), 0, 8));

// Parse Orders from HTML
$orders = [];
if (!empty($order_content)) {
    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);
    // Load HTML with UTF-8 hack
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $order_content);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//div[contains(@class, 'order-entry')]");

    foreach ($nodes as $node) {
        $orders[] = [
            'code' => $node->getAttribute('data-code'),
            'name' => $node->getAttribute('data-name'),
            'dx' => $node->getAttribute('data-dx'),
            'stat' => $node->getAttribute('data-stat'),
            'specimen' => $node->getAttribute('data-specimen')
        ];
    }
}

// If no structured orders found, try to just show raw text or handle legacy
if (empty($orders) && !empty($order_content)) {
    // Fallback for legacy text
    $orders[] = [
        'code' => '',
        'name' => strip_tags($order_content),
        'dx' => '',
        'stat' => 'No',
        'specimen' => ''
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $order_title; ?> - <?php echo $data['first_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
    </style>
</head>
<body class="bg-gray-100 p-8" onload="window.print()">

    <div class="max-w-4xl mx-auto bg-white p-8 shadow-lg min-h-[11in]">
        
        <!-- Header -->
        <div class="flex justify-between items-start border-b-2 border-gray-800 pb-4 mb-6">
            <div>
                <div class="text-sm text-gray-500 mb-1"><?php echo date('m/d/y, g:i A'); ?></div>
                <h1 class="text-xl font-bold uppercase">Vendor Order</h1>
                <div class="mt-2">
                    <svg id="barcode"></svg>
                </div>
                <div class="text-xs mt-1">
                    <strong>ORDER #:</strong> <?php echo $barcode_val; ?><br>
                    <strong>REQUESTING PROVIDER:</strong> <?php echo strtoupper($data['provider_name']); ?><br>
                    <strong>SENT:</strong> <?php echo date('m/d/Y h:i a'); ?>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-gray-700">BEYOND WOUND CARE INC</h2>
                <div class="text-xs text-gray-500">VENDOR NAME: Other - HME</div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            
            <!-- Patient Info -->
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <h3 class="font-bold border-b border-gray-300 mb-2 pb-1">Patient Information</h3>
                <div class="grid grid-cols-[100px_1fr] gap-y-1 text-xs">
                    <div class="font-bold text-gray-600">NAME:</div>
                    <div><?php echo $data['first_name'] . ' ' . $data['last_name']; ?></div>
                    
                    <div class="font-bold text-gray-600">DOB:</div>
                    <div><?php echo date('m/d/Y', strtotime($data['date_of_birth'])); ?></div>
                    
                    <div class="font-bold text-gray-600">GENDER:</div>
                    <div><?php echo $data['gender']; ?></div>
                    
                    <div class="font-bold text-gray-600">ID:</div>
                    <div><?php echo $data['patient_code']; ?></div>
                    
                    <div class="font-bold text-gray-600">PHONE:</div>
                    <div><?php echo $data['phone_primary']; ?></div>
                    
                    <div class="font-bold text-gray-600">ADDRESS:</div>
                    <div>
                        <?php echo nl2br(htmlspecialchars($data['address'])); ?>
                    </div>
                    
                    <div class="font-bold text-gray-600">PAYMENT:</div>
                    <div>Patient - Cash</div>
                </div>
            </div>

            <!-- Provider Info -->
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <h3 class="font-bold border-b border-gray-300 mb-2 pb-1">Requesting Provider Information</h3>
                <div class="grid grid-cols-[100px_1fr] gap-y-1 text-xs">
                    <div class="font-bold text-gray-600">PRACTICE:</div>
                    <div><?php echo $data['facility_name'] ?: 'BEYOND WOUND CARE INC'; ?></div>
                    
                    <div class="font-bold text-gray-600">PROVIDER:</div>
                    <div><?php echo strtoupper($data['provider_name']); ?></div>
                    
                    <div class="font-bold text-gray-600">PHONE:</div>
                    <div><?php echo '8478738693'; ?></div>
                    
                    <div class="font-bold text-gray-600">FAX:</div>
                    <div><?php echo '8478738486'; ?></div>
                    
                    <div class="font-bold text-gray-600">ADDRESS:</div>
                    <div>
                        1340 REMINGTON RD<br>
                        SCHAUMBURG, IL 60173
                    </div>
                </div>
            </div>
        </div>

        <!-- Guarantor (Placeholder) -->
        <div class="mb-8">
            <h3 class="font-bold border-b border-gray-300 mb-2 pb-1 text-xs">Responsible Party/Guarantor Information</h3>
            <div class="grid grid-cols-[100px_1fr] gap-y-1 text-xs">
                <div class="font-bold text-gray-600">NAME:</div>
                <div><?php echo $data['first_name'] . ' ' . $data['last_name']; ?></div>
                <div class="font-bold text-gray-600">RELATION:</div>
                <div>Self</div>
            </div>
        </div>

        <!-- Order Table -->
        <table class="w-full border-collapse border border-gray-300 text-xs mb-8">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 p-2 text-left w-24">CODE</th>
                    <th class="border border-gray-300 p-2 text-left">TEST NAME</th>
                    <th class="border border-gray-300 p-2 text-left w-16">STAT</th>
                    <th class="border border-gray-300 p-2 text-left w-32">SPECIMEN</th>
                    <th class="border border-gray-300 p-2 text-left w-24">DX</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($order['code']); ?></td>
                    <td class="border border-gray-300 p-2 font-bold"><?php echo htmlspecialchars($order['name']); ?></td>
                    <td class="border border-gray-300 p-2"><?php echo $order['stat'] === 'Yes' || $order['stat'] === 'true' ? 'Yes' : 'No'; ?></td>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($order['specimen']); ?></td>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($order['dx']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="5" class="border border-gray-300 p-4 text-center text-gray-500 italic">No orders found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature -->
        <div class="mt-12 pt-4 border-t border-gray-300 text-xs">
            <div class="mb-2">Electronically Signed By: <span class="font-bold"><?php echo strtoupper($data['provider_name']); ?></span></div>
            <?php if ($data['signature_data']): ?>
                <img src="<?php echo $data['signature_data']; ?>" alt="Signature" class="h-12 opacity-80">
            <?php endif; ?>
        </div>

    </div>

    <script>
        JsBarcode("#barcode", "<?php echo $barcode_val; ?>", {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: true,
            fontSize: 12
        });
    </script>
</body>
</html>