<?php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

$patient_name = $_SESSION['portal_patient_name'];
$active_page = 'documents'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents | Patient Portal</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- 1. Navigation Panel (Mobile Header and Sidebar) -->
<?php require_once 'nav_panel.php'; ?>

<!-- 2. Main Page Wrapper -->
<div class="page-wrapper">
    <!-- Nav Panel is sticky/fixed/sidebar depending on viewport -->

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container max-w-screen-lg">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="file-text" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    My Documents & Results
                </h1>
            </div>

            <div id="loading" class="text-center py-12 text-gray-500">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mb-2"></div>
                <p>Loading documents...</p>
            </div>

            <div id="documents-container" class="hidden grid grid-cols-1 gap-4">
                <!-- JS will inject content -->
            </div>

            <p id="no-docs-msg" class="hidden text-center text-gray-500 py-8 italic bg-white rounded-lg border border-gray-200">
                No documents are currently available.
            </p>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();

    document.addEventListener('DOMContentLoaded', async () => {
        const container = document.getElementById('documents-container');
        const loading = document.getElementById('loading');
        const noDocsMsg = document.getElementById('no-docs-msg');

        try {
            const response = await fetch('api/get_documents.php');
            const docs = await response.json();

            loading.classList.add('hidden');

            if (docs.length === 0) {
                noDocsMsg.classList.remove('hidden');
                return;
            }

            container.classList.remove('hidden');

            docs.forEach(doc => {
                const card = document.createElement('div');
                card.className = 'card card-base flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 hover:border-indigo-400 transition';

                // Determine icon based on file type (basic logic)
                let iconName = 'file-text';
                let iconColor = 'text-indigo-600';
                if (doc.file_name.endsWith('.pdf')) {
                    iconName = 'file-text';
                } else if (doc.file_name.match(/\.(jpg|jpeg|png)$/i)) {
                    iconName = 'image';
                    iconColor = 'text-green-600';
                }

                card.innerHTML = `
                        <div class="flex items-center mb-3 sm:mb-0">
                            <div class="p-3 rounded-full bg-indigo-50 ${iconColor} mr-4 flex-shrink-0">
                                <i data-lucide="${iconName}" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-md">${doc.document_type}</h3>
                                <p class="text-sm text-gray-500 truncate max-w-xs">${doc.file_name}</p>
                                <p class="text-xs text-gray-400 mt-1">Uploaded: ${new Date(doc.upload_date).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <a href="../${doc.file_path}" target="_blank" class="btn-primary btn-base w-full sm:w-auto px-4 py-2 text-sm flex items-center justify-center sm:ml-4">
                            <i data-lucide="download" class="w-4 h-4 mr-2"></i> View / Download
                        </a>
                    `;
                container.appendChild(card);
            });

            lucide.createIcons(); // Refresh icons for new elements

        } catch (error) {
            loading.innerHTML = '<p class="text-red-500 font-medium">Unable to load documents. Please check your network connection.</p>';
        }
    });
</script>
</body>
</html>