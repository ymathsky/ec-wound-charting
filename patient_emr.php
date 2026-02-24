<?php
// Filename: ec/patient_emr.php
require_once 'templates/header.php';

// --- Get Patient ID from URL ---
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    echo "<div class='p-8 text-center text-red-600 font-bold'>Invalid Patient ID.</div>";
    require_once 'templates/footer.php';
    exit();
}
?>

    <!-- Include Lucide Icons for UI consistency (used in the new header style) -->
    <!--suppress ALL -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 id="patient-name-header" class="text-3xl font-extrabold text-gray-900 flex items-center gap-2">
                        <i data-lucide="folder-search" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Loading EMR...
                    </h1>
                    <p id="patient-subheader" class="text-sm text-gray-500 mt-1 ml-10">Comprehensive chart and document repository.</p>
                </div>
                <div>
                    <a href="patient_profile.php?id=<?php echo $patient_id; ?>" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Profile
                    </a>
                </div>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6 sm:p-8">
                <!-- Main Content Grid - Stretched layout -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- Left Column: Demographics & Upload (lg:col-span-4) -->
                    <div id="left-column" class="lg:col-span-4 space-y-6">

                        <!-- Patient Demographics Card -->
                        <div id="demographics-container" class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                            <!-- Skeleton Loader -->
                            <div class="p-6 animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                                <div class="space-y-2">
                                    <div class="h-3 bg-gray-100 rounded w-full"></div>
                                    <div class="h-3 bg-gray-100 rounded w-5/6"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Document Card -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                                <i class="fas fa-cloud-upload-alt mr-2 text-indigo-500"></i> Upload Document
                            </h3>

                            <div id="upload-message" class="hidden p-3 mb-4 rounded-md text-sm"></div>

                            <form id="documentUploadForm" class="space-y-4">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <input type="hidden" name="upload_date" value="<?php echo date('Y-m-d'); ?>">

                                <div>
                                    <label for="document_type" class="block text-sm font-medium text-gray-700">Document Type</label>
                                    <select name="document_type" id="document_type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md border">
                                        <option value="General">General/Misc.</option>
                                        <option value="Lab_Result">Lab Result</option>
                                        <option value="Referral">Referral</option>
                                        <option value="Imaging">Imaging Report</option>
                                        <option value="Discharge">Discharge Summary</option>
                                        <option value="Insurance">Insurance Card</option>
                                        <option value="Consent">Consent Form</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="patient_document" class="block text-sm font-medium text-gray-700">Select File</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:bg-gray-50 transition-colors cursor-pointer relative">
                                        <div class="space-y-1 text-center">
                                            <i class="fas fa-file-pdf text-gray-400 text-3xl mb-2"></i>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="patient_document" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none">
                                                    <span>Upload a file</span>
                                                    <input id="patient_document" name="patient_document" type="file" class="sr-only" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PDF, PNG, JPG up to 10MB</p>
                                        </div>
                                        <!-- Filename display -->
                                        <div id="file-name-display" class="absolute bottom-1 left-0 right-0 text-center text-xs font-semibold text-gray-700 truncate px-2"></div>
                                    </div>
                                </div>

                                <button type="submit" id="uploadBtn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                    Upload & Save
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Documents List (lg:col-span-8) -->
                    <div id="right-column" class="lg:col-span-8">
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 min-h-[500px] flex flex-col">
                            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center flex-wrap gap-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <i class="fas fa-folder-open mr-2 text-yellow-500"></i> Saved Documents
                                </h3>

                                <!-- Document Category Filter -->
                                <div class="flex items-center space-x-2">
                                    <label for="doc-filter" class="text-sm text-gray-500"><i class="fas fa-filter"></i> Filter:</label>
                                    <select id="doc-filter" class="block w-40 pl-3 pr-10 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md border">
                                        <option value="All">All Types</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                </div>
                            </div>

                            <div id="documents-list-container" class="p-6 flex-1 overflow-x-auto">
                                <div class="flex justify-center items-center h-32 text-gray-400">
                                    <i class="fas fa-spinner fa-spin text-2xl mr-3"></i> Loading documents...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- DOCUMENT VIEWER MODAL (Updated) -->
    <div id="documentViewerModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="viewer-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeDocumentModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full h-[95vh] max-w-5xl">
                <div class="h-full flex flex-col">
                    <!-- Header -->
                    <div class="flex justify-between items-center bg-indigo-600 text-white p-3 flex-shrink-0">
                        <h3 class="text-lg font-medium" id="viewer-title">Document Viewer</h3>
                        <!-- Download Link (Visible only for non-embedded documents) -->
                        <a id="modal-download-link" href="#" target="_blank" class="text-white hover:text-indigo-200 text-sm font-semibold flex items-center mr-4 hidden">
                            <i class="fas fa-download mr-1"></i> Direct Download
                        </a>
                        <button type="button" onclick="closeDocumentModal()" class="text-white hover:text-indigo-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Iframe Container -->
                    <div class="flex-1 min-h-0 bg-gray-100 relative">
                        <div id="iframe-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 z-10">
                            <i class="fas fa-spinner fa-spin text-4xl text-indigo-500"></i>
                        </div>

                        <!-- Content Viewers -->
                        <iframe id="document-iframe-pdf-image" src="" class="w-full h-full border-0" onload="hideIframeLoading()" style="display: none;"></iframe>
                        <div id="document-unsupported-viewer" class="w-full h-full p-12 text-center flex flex-col items-center justify-center space-y-4" style="display: none;">
                            <i class="fas fa-exclamation-triangle text-red-500 text-6xl"></i>
                            <p class="text-xl font-semibold text-gray-800">Unsupported Document Type</p>
                            <p class="text-gray-600">This file type (`.doc`, `.docx`) cannot be reliably displayed directly in the browser.</p>
                            <p class="text-sm text-gray-500">Please use the "Direct Download" link above to open the file on your local machine.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Import FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <script>
        // Global function used by the iframe onload event
        window.hideIframeLoading = function() {
            document.getElementById('iframe-loading').classList.add('hidden');
        }

        // Global function to open the document viewer (Now handles different types)
        window.openDocumentModal = function(filePath, fileName) {
            const modal = document.getElementById('documentViewerModal');
            const iframe = document.getElementById('document-iframe-pdf-image');
            const unsupportedViewer = document.getElementById('document-unsupported-viewer');
            const title = document.getElementById('viewer-title');
            const loading = document.getElementById('iframe-loading');
            const downloadLink = document.getElementById('modal-download-link');

            // Reset views
            iframe.style.display = 'none';
            unsupportedViewer.style.display = 'none';
            downloadLink.classList.add('hidden');

            title.textContent = fileName;
            loading.classList.remove('hidden');

            const extension = fileName.split('.').pop().toLowerCase();
            let displayPath = filePath;

            // Check for common viewable types (PDFs and Images)
            if (['pdf', 'jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                // Display directly in iframe
                iframe.src = displayPath;
                iframe.style.display = 'block';
                downloadLink.classList.add('hidden'); // PDF/Image links are viewable, no need for separate download button unless requested

            } else if (['doc', 'docx'].includes(extension)) {
                // Type 2: Use Google Docs Viewer for Word documents if possible
                // Note: This relies on the file being publicly accessible, which might not be true for EHR files.
                // A safer, explicit "unsupported" message is often preferred in healthcare contexts.

                // OPTION 1: Google Viewer (requires absolute/public file path)
                // displayPath = `https://docs.google.com/gview?url=${encodeURIComponent(window.location.origin + '/' + filePath)}&embedded=true`;
                // iframe.src = displayPath;
                // iframe.style.display = 'block';
                // downloadLink.href = filePath;
                // downloadLink.classList.remove('hidden');

                // OPTION 2: Explicit Unsupported Message (Safer for internal EHR)
                loading.classList.add('hidden'); // Hide spinner immediately
                unsupportedViewer.style.display = 'flex';
                downloadLink.href = filePath;
                downloadLink.classList.remove('hidden'); // Show download button
                iframe.src = ''; // Clear iframe content
            } else {
                // Default Fallback
                iframe.src = displayPath;
                iframe.style.display = 'block';
                downloadLink.classList.add('hidden');
            }

            modal.classList.remove('hidden');
        };

        window.closeDocumentModal = function() {
            const modal = document.getElementById('documentViewerModal');
            const iframe = document.getElementById('document-iframe-pdf-image');

            // Stop loading the document and hide the modal
            iframe.src = '';
            modal.classList.add('hidden');
        };

        document.addEventListener('DOMContentLoaded', function() {
            const patientId = <?php echo $patient_id; ?>;
            const patientNameHeader = document.getElementById('patient-name-header');
            const patientSubHeader = document.getElementById('patient-subheader');
            const demographicsContainer = document.getElementById('demographics-container');
            const documentsListContainer = document.getElementById('documents-list-container');
            const uploadMessage = document.getElementById('upload-message');
            const docFilter = document.getElementById('doc-filter');

            // Store fetched docs globally to filter without refetching
            let allDocuments = [];

            // --- 1. Init & Fetching ---

            async function init() {
                await Promise.all([
                    fetchPatientDetails(),
                    fetchDocuments()
                ]);
                // Initialize Lucide Icons after dynamic content is loaded
                lucide.createIcons();
            }

            async function fetchPatientDetails() {
                try {
                    const response = await fetch(`api/get_patient_profile_data.php?id=${patientId}`);
                    if (!response.ok) throw new Error('Failed to fetch patient details.');
                    const patientData = await response.json();

                    const p = patientData.details;
                    // Update Header
                    if (patientNameHeader) {
                        patientNameHeader.innerHTML = `
                        <i data-lucide="folder-search" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        ${p.first_name} ${p.last_name}
                    `;
                    }
                    if (patientSubHeader) {
                        patientSubHeader.textContent = `DOB: ${p.date_of_birth} | ID: ${p.patient_code || patientId}`;
                    }

                    renderDemographics(p);
                } catch (error) {
                    console.error("EMR Initialization Error:", error);
                    if (patientNameHeader) {
                        patientNameHeader.innerHTML = `<i data-lucide="folder-search" class="w-7 h-7 mr-3 text-indigo-600"></i> Error Loading EMR`;
                    }
                }
            }

            async function fetchDocuments() {
                try {
                    const response = await fetch(`api/get_documents.php?id=${patientId}`);
                    if (!response.ok) throw new Error('Failed to fetch documents.');
                    const documents = await response.json();

                    allDocuments = Array.isArray(documents) ? documents : [];

                    // Populate Filter Options based on what exists
                    updateFilterOptions(allDocuments);

                    // Render Initial List (All)
                    renderDocumentsTable(allDocuments);

                } catch (error) {
                    documentsListContainer.innerHTML = `<div class="bg-red-50 text-red-600 p-4 rounded text-center">${error.message}</div>`;
                }
            }

            // --- 2. Rendering ---

            function renderDemographics(p) {
                demographicsContainer.innerHTML = `
                <div class="p-5 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-xl font-semibold text-gray-800">Patient Details</h3>
                </div>
                <div class="p-6 space-y-4 text-base text-gray-700"> <!-- Increased font size here -->
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="font-medium text-gray-500 text-sm">DOB</span>
                    <span class="text-gray-900 font-semibold">${p.date_of_birth}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="font-medium text-gray-500 text-sm">Gender</span>
                    <span class="text-gray-900 font-semibold">${p.gender}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="font-medium text-gray-500 text-sm">Contact</span>
                    <span class="text-gray-900 font-semibold">${p.contact_number || 'N/A'}</span>
                    </div>

                    <div class="mt-4 pt-2 border-t border-gray-100">
                    <h4 class="font-bold text-gray-700 mb-1 text-sm uppercase tracking-wider">Medical History</h4>
                    <p class="bg-gray-50 p-2 rounded text-sm leading-relaxed">${p.past_medical_history || 'No history recorded.'}</p>
                    </div>
                    <div class="mt-2">
                    <h4 class="font-bold text-gray-700 mb-1 text-sm uppercase tracking-wider">Allergies</h4>
                    <p class="bg-red-50 text-red-700 p-2 rounded text-sm font-medium">${p.allergies || 'NKDA'}</p>
                    </div>
                    </div>
                    `;
        }

        function updateFilterOptions(docs) {
            const types = new Set(docs.map(d => d.document_type));
            // Keep "All" as first option
            docFilter.innerHTML = '<option value="All">All Types</option>';

            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type.replace('_', ' '); // Clean up formatting (e.g., Lab_Result -> Lab Result)
                docFilter.appendChild(option);
            });
        }

        function renderDocumentsTable(documents) {
            if (documents.length === 0) {
                documentsListContainer.innerHTML = `
                    <div class="text-center py-12">
                    <div class="bg-gray-50 rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-upload text-gray-300 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No documents found</h3>
                    <p class="text-gray-500 text-sm">Upload a file to get started.</p>
                    </div>
                    `;
                return;
            }

            const rows = documents.map(doc => {
                // Determine icon based on file extension or type
                let iconClass = 'fa-file-alt text-gray-500';
                const ext = doc.file_name.split('.').pop().toLowerCase();
                if (ext === 'pdf') iconClass = 'fa-file-pdf text-red-500';
                else if (['jpg', 'jpeg', 'png'].includes(ext)) iconClass = 'fa-file-image text-blue-500';
                else if (['doc', 'docx'].includes(ext)) iconClass = 'fa-file-word text-blue-700';

                return `
                <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                ${doc.document_type.replace('_', ' ')}
                </span>
                </td>
                <td class="px-6 py-4 text-base text-gray-900 font-medium flex items-center">
                <i class="fas ${iconClass} mr-3 text-lg"></i>
                ${doc.file_name}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${new Date(doc.upload_date).toLocaleDateString()}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${doc.uploader_name || 'System'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <!-- UPDATED: Call JavaScript function instead of direct link -->
                <button onclick="openDocumentModal('${doc.file_path}', '${doc.file_name.replace(/'/g, "\\'")}')"
        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
            <i class="fas fa-eye mr-1.5"></i> View
            </button>
            </td>
            </tr>
            `}).join('');

            documentsListContainer.innerHTML = `
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploader</th>
            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            ${rows}
            </tbody>
            </table>
            `;
        }

        // --- 3. Event Listeners ---

        // File Input UX Enhancement
        document.getElementById('patient_document').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            document.getElementById('file-name-display').textContent = fileName;
        });

        // Filter Logic
        docFilter.addEventListener('change', function(e) {
            const selectedType = e.target.value;
            if (selectedType === 'All') {
                renderDocumentsTable(allDocuments);
            } else {
                const filtered = allDocuments.filter(doc => doc.document_type === selectedType);
                renderDocumentsTable(filtered);
            }
        });

        // Upload Handler
        document.getElementById('documentUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            const originalText = uploadBtn.innerHTML;

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Uploading...';

            try {
                const response = await fetch('api/upload_document.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!response.ok) throw new Error(result.message || 'Upload failed.');

                showMessage(result.message, 'success');
                this.reset();
                document.getElementById('file-name-display').textContent = '';

                // Refresh list
                fetchDocuments();

            } catch (error) {
                showMessage(`Error: ${error.message}`, 'error');
                console.error("Document Upload Error:", error);
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = originalText;
            }
        });

        function showMessage(message, type) {
            uploadMessage.textContent = message;
            uploadMessage.className = `mb-4 text-sm p-3 rounded ${type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200'}`;
            uploadMessage.classList.remove('hidden');
            setTimeout(() => uploadMessage.classList.add('hidden'), 5000);
        }

        // Start
        init();
    });
</script>

<?php
require_once 'templates/footer.php';
?>