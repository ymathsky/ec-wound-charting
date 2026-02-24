/**
 * Smart Command Logic for Entire Visit Workflow
 * Parses natural language commands to navigate and fill form fields.
 * Supports: Vitals, HPI, Wounds, Diagnosis, Medications, Procedures, Notes.
 */

window.SmartCommandParser = class SmartCommandParser {
    constructor() {
        this.mode = this.detectMode();
        this.commandInput = document.getElementById('smart_command_input');
        this.executeBtn = document.getElementById('execute_command_btn');
        this.micBtn = document.getElementById('smart_mic_btn');
        this.feedbackArea = document.getElementById('command_feedback');
        
        this.recognition = null;
        this.isListening = false;
        this.synth = window.speechSynthesis;
        this.isCloudMode = false;
        this.mediaRecorder = null;
        this.audioChunks = [];

        // Global Context (Patient/Appointment IDs)
        this.context = this.getContextFromUrl();

        this.init();
    }

    detectMode() {
        // Check for explicit context first (New AI Assistant Mode)
        if (window.visitContext && window.visitContext.mode) {
            return window.visitContext.mode;
        }

        const path = window.location.pathname;
        if (path.includes('visit_vitals')) return 'vitals';
        if (path.includes('visit_hpi')) return 'hpi';
        if (path.includes('visit_wounds') || path.includes('wound_assessment')) return 'wound';
        if (path.includes('visit_diagnosis')) return 'diagnosis';
        if (path.includes('visit_medications')) return 'medications';
        if (path.includes('visit_procedure')) return 'procedure';
        if (path.includes('visit_notes')) return 'notes';
        if (path.includes('visit_summary')) return 'summary';
        return 'global';
    }

    getContextFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return {
            patient_id: params.get('patient_id') || window.patientId || 0,
            appointment_id: params.get('appointment_id') || window.appointmentId || 0,
            user_id: params.get('user_id') || window.userId || 0
        };
    }

    init() {
        // AI Assistant Mode Initialization
        if (this.mode === 'ai_assistant') {
            this.initAIAssistantUI();
            return;
        }

        // If UI elements don't exist (e.g. on pages other than wound_assessment), inject the global floating widget
        if (!this.commandInput) {
            this.injectGlobalUI();
        }

        // Re-bind elements after injection
        this.commandInput = document.getElementById('smart_command_input');
        this.executeBtn = document.getElementById('execute_command_btn');
        this.micBtn = document.getElementById('smart_mic_btn');
        this.feedbackArea = document.getElementById('command_feedback');

        if (this.executeBtn) {
            this.executeBtn.addEventListener('click', () => this.processCommand());
        }
        
        if (this.commandInput) {
            this.commandInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.processCommand();
                }
            });
        }

        if (this.micBtn) {
            this.initSpeechRecognition();
            this.micBtn.addEventListener('click', () => this.toggleDictation());
        }

        const cloudToggle = document.getElementById('cloud_mode_toggle');
        if (cloudToggle) {
            cloudToggle.addEventListener('change', (e) => {
                this.isCloudMode = e.target.checked;
                if (this.isListening) {
                    this.toggleDictation(); // Stop current
                    setTimeout(() => this.toggleDictation(), 500); // Restart in new mode
                }
            });
        }

        // Initialize Draggable Logic for Smart Command Menu
        this.initDraggable();
    }

    initAIAssistantUI() {
        this.commandInput = document.getElementById('user-input');
        this.executeBtn = document.getElementById('send-btn');
        this.micBtn = document.getElementById('mic-toggle-btn');
        this.chatContainer = document.getElementById('ai-chat-container');
        this.liveNoteContainer = document.getElementById('live-note-content');
        this.statusIndicator = document.getElementById('status-indicator');

        if (this.executeBtn) {
            this.executeBtn.addEventListener('click', () => this.processCommand());
        }
        
        if (this.commandInput) {
            this.commandInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.processCommand();
                    // Reset height if it's a textarea
                    if (this.commandInput.style.height) {
                        setTimeout(() => { this.commandInput.style.height = '44px'; }, 50);
                    }
                }
            });
        }

        if (this.micBtn) {
            this.initSpeechRecognition();
            this.micBtn.addEventListener('click', () => this.toggleDictation());
        }

        const cloudToggle = document.getElementById('cloud_mode_toggle');
        if (cloudToggle) {
            this.isCloudMode = cloudToggle.checked;
            console.log("Cloud toggle initialized. State:", this.isCloudMode);
            
            cloudToggle.addEventListener('change', (e) => {
                this.isCloudMode = e.target.checked;
                console.log("Cloud mode changed to:", this.isCloudMode);
                
                if (this.isListening) {
                    this.toggleDictation(); // Stop current
                    setTimeout(() => this.toggleDictation(), 500); // Restart in new mode
                }
            });
        } else {
            console.warn("Cloud toggle element not found.");
        }
    }

    injectGlobalUI() {
        // Don't inject if we are on wound_assessment (it has its own)
        if (document.getElementById('smart-command-container')) return;
        
        // Don't inject on chat page
        if (window.location.pathname.includes('chat.php')) return;

        const html = `
        <div id="smart-command-container" class="fixed bottom-24 right-6 bg-white border border-blue-200 shadow-2xl rounded-xl p-4 z-50 w-80 flex flex-col items-center gap-3 transition-all hidden">
            <div id="smart-drag-handle" class="flex items-center justify-between w-full border-b pb-2 cursor-move select-none" title="Drag to move">
                <h3 class="font-bold text-blue-900 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 mr-2 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    Smart Voice Entry
                </h3>
                <button id="closeSmartBtn" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
            </div>
            
            <div class="flex flex-col items-center justify-center text-center w-full">
                <button type="button" id="smart_mic_btn" class="group relative bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300 mb-3">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                </button>

                <div class="flex items-center justify-center gap-2 mb-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="cloud_mode_toggle" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-2 text-xs font-medium text-gray-700">Cloud Mode</span>
                    </label>
                </div>

                <p class="text-xs text-gray-500 mb-2">Say: <span class="text-blue-700 italic">"Go to Vitals"</span>, <span class="text-blue-700 italic">"BP 120 over 80"</span>, or <span class="text-blue-700 italic">"Add Wound"</span></p>

                <div class="w-full relative">
                    <input type="text" id="smart_command_input" class="w-full text-center border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 py-2 px-3 text-sm bg-gray-50" placeholder="Transcript...">
                    <button type="button" id="execute_command_btn" class="absolute right-1 top-1 bottom-1 bg-white border border-gray-200 hover:bg-gray-100 text-gray-600 rounded px-2 text-xs font-bold transition">Apply</button>
                </div>
                <div id="command_feedback" class="hidden mt-2 font-medium text-xs text-green-600"></div>
            </div>
        </div>
        
        <!-- Floating FAB to reopen -->
        <button id="fab-smart-voice" class="fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-3 shadow-lg z-40 transition-transform transform hover:scale-110" title="Open Smart Voice">
             <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
        </button>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    injectModalUI() {
        if (document.getElementById('universal-page-modal')) return;
        
        const html = `
        <div id="universal-page-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[60] p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden relative">
                <div class="flex justify-between items-center p-4 border-b bg-gray-50">
                    <h3 id="universal-modal-title" class="text-lg font-bold text-gray-800">Page Title</h3>
                    <div class="flex space-x-2">
                        <button onclick="document.getElementById('universal-page-modal').classList.add('hidden'); document.getElementById('universal-page-modal').classList.remove('flex');" class="text-gray-500 hover:text-gray-700 p-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 relative bg-white">
                    <iframe id="universal-modal-iframe" src="" class="w-full h-full border-0"></iframe>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    openPageInModal(url, title) {
        // Check if we're in MDI mode (inside an iframe in the shell)
        const isInMDI = window.self !== window.top;
        
        if (isInMDI && window.parent.openPageInTab) {
            // Use MDI tab navigation instead of modal
            const icon = this.getIconForPage(url);
            window.parent.openPageInTab(url, title, icon);
        } else if (window.openPageInTab) {
            // We're in the parent, use tab navigation
            const icon = this.getIconForPage(url);
            window.openPageInTab(url, title, icon);
        } else {
            // Fallback to old modal behavior
            this.injectModalUI();
            const modal = document.getElementById('universal-page-modal');
            const iframe = document.getElementById('universal-modal-iframe');
            const titleEl = document.getElementById('universal-modal-title');
            
            titleEl.textContent = title;
            iframe.src = url;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    getIconForPage(url) {
        if (url.includes('vitals')) return 'activity';
        if (url.includes('hpi')) return 'file-text';
        if (url.includes('wound')) return 'alert-circle';
        if (url.includes('medication')) return 'pill';
        if (url.includes('diagnosis')) return 'stethoscope';
        if (url.includes('procedure')) return 'scissors';
        if (url.includes('notes')) return 'file-text';
        if (url.includes('summary')) return 'clipboard-check';
        return 'file';
    }

    injectDictationModal() {
        if (document.getElementById('dictation-modal')) return;

        const html = `
        <div id="dictation-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-[70] p-4 backdrop-blur-sm transition-opacity duration-300">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden transform transition-all scale-100">
                <!-- Header -->
                <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
                    <div class="flex items-center gap-3">
                        <div id="dictation-status-icon" class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                        <h3 class="text-xl font-bold text-gray-800">Dictation Mode</h3>
                    </div>
                    <button id="close-dictation-btn" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Content -->
                <div class="p-6 flex-1 bg-white">
                    <textarea id="dictation-textarea" class="w-full h-64 p-4 text-lg text-gray-700 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none shadow-inner bg-gray-50" placeholder="Start speaking..."></textarea>
                    <p id="dictation-status-text" class="mt-2 text-sm text-gray-500 italic">Listening...</p>
                </div>

                <!-- Footer -->
                <div class="p-5 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button id="cancel-dictation-btn" class="px-5 py-2.5 text-gray-600 font-medium hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                    <button id="apply-dictation-btn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">Apply Text</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);

        // Bind events
        document.getElementById('close-dictation-btn').addEventListener('click', () => this.closeDictationModal());
        document.getElementById('cancel-dictation-btn').addEventListener('click', () => this.closeDictationModal());
        document.getElementById('apply-dictation-btn').addEventListener('click', () => this.applyDictation());
    }

    openDictationModal() {
        this.injectDictationModal();
        const modal = document.getElementById('dictation-modal');
        const textarea = document.getElementById('dictation-textarea');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        textarea.value = '';
        textarea.focus();
        
        // Update status
        this.updateDictationStatus('listening');
    }

    closeDictationModal() {
        const modal = document.getElementById('dictation-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        // Stop recording if active
        if (this.isListening) {
            if (this.isCloudMode) {
                this.stopCloudRecording();
            } else if (this.recognition) {
                this.recognition.stop();
            }
        }
    }

    updateDictationStatus(status) {
        const icon = document.getElementById('dictation-status-icon');
        const text = document.getElementById('dictation-status-text');
        
        if (!icon || !text) return;

        if (status === 'listening') {
            icon.className = 'w-3 h-3 rounded-full bg-red-500 animate-pulse';
            text.textContent = 'Listening...';
        } else if (status === 'processing') {
            icon.className = 'w-3 h-3 rounded-full bg-yellow-500 animate-bounce';
            text.textContent = 'Processing audio...';
        } else if (status === 'stopped') {
            icon.className = 'w-3 h-3 rounded-full bg-gray-400';
            text.textContent = 'Stopped.';
        }
    }

    applyDictation() {
        const textarea = document.getElementById('dictation-textarea');
        const text = textarea.value.trim();
        
        if (text) {
            if (this.commandInput) {
                this.commandInput.value = text;
                // Trigger input event to resize or validate if needed
                this.commandInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            
            // If in AI Assistant mode, we might want to auto-send or just populate
            // For now, just populate the input.
        }
        
        this.closeDictationModal();
    }


    initDraggable() {
        const handle = document.getElementById('smart-drag-handle');
        const container = document.getElementById('smart-command-container');
        const closeBtn = document.getElementById('closeSmartBtn');
        const fabBtn = document.getElementById('fab-smart-voice');

        if (closeBtn && container) {
            closeBtn.addEventListener('click', () => {
                container.classList.add('hidden');
                if (this.isListening && this.recognition) this.recognition.stop();
            });
        }

        if (fabBtn && container) {
            fabBtn.addEventListener('click', () => {
                container.classList.remove('hidden');
            });
        }

        // Connect the embedded button as well (if exists on page)
        const embeddedBtn = document.getElementById('open_smart_voice_btn');
        if (embeddedBtn && container) {
            embeddedBtn.addEventListener('click', () => {
                container.classList.remove('hidden');
                if (!this.isListening && this.recognition) {
                    try { this.recognition.start(); } catch (e) {}
                }
            });
        }
        
        if (!handle || !container) return;

        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        const startDrag = (clientX, clientY) => {
            isDragging = true;
            startX = clientX;
            startY = clientY;
            
            const rect = container.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            container.classList.remove('right-6', 'bottom-24', 'right-10', 'top-24'); // Remove positioning classes
            container.style.left = `${initialLeft}px`;
            container.style.top = `${initialTop}px`;
            container.style.transform = 'none';
            
            handle.style.cursor = 'grabbing';
        };

        const doDrag = (clientX, clientY) => {
            if (!isDragging) return;
            const dx = clientX - startX;
            const dy = clientY - startY;
            container.style.left = `${initialLeft + dx}px`;
            container.style.top = `${initialTop + dy}px`;
        };

        const stopDrag = () => {
            isDragging = false;
            if(handle) handle.style.cursor = 'move';
        };

        // Mouse Events
        handle.addEventListener('mousedown', (e) => {
            if (e.target.closest('#closeSmartBtn')) return;
            startDrag(e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', (e) => {
            e.preventDefault();
            doDrag(e.clientX, e.clientY);
        });
        document.addEventListener('mouseup', stopDrag);

        // Touch Events
        handle.addEventListener('touchstart', (e) => {
            if (e.target.closest('#closeSmartBtn')) return;
            const touch = e.touches[0];
            startDrag(touch.clientX, touch.clientY);
        }, { passive: false });
        document.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            doDrag(touch.clientX, touch.clientY);
        }, { passive: false });
        document.addEventListener('touchend', stopDrag);
    }

    initSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';

            this.recognition.onstart = () => {
                this.isListening = true;
                this.micBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                this.micBtn.classList.add('bg-red-500', 'hover:bg-red-600', 'animate-pulse');
                this.commandInput.placeholder = "Listening... (Say 'Stop' to end)";
                this.speak("Listening");
                this.lastInterimLength = 0; // Reset interim length tracker
            };

            this.recognition.onend = () => {
                this.isListening = false;
                this.micBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                this.micBtn.classList.remove('bg-red-500', 'hover:bg-red-600', 'animate-pulse');
                this.commandInput.placeholder = "Transcript will appear here...";
            };

            this.recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }

                const modalTextarea = document.getElementById('dictation-textarea');

                if (modalTextarea && !document.getElementById('dictation-modal').classList.contains('hidden')) {
                    // Modal Mode
                    
                    // 1. Revert previous interim
                    if (this.lastInterimLength > 0) {
                        modalTextarea.value = modalTextarea.value.substring(0, modalTextarea.value.length - this.lastInterimLength);
                        this.lastInterimLength = 0;
                    }

                    // 2. Append Final (committed)
                    if (finalTranscript) {
                        const prefix = (modalTextarea.value && !modalTextarea.value.endsWith(' ')) ? ' ' : '';
                        modalTextarea.value += prefix + finalTranscript;
                        // No interim length for final
                        
                        if (finalTranscript.toLowerCase().includes('stop dictation')) {
                            this.recognition.stop();
                            this.updateDictationStatus('stopped');
                            return;
                        }
                    }

                    // 3. Append New Interim
                    if (interimTranscript) {
                        const prefix = (modalTextarea.value && !modalTextarea.value.endsWith(' ')) ? ' ' : '';
                        const textToAdd = prefix + interimTranscript;
                        modalTextarea.value += textToAdd;
                        this.lastInterimLength = textToAdd.length;
                    }
                    
                    modalTextarea.scrollTop = modalTextarea.scrollHeight;
                } else {
                    // Classic Mode (Direct Input)
                    if (interimTranscript) {
                        this.commandInput.value = interimTranscript;
                        this.commandInput.classList.add('text-gray-500', 'italic');
                    }
                    
                    if (finalTranscript) {
                        this.commandInput.value = finalTranscript;
                        this.commandInput.classList.remove('text-gray-500', 'italic');
                        
                        if (finalTranscript.toLowerCase().includes('stop') || finalTranscript.toLowerCase().includes('cancel')) {
                            this.recognition.stop();
                            this.showFeedback("Voice entry stopped.", 'info');
                            this.speak("Voice entry stopped");
                            return;
                        }

                        this.processCommand();
                    }
                }
            };

            this.recognition.onerror = (event) => {
                console.error("Speech recognition error", event.error);
                let msg = "Error: " + event.error;
                if (event.error === 'not-allowed') msg = "Microphone access denied.";
                else if (event.error === 'no-speech') return; // Ignore

                this.showFeedback(msg, 'error');
                this.speak("Error. " + msg);
                this.isListening = false;
                this.micBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                this.micBtn.classList.remove('bg-red-500', 'hover:bg-red-600', 'animate-pulse');
            };
        } else {
            if (this.micBtn) this.micBtn.style.display = 'none';
        }
    }

    toggleDictation() {
        if (this.isListening) {
            if (this.isCloudMode) {
                this.stopCloudRecording();
            } else {
                this.recognition.stop();
            }
            // Don't close modal automatically on stop, let user review text
            this.updateDictationStatus('stopped');
        } else {
            this.openDictationModal(); // Open modal first
            
            if (this.isCloudMode) {
                this.startCloudRecording();
            } else {
                this.recognition.start();
            }
        }
    }

    async startCloudRecording() {
        console.log("Starting Cloud Recording...");
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // Try to use a supported mime type
            let options = {};
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                options = { mimeType: 'audio/webm;codecs=opus' };
            } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                options = { mimeType: 'audio/webm' };
            }
            
            console.log("MediaRecorder options:", options);
            this.mediaRecorder = new MediaRecorder(stream, options);
            this.audioChunks = [];

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = async () => {
                console.log("Cloud Recording Stopped. Chunks:", this.audioChunks.length);
                const audioBlob = new Blob(this.audioChunks, { type: options.mimeType || 'audio/webm' });
                console.log("Audio Blob Size:", audioBlob.size, "Type:", audioBlob.type);
                
                if (audioBlob.size > 0) {
                    await this.processCloudAudio(audioBlob);
                } else {
                    this.showFeedback("No audio recorded.", 'warning');
                }
                
                // Stop all tracks to release microphone
                stream.getTracks().forEach(track => track.stop());
            };

            this.mediaRecorder.start();
            this.isListening = true;
            
            // UI Updates
            this.micBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            this.micBtn.classList.add('bg-purple-600', 'hover:bg-purple-700', 'animate-pulse'); // Purple for Cloud
            if (this.commandInput) this.commandInput.placeholder = "Recording to Cloud... (Click mic to stop)";
            this.speak("Recording");

        } catch (err) {
            console.error("Error accessing microphone:", err);
            this.showFeedback("Microphone access denied.", 'error');
        }
    }

    stopCloudRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
            this.isListening = false;
            
            // UI Updates
            this.micBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            this.micBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700', 'animate-pulse');
            this.commandInput.placeholder = "Processing audio...";
        }
    }

    async processCloudAudio(audioBlob) {
        console.log("Processing Cloud Audio. Blob size:", audioBlob.size);
        
        const modalTextarea = document.getElementById('dictation-textarea');
        const isModalOpen = modalTextarea && !document.getElementById('dictation-modal').classList.contains('hidden');

        if (isModalOpen) {
            this.updateDictationStatus('processing');
        } else if (this.commandInput) {
            this.commandInput.value = "Transcribing...";
        }
        
        const reader = new FileReader();
        reader.readAsDataURL(audioBlob);
        reader.onloadend = async () => {
            // Ensure we have a valid base64 string
            if (!reader.result || !reader.result.includes(',')) {
                console.error("Invalid FileReader result:", reader.result);
                this.showFeedback("Error processing audio file.", 'error');
                return;
            }
            
            const base64Audio = reader.result.split(',')[1]; 
            
            try {
                console.log("Sending audio to backend...");
                const response = await fetch('api/ai_companion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'transcribe_audio',
                        audio: base64Audio,
                        mimeType: audioBlob.type || 'audio/webm'
                    })
                });
                
                const data = await response.json();
                console.log("Backend response:", data);
                
                if (data.success) {
                    if (isModalOpen) {
                        const currentVal = modalTextarea.value;
                        modalTextarea.value = currentVal + (currentVal && !currentVal.endsWith(' ') ? ' ' : '') + data.text;
                        modalTextarea.scrollTop = modalTextarea.scrollHeight;
                        this.updateDictationStatus('stopped');
                    } else if (this.commandInput) {
                        this.commandInput.value = data.text;
                        // Optional: Auto-process if it looks like a command
                        // this.processCommand(); 
                    }
                } else {
                    this.showFeedback("Transcription failed: " + (data.message || "Unknown error"), 'error');
                    if (isModalOpen) {
                        this.updateDictationStatus('stopped');
                    } else if (this.commandInput) {
                        this.commandInput.value = "";
                    }
                }
            } catch (error) {
                console.error("Transcription error:", error);
                this.showFeedback("Network error during transcription.", 'error');
                if (isModalOpen) {
                    this.updateDictationStatus('stopped');
                } else if (this.commandInput) {
                    this.commandInput.value = "";
                }
            }
        };
    }

    speak(text) {
        if (this.synth) {
            // Cancel any currently playing speech to avoid overlap
            this.synth.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            
            // --- Voice Selection Logic ---
            const voices = this.synth.getVoices();
            
            // Preference: 1. Google US English, 2. Microsoft Zira (Female), 3. Any Female, 4. Default
            let selectedVoice = voices.find(v => v.name.includes('Google US English'));
            
            if (!selectedVoice) {
                selectedVoice = voices.find(v => v.name.includes('Zira')); // Common Windows Female Voice
            }
            
            if (!selectedVoice) {
                // Try to find any female voice
                selectedVoice = voices.find(v => v.name.includes('Female'));
            }

            if (selectedVoice) {
                utterance.voice = selectedVoice;
            }

            // Adjust rate/pitch for a more natural feel
            utterance.rate = 1.0; 
            utterance.pitch = 1.0;

            this.synth.speak(utterance);
        }
    }

    async processCommand() {
        let text = this.commandInput.value.trim();
        
        // Check for pending image attachment (allow empty text if image exists)
        const hasPendingImage = window.pendingImageAttachment !== null && window.pendingImageAttachment !== undefined;

        if (!text && !hasPendingImage) return;

        // If we have an image but no text, use a default prompt to trigger analysis
        if (!text && hasPendingImage) {
            text = "Analyze this image";
        }

        // --- Check for Navigation Commands FIRST (All Modes) ---
        // This allows "Open Vitals" to work even in AI Assistant Mode
        const navigationResult = this.parseText(text);
        if (navigationResult === null) {
            // Navigation occurred
            this.commandInput.value = '';
            if (this.mode === 'ai_assistant') {
                this.appendChatMessage('user', text);
                this.appendChatMessage('ai', "Opening that page for you...");
            }
            return;
        }

        // --- AI Feature: Add New Wound (Prioritized over Chat) ---
        // Trigger phrases: "add wound", "new wound", "patient has a wound", "found a wound", "add * ulcer", "add * injury"
        const lowerText = text.toLowerCase();
        console.log("Processing Voice Command:", lowerText); // Debugging

        // Regex for more flexible matching
        // Matches: "add wound", "add a wound", "add new wound", "add a new wound", "adding wound", "add wounds"
        const addWoundRegex = /add(?:ing)?\s+(?:a\s+)?(?:new\s+)?wounds?/i;
        
        if (
            addWoundRegex.test(lowerText) ||
            lowerText.includes('patient has a wound') || 
            lowerText.includes('found a wound') ||
            (lowerText.startsWith('add') && (lowerText.includes('ulcer') || lowerText.includes('injury') || lowerText.includes('incision') || lowerText.includes('tear')))
        ) {
            console.log("Matched Add Wound Intent");
            // If in AI Assistant mode, show the user what's happening
            if (this.mode === 'ai_assistant') {
                this.appendChatMessage('user', text);
            }
            await this.handleAddWound(text);
            return;
        }

        // --- AI Assistant Mode ---
        if (this.mode === 'ai_assistant') {
            // Try to parse specific commands first (Vitals)
            const vitalsUpdates = this.parseVitals(text);
            if (Object.keys(vitalsUpdates).length > 0) {
                this.appendChatMessage('user', text);
                await this.saveVitalsToBackend(vitalsUpdates);
                return;
            }

            await this.handleAIAssistantChat(text);
            return;
        }

        const updates = this.parseText(text);
        
        // If updates is null, it means a navigation command was executed
        if (updates === null) return;

        this.applyUpdates(updates);
        
        if (Object.keys(updates).length > 0) {
            const msg = `Updated ${Object.keys(updates).length} fields`;
            this.showFeedback(msg, 'success');
            this.speak(msg);
            this.commandInput.value = '';
        } else {
            // --- AI Companion Fallback ---
            // If no commands matched, treat it as a conversation
            await this.handleCompanionChat(text);
        }
    }

    async handleAIAssistantChat(text) {
        // Check for pending image attachment
        const pendingImage = window.pendingImageAttachment;
        const pendingImageType = window.pendingImageType;
        const pendingWoundId = window.pendingWoundId;
        window.pendingImageAttachment = null; // Clear it
        window.pendingImageType = null; // Clear it
        window.pendingWoundId = null; // Clear it

        // If we have an image but no text, provide a default text
        if (!text && pendingImage) {
            text = "Analyze this image";
        }

        // If we have neither, do nothing
        if (!text && !pendingImage) return;

        // Append User Message to UI
        if (pendingImage) {
            // Image is already appended by the modal logic, so we just append text if it's not the default
            if (text !== "Attached an image.") {
                this.appendChatMessage('user', text);
            }
        } else {
            this.appendChatMessage('user', text);
        }
        
        this.commandInput.value = '';
        this.updateStatus('Thinking...');

        // Check Fast Mode
        const isFastMode = document.getElementById('fast_mode_toggle')?.checked;

        try {
            const payload = {
                patient_id: this.context.patient_id,
                appointment_id: this.context.appointment_id,
                user_id: this.context.user_id,
                transcript: text,
                mode: isFastMode ? 'chat' : 'full_visit', // Signal to backend
                current_note: this.liveNoteContainer ? this.liveNoteContainer.innerHTML : '',
                context: window.aiContext || null
            };

            if (pendingImage) {
                // Extract base64 and mime
                const parts = pendingImage.split(',');
                const mimeMatch = parts[0].match(/:(.*?);/);
                payload.image_data = parts[1];
                payload.mime_type = mimeMatch ? mimeMatch[1] : 'image/jpeg';
                if (pendingImageType) {
                    payload.image_type = pendingImageType;
                }
                if (pendingWoundId) {
                    payload.wound_id = pendingWoundId;
                }
            }

            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                this.appendChatMessage('ai', result.reply);
                this.speak(result.reply);
                
                // Update Live Note
                if (this.liveNoteContainer) {
                    if (result.live_note_html) {
                        // Use instantaneous result to avoid latency/race conditions
                        this.liveNoteContainer.innerHTML = result.live_note_html;
                    } else {
                        // Fallback to fetch
                        this.refreshLiveReport();
                    }
                }

                // Update AI Insights if provided
                const insightsContainer = document.getElementById('ai-insights-container');
                const insightsContent = document.getElementById('ai-insights-content');
                if (result.insight && insightsContainer && insightsContent) {
                    let formattedInsight = result.insight;
                    
                    // Basic Markdown to HTML fallback
                    // Bold: **text**
                    formattedInsight = formattedInsight.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                    
                    // Lists: * item (simple heuristic)
                    // If we see lines starting with * , wrap them in li and the whole thing in ul
                    if (formattedInsight.match(/^\s*\*\s/m)) {
                         formattedInsight = formattedInsight.replace(/^\s*\*\s(.*)$/gm, '<li>$1</li>');
                         // Wrap any sequence of <li> in <ul> (simplified, assumes the whole block is a list if it has bullets)
                         if (!formattedInsight.includes('<ul>')) {
                             formattedInsight = '<ul class="list-disc pl-5 space-y-1">' + formattedInsight + '</ul>';
                         }
                    }

                    insightsContent.innerHTML = formattedInsight;
                    insightsContainer.classList.remove('hidden');
                }
            } else {
                this.appendChatMessage('ai', "I'm sorry, I encountered an error: " + result.message);
                this.speak("Error.");
            }
        } catch (e) {
            console.error(e);
            this.appendChatMessage('ai', "Network error. Please try again.");
        } finally {
            this.updateStatus('Ready');
        }
    }

    appendChatMessage(sender, text) {
        if (!this.chatContainer) return;
        
        const isUser = sender === 'user';
        const alignClass = isUser ? 'justify-end' : 'justify-start';
        const bgClass = isUser ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-100';
        const icon = isUser ? '<i data-lucide="user" class="w-5 h-5 text-white"></i>' : '<i data-lucide="bot" class="w-5 h-5 text-indigo-600"></i>';
        const iconBg = isUser ? 'bg-indigo-800' : 'bg-indigo-100';

        const wrapper = document.createElement('div');
        wrapper.className = `flex items-start ${alignClass} mb-3`;

        let html = '';
        if (!isUser) {
            html += `<div class="${iconBg} p-2 rounded-full mr-3 flex-shrink-0">${icon}</div>`;
        }
        
        html += `<div class="${bgClass} p-3 rounded-lg shadow-sm max-w-[85%]"><p class="text-sm whitespace-pre-wrap"></p></div>`;

        if (isUser) {
            html += `<div class="${iconBg} p-2 rounded-full ml-3 flex-shrink-0">${icon}</div>`;
        }

        wrapper.innerHTML = html;
        this.chatContainer.appendChild(wrapper);
        
        // Refresh icons
        if (window.lucide && window.lucide.createIcons) {
            window.lucide.createIcons({ root: wrapper });
        }

        const pTag = wrapper.querySelector('p');
        
        if (isUser) {
            pTag.textContent = text;
            this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
        } else {
            // Typewriter effect
            let i = 0;
            const speed = 30; // Slower: 30ms per char
            
            const type = () => {
                if (i < text.length) {
                    pTag.textContent += text.charAt(i);
                    i++;
                    this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
                    setTimeout(type, speed);
                }
            };
            type();
        }
    }

    updateLiveNote(htmlContent) {
        if (this.liveNoteContainer) {
            this.liveNoteContainer.innerHTML = htmlContent;
        }
    }

    async refreshLiveReport() {
        if (!this.liveNoteContainer) return;
        
        try {
            const response = await fetch(`api/get_live_report.php?patient_id=${this.context.patient_id}&appointment_id=${this.context.appointment_id}`);
            const html = await response.text();
            this.liveNoteContainer.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } catch (e) {
            console.error("Failed to refresh report", e);
        }
    }

    updateStatus(status) {
        if (this.statusIndicator) this.statusIndicator.textContent = status;
    }

    async saveVitalsToBackend(vitals) {
        this.updateStatus('Saving Vitals...');
        try {
            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_vitals',
                    patient_id: this.context.patient_id,
                    appointment_id: this.context.appointment_id,
                    vitals: vitals
                })
            });
            const result = await response.json();

            if (result.success) {
                this.appendChatMessage('ai', "I've updated the vitals for you.");
                this.speak("Vitals updated.");
                this.commandInput.value = '';

                // --- SYNC TO LIVE NOTE ---
                const liveNote = document.getElementById('live-note-content');
                if (liveNote) {
                    // Format vitals for display
                    const vitalsStr = Object.entries(vitals)
                        .map(([k, v]) => `${k.replace(/_/g, ' ')}: ${v}`)
                        .join(', ');
                    
                    const p = document.createElement('p');
                    p.innerHTML = `<strong>Vitals Recorded:</strong> ${vitalsStr}`;
                    liveNote.appendChild(p);
                    
                    // Trigger autosave if available
                    if (window.saveDraft) window.saveDraft();
                }
                // -------------------------

            } else {
                this.appendChatMessage('ai', "Error saving vitals: " + result.message);
                this.speak("Error saving vitals.");
            }
        } catch (e) {
            console.error(e);
            this.appendChatMessage('ai', "Network error while saving vitals.");
        } finally {
            this.updateStatus('Ready');
        }
    }

    syncExtractedDataToLiveNote(data) {
        const liveNote = document.getElementById('live-note-content');
        if (!liveNote) return;

        let updates = [];

        // Vitals
        if (data.vitals && Object.keys(data.vitals).length > 0) {
            const v = data.vitals;
            const parts = [];
            if (v.blood_pressure) parts.push(`BP: ${v.blood_pressure}`);
            if (v.heart_rate) parts.push(`HR: ${v.heart_rate}`);
            if (v.respiratory_rate) parts.push(`RR: ${v.respiratory_rate}`);
            if (v.oxygen_saturation) parts.push(`O2: ${v.oxygen_saturation}%`);
            if (v.temperature_celsius) parts.push(`Temp: ${v.temperature_celsius}C`);
            
            if (parts.length > 0) {
                updates.push(`<strong>Vitals:</strong> ${parts.join(', ')}`);
            }
        }

        // Wounds
        if (data.wounds && Array.isArray(data.wounds)) {
            data.wounds.forEach(w => {
                updates.push(`<strong>Wound Assessment:</strong> ${w.location} - ${w.type} (${w.length_cm || '?'}x${w.width_cm || '?'} cm)`);
            });
        }

        // Diagnoses
        if (data.diagnoses && Array.isArray(data.diagnoses)) {
            data.diagnoses.forEach(d => {
                updates.push(`<strong>Diagnosis:</strong> ${d.description} (${d.code || 'Uncoded'})`);
            });
        }

        // Medications
        if (data.medications && Array.isArray(data.medications)) {
            data.medications.forEach(m => {
                updates.push(`<strong>Medication:</strong> ${m.drug_name} ${m.dosage || ''} ${m.frequency || ''}`);
            });
        }

        // Append updates
        if (updates.length > 0) {
            updates.forEach(html => {
                const p = document.createElement('p');
                p.innerHTML = html;
                liveNote.appendChild(p);
            });
            
            // Trigger autosave
            if (window.saveDraft) window.saveDraft();
        }
    }

    appendThinkingMessage() {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-start mb-4';
        msgDiv.id = 'current-thinking-bubble';
        msgDiv.innerHTML = `
            <div class="bg-indigo-100 p-2 rounded-full mr-2 flex-shrink-0">
                <i data-lucide="sparkles" class="w-5 h-5 text-indigo-600 animate-pulse"></i>
            </div>
            <div class="bg-white border border-indigo-100 text-gray-800 p-3 rounded-lg rounded-tl-none shadow-sm max-w-[85%] w-full">
                <div class="flex justify-between items-center cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden');">
                    <span class="text-xs font-bold text-indigo-500 uppercase tracking-wider">AI Thinking</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                </div>
                <div id="thinking-steps" class="mt-2 space-y-1 text-xs text-gray-500">
                    <div class="flex items-center"><div class="w-1.5 h-1.5 bg-indigo-400 rounded-full mr-2"></div> Analyzing input...</div>
                </div>
            </div>
        `;
        this.chatContainer.appendChild(msgDiv);
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        
        return msgDiv;
    }

    async handleCompanionChat(text) {
        // this.showFeedback("Thinking...", 'info'); // Replaced by Thinking Bubble
        const thinkingBubble = this.appendThinkingMessage();
        const stepsContainer = thinkingBubble.querySelector('#thinking-steps');
        
        // Simulate steps while waiting
        const steps = [
            "Extracting clinical entities...",
            "Checking medical guidelines...",
            "Formulating insights...",
            "Finalizing response..."
        ];
        let stepIndex = 0;
        const stepInterval = setInterval(() => {
            if (stepIndex < steps.length) {
                const div = document.createElement('div');
                div.className = 'flex items-center animate-pulse';
                div.innerHTML = `<div class="w-1.5 h-1.5 bg-indigo-400 rounded-full mr-2"></div> ${steps[stepIndex]}`;
                stepsContainer.appendChild(div);
                stepIndex++;
            }
        }, 1500);
        
        try {
            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patient_id: this.context.patient_id,
                    appointment_id: this.context.appointment_id,
                    transcript: text
                })
            });
            const result = await response.json();
            
            clearInterval(stepInterval);
            thinkingBubble.remove(); // Remove simulated bubble, or replace with real one

            if (result.success) {
                // Show Real Thought Process if available
                if (result.thought_process && Array.isArray(result.thought_process) && result.thought_process.length > 0) {
                    const realThinkingDiv = document.createElement('div');
                    realThinkingDiv.className = 'flex items-start mb-2';
                    realThinkingDiv.innerHTML = `
                        <div class="bg-gray-100 p-1.5 rounded-full mr-2 flex-shrink-0 opacity-50">
                            <i data-lucide="brain-circuit" class="w-4 h-4 text-gray-500"></i>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 text-gray-600 p-2 rounded-lg text-xs w-full">
                            <div class="font-bold mb-1 cursor-pointer flex justify-between" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <span>AI Reasoning</span>
                                <i data-lucide="chevron-down" class="w-3 h-3"></i>
                            </div>
                            <div class="hidden space-y-1 pl-1 border-l-2 border-gray-300">
                                ${result.thought_process.map(step => `<div>• ${step}</div>`).join('')}
                            </div>
                        </div>
                    `;
                    this.chatContainer.appendChild(realThinkingDiv);
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }

                this.showFeedback("AI Companion", 'success');
                this.speak(result.reply);
                this.commandInput.value = '';

                // Sync extracted data to Live Note
                if (result.extracted_data) {
                    this.syncExtractedDataToLiveNote(result.extracted_data);
                }

                // Show Clinical Insights
                if (result.clinical_insights && Array.isArray(result.clinical_insights) && result.clinical_insights.length > 0) {
                    const container = document.getElementById('ai-insights-container');
                    const content = document.getElementById('ai-insights-content');
                    if (container && content) {
                        content.innerHTML = '<ul class="list-disc pl-5 space-y-1">' + 
                            result.clinical_insights.map(i => `<li>${i}</li>`).join('') + 
                            '</ul>';
                        container.classList.remove('hidden');
                    }
                }
            } else {
                console.warn("AI Companion Error:", result.message);
                this.showFeedback(result.message || "I didn't catch that.", 'warning');
                this.speak("I didn't catch that.");
            }
        } catch (e) {
            clearInterval(stepInterval);
            if (thinkingBubble) thinkingBubble.remove();
            console.error(e);
            this.showFeedback("Connection error", 'error');
        }
    }

    async handleAddWound(text) {
        this.showFeedback("Analyzing wound description...", 'info');
        this.speak("Analyzing wound description");

        try {
            const response = await fetch('api/ai_add_wound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patient_id: this.context.patient_id,
                    transcript: text
                })
            });
            const result = await response.json();

            if (result.success) {
                this.showFeedback(result.message, 'success');
                this.speak(result.message);
                this.commandInput.value = '';

                // --- SYNC TO LIVE NOTE ---
                const liveNote = document.getElementById('live-note-content');
                if (liveNote) {
                    const p = document.createElement('p');
                    p.innerHTML = `<strong>Wound Added:</strong> ${text}`; // Use the transcript as description
                    liveNote.appendChild(p);
                    
                    // Trigger autosave if available
                    if (window.saveDraft) window.saveDraft();
                }
                // -------------------------
                
                // If on wounds page, refresh to show the new wound
                if (this.mode === 'wound' || window.location.pathname.includes('visit_wounds')) {
                    this.speak("Reloading page to show new wound.");
                    setTimeout(() => location.reload(), 2000);
                }
            } else {
                this.showFeedback("Error: " + result.message, 'error');
                this.speak("Error adding wound. " + result.message);
            }
        } catch (e) {
            console.error(e);
            this.showFeedback("Network error", 'error');
            this.speak("Network error");
        }
    }

    parseText(text) {
        const lowerText = text.toLowerCase();

        // --- Global Navigation Commands ---
        if (lowerText.includes('go to') || lowerText.includes('open') || lowerText.includes('navigate to')) {
            const baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
            const params = `?patient_id=${this.context.patient_id}&appointment_id=${this.context.appointment_id}&user_id=${this.context.user_id}`;
            const modalParams = params + '&layout=modal';

            if (lowerText.includes('vitals')) {
                this.speak("Opening Vitals");
                this.openPageInModal(`${baseUrl}/visit_vitals.php${modalParams}`, "Vitals");
                return null;
            } else if (lowerText.includes('hpi') || lowerText.includes('history')) {
                this.speak("Opening HPI");
                this.openPageInModal(`${baseUrl}/visit_hpi.php${modalParams}`, "History of Present Illness");
                return null;
            } else if (lowerText.includes('wound')) {
                this.speak("Opening Wounds");
                this.openPageInModal(`${baseUrl}/visit_wounds.php${modalParams}`, "Wound Assessment");
                return null;
            } else if (lowerText.includes('diagnosis')) {
                this.speak("Opening Diagnosis");
                this.openPageInModal(`${baseUrl}/visit_diagnosis.php${modalParams}`, "Diagnosis");
                return null;
            } else if (lowerText.includes('medication') || lowerText.includes('meds')) {
                this.speak("Opening Medications");
                this.openPageInModal(`${baseUrl}/visit_medications.php${modalParams}`, "Medications");
                return null;
            } else if (lowerText.includes('procedure') || lowerText.includes('billing')) {
                this.speak("Opening Procedures");
                this.openPageInModal(`${baseUrl}/visit_procedure.php${modalParams}`, "Procedures");
                return null;
            } else if (lowerText.includes('note') || lowerText.includes('plan')) {
                this.speak("Opening Visit Note");
                this.openPageInModal(`${baseUrl}/visit_notes.php${modalParams}`, "Visit Note");
                return null;
            } else if (lowerText.includes('summary')) {
                this.speak("Opening Summary");
                this.openPageInModal(`${baseUrl}/visit_summary.php${modalParams}`, "Visit Summary");
                return null;
            }
        }

        // --- Page Specific Parsing ---
        switch (this.mode) {
            case 'vitals': return this.parseVitals(text);
            case 'hpi': return this.parseHPI(text);
            case 'wound': return this.parseWound(text);
            case 'notes': return this.parseNotes(text);
            // Add other parsers here
            default: return {};
        }
    }

    parseNotes(text) {
        const lowerText = text.toLowerCase();
        
        // Tab Navigation
        const tabs = {
            'chief complaint': 'chief_complaint',
            'subjective': 'subjective',
            'objective': 'objective',
            'assessment': 'assessment',
            'plan': 'plan'
        };

        for (const [key, id] of Object.entries(tabs)) {
            if (lowerText.includes(key)) {
                const btn = document.getElementById(`tab-btn-${id}`);
                if (btn) {
                    btn.click();
                    this.speak(`Opening ${key}`);
                    return null; // Navigation action
                }
            }
        }
        
        return {};
    }

    parseVitals(text) {
        const updates = {};
        const lowerText = text.toLowerCase();

        const heightMatch = lowerText.match(/height[\s:isof]*(\d+(?:\.\d+)?)/);
        if (heightMatch) updates['height_in'] = heightMatch[1];

        const weightMatch = lowerText.match(/(?:weight|wt)[\s:isof]*(\d+(?:\.\d+)?)/) || lowerText.match(/(\d+(?:\.\d+)?)[\s]*(?:lbs|pounds)/);
        if (weightMatch) updates['weight_lbs'] = weightMatch[1];

        const bpMatch = lowerText.match(/(?:bp|blood pressure)[\s:isof]*(\d+)[\s\/\-over]+(\d+)/) || lowerText.match(/(\d+)\/(\d+)/);
        if (bpMatch) updates['blood_pressure'] = `${bpMatch[1]}/${bpMatch[2]}`;

        const hrMatch = lowerText.match(/(?:heart rate|pulse|hr)[\s:isof]*(\d+)/);
        if (hrMatch) updates['heart_rate'] = hrMatch[1];

        const rrMatch = lowerText.match(/(?:respiratory rate|respiration|resp|rr)[\s:isof]*(\d+)/);
        if (rrMatch) updates['respiratory_rate'] = rrMatch[1];

        const tempMatch = lowerText.match(/(?:temperature|temp)[\s:isof]*(\d+(?:\.\d+)?)/);
        if (tempMatch) updates['temperature_f'] = tempMatch[1];

        const o2Match = lowerText.match(/(?:o2|oxygen|sat|saturation)[\s:isof]*(\d+)/);
        if (o2Match) updates['oxygen_saturation'] = o2Match[1];

        return updates;
    }

    parseHPI(text) {
        const updates = {};
        const lowerText = text.toLowerCase();

        // Helper to find input ID by label text
        const findId = (keywords) => {
            const labels = Array.from(document.querySelectorAll('label'));
            for (const label of labels) {
                if (keywords.some(k => label.textContent.toLowerCase().includes(k))) {
                    const id = label.getAttribute('for');
                    if (id) return id;
                }
            }
            return null;
        };

        // Pain Score (Numeric)
        // "Pain 5", "Pain level 5", "5 out of 10"
        const painMatch = lowerText.match(/pain[\s:isof]*(\d+)/) || lowerText.match(/(\d+)[\s]*\/[\s]*10/);
        if (painMatch) {
            const painId = findId(['pain', 'severity']);
            if (painId) updates[painId] = painMatch[1];
        }

        // Duration (Text)
        // "Duration 3 weeks", "Onset 2 days ago"
        if (lowerText.includes('duration') || lowerText.includes('onset')) {
            const durationId = findId(['duration', 'onset', 'how long']);
            if (durationId) {
                // Extract everything after the keyword
                const content = text.replace(/.*(?:duration|onset|long)\s*/i, '');
                updates[durationId] = content;
            }
        }

        // Location (Text)
        if (lowerText.includes('location')) {
            const locId = findId(['location', 'site']);
            if (locId) {
                const content = text.replace(/.*(?:location|site)\s*/i, '');
                updates[locId] = content;
            }
        }

        return updates;
    }

    parseWound(text) {
        const updates = {};
        const lowerText = text.toLowerCase();

        // --- Dimensions ---
        const lengthMatch = lowerText.match(/(?:length|len|l)[\s:isof]*(\d+(?:\.\d+)?)/) || lowerText.match(/(\d+(?:\.\d+)?)[\s]*(?:cm)?[\s]*(?:length|len|l)/);
        if (lengthMatch) updates['length_cm'] = lengthMatch[1];

        const widthMatch = lowerText.match(/(?:width|wid|w)[\s:isof]*(\d+(?:\.\d+)?)/) || lowerText.match(/(\d+(?:\.\d+)?)[\s]*(?:cm)?[\s]*(?:width|wid|w)/);
        if (widthMatch) updates['width_cm'] = widthMatch[1];

        const depthMatch = lowerText.match(/(?:depth|dep|d)[\s:isof]*(\d+(?:\.\d+)?)/) || lowerText.match(/(\d+(?:\.\d+)?)[\s]*(?:cm)?[\s]*(?:depth|dep|d)/);
        if (depthMatch) updates['depth_cm'] = depthMatch[1];

        // --- Tunneling ---
        if (lowerText.includes('tunneling') || lowerText.includes('tunnel')) {
            if (lowerText.match(/(?:no|not|absent|none)\s*(?:tunneling|tunnel)/) || lowerText.match(/(?:tunneling|tunnel)\s*(?:is)?\s*(?:no|not|absent|none)/)) {
                updates['tunneling_present'] = 'No';
            } else if (lowerText.match(/(?:yes|present)\s*(?:tunneling|tunnel)/) || lowerText.match(/(?:tunneling|tunnel)\s*(?:is)?\s*(?:yes|present)/)) {
                updates['tunneling_present'] = 'Yes';
            }
            
            const locMatch = lowerText.match(/location\s+(\d+)\s*o'?clock\s+(\d+(?:\.\d+)?)\s*cm/);
            if (locMatch) {
                updates['tunneling_present'] = 'Yes';
                updates['tunneling_new_location'] = { position: locMatch[1], depth: locMatch[2] };
            }
        }

        // --- Undermining ---
        if (lowerText.includes('undermining') || lowerText.includes('undermine')) {
            if (lowerText.match(/(?:no|not|absent|none)\s*(?:undermining|undermine)/) || lowerText.match(/(?:undermining|undermine)\s*(?:is)?\s*(?:no|not|absent|none)/)) {
                updates['undermining_present'] = 'No';
            } else if (lowerText.match(/(?:yes|present)\s*(?:undermining|undermine)/) || lowerText.match(/(?:undermining|undermine)\s*(?:is)?\s*(?:yes|present)/)) {
                updates['undermining_present'] = 'Yes';
            }

            const locMatch = lowerText.match(/location\s+(\d+)\s*o'?clock\s+(\d+(?:\.\d+)?)\s*cm/);
            if (locMatch) {
                updates['undermining_present'] = 'Yes';
                updates['undermining_new_location'] = { position: locMatch[1], depth: locMatch[2] };
            }
        }

        // --- Pain ---
        const painMatch = lowerText.match(/pain[\s:isof]*(\d+)/);
        if (painMatch) {
            let val = parseInt(painMatch[1]);
            if (val >= 0 && val <= 10) updates['pain_level'] = val;
        }

        // --- Tissue % ---
        const granMatch = lowerText.match(/granulation[\s:isof]*(\d+)/);
        if (granMatch) updates['granulation_percent'] = granMatch[1];

        const sloughMatch = lowerText.match(/slough[\s:isof]*(\d+)/);
        if (sloughMatch) updates['slough_percent'] = sloughMatch[1];

        const escharMatch = lowerText.match(/eschar[\s:isof]*(\d+)/);
        if (escharMatch) updates['eschar_percent'] = escharMatch[1];

        const epiMatch = lowerText.match(/(?:epithelial|epi)[\s:isof]*(\d+)/);
        if (epiMatch) updates['epithelialization_percent'] = epiMatch[1];

        // --- Drainage Amount ---
        if (lowerText.includes('drainage') || lowerText.includes('exudate')) {
            if (lowerText.match(/none/)) updates['exudate_amount'] = 'None';
            else if (lowerText.match(/scant/)) updates['exudate_amount'] = 'Scant';
            else if (lowerText.match(/small/)) updates['exudate_amount'] = 'Small';
            else if (lowerText.match(/moderate|mod/)) updates['exudate_amount'] = 'Moderate';
            else if (lowerText.match(/large|heavy/)) updates['exudate_amount'] = 'Large';
        }

        // --- Drainage Type ---
        if (lowerText.match(/serous/)) updates['drainage_type'] = 'Serous';
        else if (lowerText.match(/purulent/)) updates['drainage_type'] = 'Purulent';
        else if (lowerText.match(/serosanguineous|sero-sanguineous/)) updates['drainage_type'] = 'Serosanguineous';
        else if (lowerText.match(/clear/)) updates['drainage_type'] = 'Clear';

        // --- Odor ---
        if (lowerText.includes('odor')) {
            if (lowerText.match(/(?:no|not|absent|none)\s*odor/) || lowerText.match(/odor\s*(?:is)?\s*(?:no|not|absent|none)/)) {
                updates['odor_present'] = 'No';
            } else if (lowerText.match(/(?:yes|present|foul|bad)\s*odor/) || lowerText.match(/odor\s*(?:is)?\s*(?:yes|present|foul|bad)/)) {
                updates['odor_present'] = 'Yes';
            }
        }

        // --- Periwound (Multi-select) ---
        const periwoundOptions = ['Intact', 'Macerated', 'Erythema', 'Edema', 'Indurated'];
        const periwoundMatches = [];
        periwoundOptions.forEach(opt => {
            const negationRegex = new RegExp(`(?:no|not|absent|without)\\s+${opt}`, 'i');
            if (lowerText.includes(opt.toLowerCase()) && !negationRegex.test(lowerText)) {
                periwoundMatches.push(opt);
            }
        });
        if (periwoundMatches.length > 0) updates['periwound_condition'] = periwoundMatches;

        // --- Infection (Multi-select) ---
        const infectionOptions = ['Redness', 'Swelling', 'Warmth', 'Increased Pain', 'Purulent Drainage', 'Osteomyelitis', 'Cellulitis'];
        const infectionMatches = [];
        infectionOptions.forEach(opt => {
            const negationRegex = new RegExp(`(?:no|not|absent|without)\\s+${opt}`, 'i');
            if (lowerText.includes(opt.toLowerCase()) && !negationRegex.test(lowerText)) {
                infectionMatches.push(opt);
            }
        });
        if (lowerText.includes('increased pain') && !infectionMatches.includes('Increased Pain')) {
             if (!/no\s+increased\s+pain/i.test(lowerText)) infectionMatches.push('Increased Pain');
        }
        if (infectionMatches.length > 0) updates['signs_of_infection'] = infectionMatches;

        // --- Exposed Structures (Checkbox) ---
        const exposedOptions = ['Bone', 'Tendon', 'Ligament', 'Muscle', 'Fascia', 'Hardware', 'Joint Capsule'];
        const exposedMatches = [];
        if (lowerText.includes('exposed')) {
             exposedOptions.forEach(opt => {
                const negationRegex = new RegExp(`(?:no|not|absent|without)\\s+${opt}`, 'i');
                if (lowerText.includes(opt.toLowerCase()) && !negationRegex.test(lowerText)) {
                    exposedMatches.push(opt);
                }
            });
            if (lowerText.includes('none')) exposedMatches.push('None');
        }
        if (exposedMatches.length > 0) updates['exposed_structures'] = exposedMatches;

        // --- Debridement ---
        if (lowerText.includes('debridement') || lowerText.includes('debride')) {
             if (lowerText.match(/(?:no|not|absent|none)\s*(?:debridement|debride)/) || lowerText.match(/(?:debridement|debride)\s*(?:is)?\s*(?:no|not|absent|none)/)) {
                updates['debridement_performed'] = 'No';
            } else if (lowerText.match(/(?:yes|present|performed)\s*(?:debridement|debride)/) || lowerText.match(/(?:debridement|debride)\s*(?:is)?\s*(?:yes|present|performed)/)) {
                updates['debridement_performed'] = 'Yes';
            }

            if (lowerText.match(/sharp/)) updates['debridement_type'] = 'Sharp';
            else if (lowerText.match(/mechanical/)) updates['debridement_type'] = 'Mechanical';
            else if (lowerText.match(/enzymatic/)) updates['debridement_type'] = 'Enzymatic';
            else if (lowerText.match(/autolytic/)) updates['debridement_type'] = 'Autolytic';
            
            if (updates['debridement_type']) {
                updates['debridement_performed'] = 'Yes';
            }
        }

        return updates;
    }

    applyUpdates(updates) {
        for (const [fieldId, value] of Object.entries(updates)) {
            const flashElement = (el) => {
                if (!el) return;
                el.classList.remove('smart-update-flash');
                void el.offsetWidth;
                el.classList.add('smart-update-flash');
            };

            if (fieldId === 'undermining_new_location') {
                if (typeof window.addLocationField === 'function') window.addLocationField('undermining', value);
                continue;
            }
            if (fieldId === 'tunneling_new_location') {
                if (typeof window.addLocationField === 'function') window.addLocationField('tunneling', value);
                continue;
            }

            if (fieldId === 'periwound_condition' || fieldId === 'signs_of_infection') {
                const select = document.getElementById(fieldId);
                if (select) {
                    Array.from(select.options).forEach(option => {
                        if (value.includes(option.value)) option.selected = true;
                    });
                    select.dispatchEvent(new Event('change'));
                    flashElement(select);
                }
                continue;
            }

            if (fieldId === 'exposed_structures') {
                const container = document.getElementById('exposed_structures_container');
                if (container) {
                    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(cb => {
                        if (value.includes(cb.value)) {
                            cb.checked = true;
                            flashElement(cb.parentElement);
                        }
                    });
                }
                continue;
            }

            const element = document.getElementById(fieldId);
            if (element) {
                if (element.tagName === 'INPUT' || element.tagName === 'SELECT') {
                    element.value = value;
                    element.dispatchEvent(new Event('change'));
                    element.dispatchEvent(new Event('input'));
                    flashElement(element);
                }
                
                const btnGroup = document.getElementById(fieldId + '_group');
                if (btnGroup) {
                    const buttons = btnGroup.querySelectorAll('.btn-option');
                    buttons.forEach(btn => {
                        if (btn.dataset.value == value) {
                            btn.click();
                            flashElement(btn);
                        }
                    });
                }
            }
        }
        
        // Show visual log of changes
        this.showChangeLog(updates);
    }

    showChangeLog(updates) {
        if (Object.keys(updates).length === 0) return;
        
        if (!this.changeLogContainer) {
            this.initChangeLogUI();
        }
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        let html = `<div class="bg-white border-l-4 border-green-500 shadow-md rounded p-3 mb-2 animate-fade-in-up relative text-left">
            <button class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs" onclick="this.parentElement.remove()">&times;</button>
            <p class="text-xs text-gray-400 mb-1 flex items-center"><i data-lucide="clock" class="w-3 h-3 mr-1"></i> ${timestamp}</p>
            <ul class="text-sm text-gray-700 space-y-1">`;
            
        for (const [key, value] of Object.entries(updates)) {
            // Format key for display (e.g., 'heart_rate' -> 'Heart Rate')
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            let displayValue = value;
            if (Array.isArray(value)) displayValue = value.join(', ');
            else if (typeof value === 'object') displayValue = JSON.stringify(value);
            
            html += `<li><span class="font-semibold text-indigo-600">${label}:</span> ${displayValue}</li>`;
        }
        
        html += `</ul></div>`;
        
        this.changeLogContainer.insertAdjacentHTML('afterbegin', html);
        
        // Re-init icons for the new content
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Auto-remove after 8 seconds
        const newToast = this.changeLogContainer.firstElementChild;
        setTimeout(() => {
            if (newToast && newToast.parentElement) {
                newToast.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                setTimeout(() => newToast.remove(), 500);
            }
        }, 8000);
    }
    
    initChangeLogUI() {
        const div = document.createElement('div');
        div.id = 'smart-change-log';
        div.className = 'fixed bottom-32 right-6 w-80 flex flex-col-reverse z-50 pointer-events-none';
        
        const style = document.createElement('style');
        style.textContent = `
            #smart-change-log > div { pointer-events: auto; }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(div);
        this.changeLogContainer = div;
    }

    showFeedback(message, type) {
        if (!this.feedbackArea) return;
        this.feedbackArea.textContent = message;
        this.feedbackArea.className = type === 'error' ? 'text-xs text-red-600 mt-1' : (type === 'warning' ? 'text-xs text-orange-600 mt-1' : 'text-xs text-green-600 mt-1');
        this.feedbackArea.style.display = 'block';
        setTimeout(() => {
            this.feedbackArea.style.display = 'none';
        }, 3000);
    }
};

// Initialize globally
document.addEventListener('DOMContentLoaded', () => {
    // Don't initialize on chat page
    if (window.location.pathname.includes('chat.php')) {
        console.log('[Smart Voice] Skipping initialization on chat page');
        return;
    }
    
    if (!window.smartVoice) {
        window.smartVoice = new window.SmartCommandParser();
    }
});
