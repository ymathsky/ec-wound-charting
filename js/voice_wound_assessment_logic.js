class VoiceWoundAssistant {
    constructor(config) {
        this.fields = config.fields; // Array of field objects { id, label, prompt, type, options? }
        this.currentIndex = 0;
        this.isListening = false;
        this.recognition = null;
        this.synth = window.speechSynthesis;
        
        // UI Elements (Shared with Vitals or specific to Wound)
        this.statusContainer = document.getElementById('voice-status-container');
        this.statusText = document.getElementById('voice-status-text');
        this.transcriptText = document.getElementById('voice-transcript-text');
        this.micIcon = document.getElementById('voice-mic-icon');
        
        this.initDraggable();
        this.initSpeechRecognition();
    }

    initDraggable() {
        const handle = document.getElementById('voice-drag-handle');
        const container = this.statusContainer;
        
        if (!handle || !container) return;

        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        // Mouse Events
        handle.addEventListener('mousedown', (e) => {
            // Prevent dragging if clicking the close button
            if (e.target.closest('#closeVoiceBtn')) return;

            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            
            // Get current computed position
            const rect = container.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // Remove centering classes/transforms to switch to absolute positioning
            container.classList.remove('left-1/2', 'transform', '-translate-x-1/2');
            container.style.left = `${initialLeft}px`;
            container.style.top = `${initialTop}px`;
            container.style.transform = 'none';
            
            handle.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            
            container.style.left = `${initialLeft + dx}px`;
            container.style.top = `${initialTop + dy}px`;
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
            if(handle) handle.style.cursor = 'move';
        });

        // Touch Events (for tablets/mobile)
        handle.addEventListener('touchstart', (e) => {
            if (e.target.closest('#closeVoiceBtn')) return;
            
            isDragging = true;
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            
            const rect = container.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            container.classList.remove('left-1/2', 'transform', '-translate-x-1/2');
            container.style.left = `${initialLeft}px`;
            container.style.top = `${initialTop}px`;
            container.style.transform = 'none';
        }, { passive: false });

        document.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            e.preventDefault(); // Prevent scrolling while dragging
            
            const touch = e.touches[0];
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            
            container.style.left = `${initialLeft + dx}px`;
            container.style.top = `${initialTop + dy}px`;
        }, { passive: false });

        document.addEventListener('touchend', () => {
            isDragging = false;
        });
    }

    initSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const SpeechGrammarList = window.SpeechGrammarList || window.webkitSpeechGrammarList;

            this.recognition = new SpeechRecognition();
            
            // --- GRAMMAR CALIBRATION ---
            // Bias the recognizer towards our specific medical vocabulary
            if (SpeechGrammarList) {
                const speechRecognitionList = new SpeechGrammarList();
                const terms = this.generateVocabularyList();
                const grammar = '#JSGF V1.0; grammar wound_assessment; public <term> = ' + terms.join(' | ') + ' ;';
                speechRecognitionList.addFromString(grammar, 1);
                this.recognition.grammars = speechRecognitionList;
                console.log("Voice Grammar Loaded:", terms.length, "terms");
            }

            this.recognition.continuous = false;
            this.recognition.interimResults = true; // Enable interim results for faster feedback & interruption
            this.recognition.lang = 'en-US';

            this.recognition.onstart = () => {
                this.isListening = true;
                this.updateUI('listening');
                this.playListeningSound();
            };

            this.recognition.onspeechstart = () => {
                if (this.synth.speaking) this.synth.cancel();
            };

            this.recognition.onend = () => {
                this.isListening = false;
                this.updateUI('idle');

                // Auto-restart if we didn't get a result and it wasn't a manual stop
                if (!this.manualStop && !this.gotResult) {
                    console.log("Silence detected. Restarting listener...");
                    try {
                        this.recognition.start();
                    } catch (e) {
                        // ignore
                    }
                }
            };

            this.recognition.onresult = (event) => {
                this.gotResult = true; // Flag that we heard something
                
                // Cancel TTS immediately on any result (interim or final)
                if (this.synth.speaking) this.synth.cancel();

                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }

                if (interimTranscript) {
                    this.transcriptText.textContent = `Hearing: "${interimTranscript}..."`;
                }

                if (finalTranscript) {
                    this.handleInput(finalTranscript);
                }
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error', event.error);
                
                if (event.error === 'no-speech') {
                    // Handled by onend auto-restart
                    this.updateUI('idle', 'Listening...');
                } else if (event.error === 'aborted') {
                    // Ignore aborted errors (happens when we stop manually)
                    this.updateUI('idle');
                } else {
                    this.speak(`Error: ${event.error}. Please try again.`);
                    this.updateUI('error', event.error);
                }
            };
        } else {
            alert("Your browser does not support Voice Recognition. Please use Chrome or Edge.");
        }
    }

    generateVocabularyList() {
        // Collect all keywords, options, and commands to bias the recognizer
        const vocabulary = new Set([
            'stop', 'cancel', 'exit', 'skip', 'next', 'repeat', 'again', 'yes', 'no', 'none',
            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'zero',
            'centimeters', 'percent', 'pain', 'level', 'tunneling', 'undermining', 'depth', 'width', 'length'
        ]);

        // Add field labels and options
        this.fields.forEach(field => {
            // Add label words
            field.label.toLowerCase().split(' ').forEach(w => vocabulary.add(w.replace(/[^a-z]/g, '')));
            
            // Add options
            if (field.options) {
                field.options.forEach(opt => {
                    if (typeof opt === 'string') {
                        vocabulary.add(opt.toLowerCase());
                    }
                });
            }
        });

        return Array.from(vocabulary);
    }

    start() {
        if (!this.recognition) return;
        this.manualStop = false;
        this.currentIndex = 0;
        if (this.statusContainer) this.statusContainer.classList.remove('hidden');
        this.askCurrentField();
    }

    stop() {
        this.manualStop = true;
        if (this.recognition) this.recognition.stop();
        this.isListening = false;
        if (this.statusContainer) this.statusContainer.classList.add('hidden');
        this.synth.cancel();
    }

    askCurrentField() {
        this.gotResult = false; // Reset result flag for new question
        
        if (this.currentIndex >= this.fields.length) {
            this.speak("Wound assessment complete. Listening for commands.");
            // Keep listening for commands (e.g. "Go to Pain Level", "Stop")
            try {
                this.recognition.start();
            } catch (e) {
                // Already active
            }
            return;
        }

        const field = this.fields[this.currentIndex];
        
        // Highlight the input or section
        document.querySelectorAll('.form-input, .btn-group-container').forEach(el => el.classList.remove('ring-2', 'ring-indigo-500'));
        
        const inputEl = document.getElementById(field.id);
        // If it's a button group, highlight the container
        const groupEl = document.getElementById(field.id + '_group');

        if (inputEl) {
            inputEl.focus();
            inputEl.classList.add('ring-2', 'ring-indigo-500');
            inputEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (groupEl) {
            groupEl.classList.add('ring-2', 'ring-indigo-500');
            groupEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        this.updateUI('speaking', `Asking: ${field.label}`);
        
        // Start listening immediately (allows barge-in)
        try {
            this.recognition.start();
        } catch (e) {
            // Already active
        }

        this.speak(field.prompt);
    }

    playListeningSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(500, ctx.currentTime);
            gain.gain.setValueAtTime(0.05, ctx.currentTime);
            
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.2);
            osc.stop(ctx.currentTime + 0.2);
        } catch (e) {
            console.error("Audio play failed", e);
        }
    }

    handleInput(text) {
        const lowerText = text.toLowerCase();
        
        // --- Global Commands ---
        if (lowerText.includes('stop') || lowerText.includes('cancel') || lowerText.includes('exit')) {
            this.speak("Stopping voice mode.");
            this.stop();
            return;
        }

        // --- Navigation Commands ---
        const navMatch = this.parseCommand(lowerText);
        if (navMatch) {
            const newIndex = this.fields.findIndex(f => f.id === navMatch.fieldId);
            if (newIndex !== -1) {
                this.currentIndex = newIndex;
                this.speak(`Going to ${navMatch.label}.`, () => {
                    this.askCurrentField();
                });
                return;
            }
        }

        if (lowerText.includes('skip') || lowerText.includes('next')) {
            this.speak("Skipping.");
            this.currentIndex++;
            this.askCurrentField();
            return;
        }
        if (lowerText.includes('repeat') || lowerText.includes('again')) {
            this.askCurrentField();
            return;
        }

        // If assessment is complete, only accept commands
        if (this.currentIndex >= this.fields.length) {
            this.speak("Assessment is complete. Say 'Stop' or navigate to a field.");
            return;
        }

        const field = this.fields[this.currentIndex];
        const value = this.parseValue(field.type, text, field.options);

        // Debug feedback in UI
        this.transcriptText.textContent = `Heard: "${text}" -> ${value !== null ? value : '?'}`;

        if (value !== null) {
            this.fillField(field.id, value);
            
            // Check for dynamic fields (Tunneling/Undermining)
            this.checkForDynamicFields(field.id, value);

            this.speak(`Recorded ${value}.`, () => {
                this.currentIndex++;
                this.askCurrentField();
            });
        } else {
            this.speak(`I couldn't understand the value for ${field.label}. Please repeat.`, () => {
                this.recognition.start();
            });
        }
    }

    checkForDynamicFields(id, value) {
        if ((id === 'tunneling_present' || id === 'undermining_present') && value === 'Yes') {
            const type = id.replace('_present', '');
            if (window.addLocationField) {
                let posId, depthId;
                
                try {
                    // Try to get IDs from the function return
                    const result = window.addLocationField(type);
                    if (result) {
                        posId = result.posId;
                        depthId = result.depthId;
                    }
                } catch (e) {
                    console.error("Error adding location field", e);
                }

                // Fallback: If IDs missing (e.g. cached old JS file), find elements and assign IDs
                if (!posId || !depthId) {
                    const container = document.getElementById(`${type}_locations`);
                    if (container && container.lastElementChild) {
                        const index = container.children.length - 1;
                        const row = container.lastElementChild;
                        const select = row.querySelector('select');
                        const input = row.querySelector('input[type="number"]');
                        
                        if (select && input) {
                            // Assign IDs if they don't exist
                            if (!select.id) select.id = `${type}_pos_${index}`;
                            if (!input.id) input.id = `${type}_depth_${index}`;
                            
                            posId = select.id;
                            depthId = input.id;
                        }
                    }
                }

                if (posId && depthId) {
                    // Insert new fields into the sequence
                    const newFields = [
                        { 
                            id: posId, 
                            label: `${type.charAt(0).toUpperCase() + type.slice(1)} Location`, 
                            prompt: `Where is the ${type} located? (1 to 12 o'clock)`, 
                            type: 'select', 
                            options: ['1','2','3','4','5','6','7','8','9','10','11','12'] 
                        },
                        { 
                            id: depthId, 
                            label: `${type.charAt(0).toUpperCase() + type.slice(1)} Depth`, 
                            prompt: `What is the depth of the ${type} in centimeters?`, 
                            type: 'number' 
                        }
                    ];
                    
                    this.fields.splice(this.currentIndex + 1, 0, ...newFields);
                    console.log("Added dynamic fields:", newFields);
                }
            }
        }
    }

    parseCommand(text) {
        const map = {
            'length': 'length_cm',
            'width': 'width_cm',
            'depth': 'depth_cm',
            'tunneling': 'tunneling_present',
            'undermining': 'undermining_present',
            'pain': 'pain_level',
            'granulation': 'granulation_percent',
            'slough': 'slough_percent',
            'eschar': 'eschar_percent',
            'epithelial': 'epithelialization_percent',
            'drainage amount': 'exudate_amount',
            'drainage type': 'drainage_type',
            'drainage': 'exudate_amount', 
            'odor': 'odor_present',
            'periwound': 'periwound_condition',
            'infection': 'signs_of_infection',
            'exposed': 'exposed_structures_container',
            'structures': 'exposed_structures_container',
            'debridement': 'debridement_performed'
        };

        // Sort keys by length descending to match "drainage amount" before "drainage"
        const keywords = Object.keys(map).sort((a, b) => b.length - a.length).join('|');
        
        // Allow "to" or "2" (common transcription error) or optional "to"
        // Matches: "skip to infection", "skip 2 infection", "skip infection"
        const regex = new RegExp(`(?:go|skip|jump|navigate)(?:\\s+(?:to|2))?\\s+(?:the\\s+)?(${keywords})`, 'i');
        
        const match = text.match(regex);
        if (match) {
            return {
                fieldId: map[match[1].toLowerCase()],
                label: match[1]
            };
        }
        return null;
    }

    fillField(id, value) {
        const input = document.getElementById(id);
        
        // Handle Multi-Value Fields (Arrays)
        if (Array.isArray(value)) {
            // 1. Multi-Select Dropdown
            if (input && input.tagName === 'SELECT' && input.multiple) {
                // Deselect all first? Or append? Let's append/select found ones.
                // Actually, usually voice replaces. Let's clear then select.
                Array.from(input.options).forEach(opt => opt.selected = false);
                
                Array.from(input.options).forEach(opt => {
                    if (value.includes(opt.value)) opt.selected = true;
                });
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
                return;
            }
            
            // 2. Checkbox Group (Container ID)
            const container = document.getElementById(id);
            if (container) {
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                // Clear all first
                checkboxes.forEach(cb => cb.checked = false);
                
                checkboxes.forEach(cb => {
                    if (value.includes(cb.value)) cb.checked = true;
                });
                // Trigger change on one of them to bubble up
                if (checkboxes.length > 0) {
                    const event = new Event('change', { bubbles: true });
                    checkboxes[0].dispatchEvent(event);
                }
                return;
            }
        }

        // Handle Button Groups (Hidden Input + Visual Buttons)
        const group = document.getElementById(id + '_group');
        if (group) {
            // Update hidden input
            if (input) {
                input.value = value;
                // Trigger change event to ensure autosave/logic runs
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
            
            // Update visual buttons
            const buttons = group.querySelectorAll('.btn-option');
            buttons.forEach(btn => {
                if (btn.dataset.value == value) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            return;
        }

        // Handle Standard Inputs
        if (input) {
            input.value = value;
            // Trigger change event if needed
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
            
            // Visual feedback
            input.classList.add('bg-green-50');
            setTimeout(() => input.classList.remove('bg-green-50'), 1000);
        }
    }

    speak(text, onEndCallback) {
        this.synth.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        if (onEndCallback) utterance.onend = onEndCallback;
        this.synth.speak(utterance);
    }

    updateUI(state, message) {
        if (this.statusText && message) this.statusText.textContent = message;
        
        if (!this.micIcon) return;

        if (state === 'listening') {
            this.micIcon.classList.add('text-red-600', 'animate-pulse');
            this.micIcon.classList.remove('text-gray-400');
            if (this.statusText) this.statusText.textContent = "Listening...";
        } else if (state === 'speaking') {
            this.micIcon.classList.remove('text-red-600', 'animate-pulse');
            this.micIcon.classList.add('text-indigo-600');
        } else {
            this.micIcon.classList.remove('text-red-600', 'animate-pulse', 'text-indigo-600');
            this.micIcon.classList.add('text-gray-400');
        }
    }

    // --- PARSING LOGIC ---
    parseValue(type, text, options) {
        text = text.toLowerCase().trim();
        
        // 0. Direct Match (e.g. "Yes", "No", "5")
        if (options && Array.isArray(options)) {
            const directMatch = options.find(opt => opt.toLowerCase() === text);
            if (directMatch) return directMatch;
        }

        // Enhanced number mapping (zero to ten)
        const numberMap = {
            'zero': '0', 'one': '1', 'two': '2', 'three': '3', 'four': '4',
            'five': '5', 'six': '6', 'seven': '7', 'eight': '8', 'nine': '9', 'ten': '10'
        };

        // Common Misheard Medical Terms Calibration
        const misheardMap = {
            'air theme a': 'erythema',
            'erythema': 'erythema',
            'purr you lent': 'purulent',
            'pure you lent': 'purulent',
            'serous': 'serous',
            'serious': 'serous',
            'scan': 'scant',
            'scant': 'scant',
            'mod': 'moderate',
            'moderate': 'moderate',
            'large': 'large',
            'none': 'none',
            'nun': 'none',
            'done': 'none',
            'intact': 'intact',
            'in fact': 'intact',
            'slough': 'slough',
            'slow': 'slough',
            'eschar': 'eschar',
            'scar': 'eschar',
            'granulation': 'granulation',
            'granule': 'granulation'
        };

        // Apply misheard map first
        Object.keys(misheardMap).forEach(phrase => {
             if (text.includes(phrase)) {
                 text = text.replace(phrase, misheardMap[phrase]);
             }
        });

        // Replace number words with digits
        Object.keys(numberMap).forEach(word => {
             const regex = new RegExp(`\\b${word}\\b`, 'g');
             text = text.replace(regex, numberMap[word]);
        });

        // Common replacements (Use word boundaries to avoid replacing inside words like "bone" -> "b1")
        text = text.replace(/\bto\b/g, '2')
                   .replace(/\bfor\b/g, '4')
                   .replace(/\btoo\b/g, '2')
                   .replace(/\bpoint\b/g, '.')
                   .replace(/\bno pain\b/g, '0'); // Handle "no pain" -> 0

        if (type === 'number') {
            const match = text.match(/(\d+(\.\d+)?)/);
            if (match) return parseFloat(match[1]);
            return null;
        }

        if (type === 'multi_select' || type === 'checkbox_group') {
            const selected = [];
            // Check if "None" is spoken
            if (text.includes('none') || text.includes('no ')) {
                if (options.includes('None')) return ['None'];
                if (options.includes('Intact')) return ['Intact']; // For periwound
            }

            // Iterate all options and check if text contains them
            options.forEach(opt => {
                if (text.includes(opt.toLowerCase())) {
                    selected.push(opt);
                }
            });
            
            return selected.length > 0 ? selected : null;
        }

        if (type === 'select' || type === 'button_group') {
            // Fuzzy match against options
            
            // 1. Check for exact numeric matches (e.g. Pain Level "5")
            const numMatch = text.match(/(\d+)/);
            if (numMatch) {
                const numVal = numMatch[1];
                // Check loosely (string vs number)
                if (options.some(opt => opt == numVal)) {
                    return numVal;
                }
            }

            // 2. Handle "Yes/No" specifically
            if (options.includes('Yes') && options.includes('No')) {
                if (text.includes('yes') || text.includes('yeah') || text.includes('sure')) return 'Yes';
                if (text.includes('no') || text.includes('none') || text.includes('nope')) return 'No';
            }

            // 3. Handle Drainage Amount (None, Scant, Small, Moderate, Large)
            if (text.includes('none')) return 'None';
            if (text.includes('scant')) return 'Scant';
            if (text.includes('small')) return 'Small';
            if (text.includes('mod')) return 'Moderate'; // "Moderate" or "Mod"
            if (text.includes('large')) return 'Large';

            // 4. Generic Option Match (Case-insensitive)
            // Try to find the option that is contained in the text
            const foundOpt = options.find(opt => text.includes(opt.toLowerCase()));
            if (foundOpt) return foundOpt;

            return null;
        }

        return null;
    }
}
