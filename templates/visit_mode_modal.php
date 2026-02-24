<!-- Visit Mode Selection Modal -->
<div id="visitModeModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all scale-100">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 p-6 text-white">
            <h3 class="text-2xl font-bold flex items-center">
                <i data-lucide="stethoscope" class="w-6 h-6 mr-3"></i>
                Start Patient Visit
            </h3>
            <p class="text-indigo-100 mt-1 text-sm">Choose how you want to conduct this visit.</p>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">
            <!-- Option 1: AI Assistant -->
            <button onclick="selectVisitMode('ai')" class="w-full group relative flex items-center p-4 border-2 border-indigo-100 rounded-xl hover:border-indigo-500 hover:bg-indigo-50 transition-all duration-200 text-left">
                <div class="bg-indigo-100 p-3 rounded-full mr-4 group-hover:bg-indigo-200 transition-colors">
                    <i data-lucide="bot" class="w-6 h-6 text-indigo-600"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-800 group-hover:text-indigo-700">AI Assistant Mode</h4>
                    <p class="text-xs text-gray-500 mt-1">Voice-guided. The AI scribes for you while you talk.</p>
                </div>
                <div class="absolute top-3 right-3">
                    <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">New</span>
                </div>
            </button>

            <!-- Option 2: Dictation Mode -->
            <button onclick="selectVisitMode('dictation')" class="w-full group flex items-center p-4 border-2 border-purple-100 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all duration-200 text-left">
                <div class="bg-purple-100 p-3 rounded-full mr-4 group-hover:bg-purple-200 transition-colors">
                    <i data-lucide="mic" class="w-6 h-6 text-purple-600"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-800 group-hover:text-purple-700">Dictation Mode</h4>
                    <p class="text-xs text-gray-500 mt-1">Narrate the entire visit freely. AI formats it later.</p>
                </div>
            </button>

            <!-- Option 3: Standard Mode -->
            <button onclick="selectVisitMode('standard')" class="w-full group flex items-center p-4 border-2 border-gray-100 rounded-xl hover:border-gray-400 hover:bg-gray-50 transition-all duration-200 text-left">
                <div class="bg-gray-100 p-3 rounded-full mr-4 group-hover:bg-gray-200 transition-colors">
                    <i data-lucide="layout-list" class="w-6 h-6 text-gray-600"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-800">Standard Mode</h4>
                    <p class="text-xs text-gray-500 mt-1">Classic tab-based charting (Vitals, HPI, Wounds).</p>
                </div>
            </button>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 p-4 flex justify-center border-t">
            <button onclick="closeVisitModeModal()" class="text-gray-500 hover:text-gray-700 text-sm font-medium">Cancel</button>
        </div>
    </div>
</div>

<script>
    let currentVisitParams = {};

    function openVisitModeModal(patientId, appointmentId, userId) {
        currentVisitParams = { patientId, appointmentId, userId };
        const modal = document.getElementById('visitModeModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Initialize icons if Lucide is loaded
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeVisitModeModal() {
        const modal = document.getElementById('visitModeModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function selectVisitMode(mode) {
        const { patientId, appointmentId, userId } = currentVisitParams;
        let url = '';
        let title = '';
        let icon = '';

        if (mode === 'ai') {
            url = `visit_ai_assistant.php?patient_id=${patientId}&appointment_id=${appointmentId}&user_id=${userId}&layout=modal`;
            title = 'AI Assistant';
            icon = 'bot';
        } else if (mode === 'dictation') {
            url = `visit_narrative.php?patient_id=${patientId}&appointment_id=${appointmentId}&user_id=${userId}&layout=modal`;
            title = 'Dictation Mode';
            icon = 'mic';
        } else {
            url = `visit_vitals.php?patient_id=${patientId}&appointment_id=${appointmentId}&user_id=${userId}&layout=modal`;
            title = 'Visit Vitals';
            icon = 'heart-pulse';
        }

        // Check if we're in MDI mode
        if (window.parent && window.parent.mdiManager) {
            // Open in new tab via parent
            window.parent.openPageInTab(url, title, icon);
            closeVisitModeModal();
        } else if (window.mdiManager) {
            // We're in the parent MDI shell
            window.openPageInTab(url, title, icon);
            closeVisitModeModal();
        } else {
            // Fallback to regular navigation
            window.location.href = url;
        }
    }
</script>
