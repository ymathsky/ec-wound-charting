class VoiceVitalsAssistant {
    constructor(config) {
        this.fields = config.fields; // Array of field objects { id, label, prompt, type }
        this.nextUrl = config.nextUrl || null; // URL for HPI navigation
        this.currentIndex = 0;
        this.isListening = false;
        this.recognition = null;
        this.synth = window.speechSynthesis;
        
        // UI Elements
        this.statusContainer = document.getElementById('voice-status-container');
        this.statusText = document.getElementById('voice-status-text');
        this.transcriptText = document.getElementById('voice-transcript-text');
        this.micIcon = document.getElementById('voice-mic-icon');
        
        this.initSpeechRecognition();
    }

    initSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'en-US';

            this.recognition.onstart = () => {
                this.isListening = true;
                this.updateUI('listening');
            };

            this.recognition.onend = () => {
                this.isListening = false;
                // If we stopped but didn't finish, we might need to handle that
                // But usually onresult handles the flow.
                this.updateUI('idle');
            };

            this.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                this.handleInput(transcript);
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error', event.error);
                this.speak("I didn't catch that. Please try again.");
                this.updateUI('error', event.error);
            };
        } else {
            alert("Your browser does not support Voice Recognition. Please use Chrome or Edge.");
        }
    }

    start() {
        if (!this.recognition) return;
        this.currentIndex = 0;
        this.statusContainer.classList.remove('hidden');
        this.askCurrentField();
    }

    stop() {
        if (this.recognition) this.recognition.stop();
        this.isListening = false;
        this.statusContainer.classList.add('hidden');
        this.synth.cancel();
    }

    askCurrentField() {
        if (this.currentIndex >= this.fields.length) {
            this.speak("All vitals recorded. Stopping voice mode.");
            setTimeout(() => this.stop(), 2000);
            return;
        }

        const field = this.fields[this.currentIndex];
        
        // Highlight the input
        document.querySelectorAll('.form-input').forEach(el => el.classList.remove('ring-2', 'ring-indigo-500'));
        const inputEl = document.getElementById(field.id);
        if (inputEl) {
            inputEl.focus();
            inputEl.classList.add('ring-2', 'ring-indigo-500');
            inputEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        this.updateUI('speaking', `Asking: ${field.label}`);
        
        this.speak(field.prompt, () => {
            // Start listening after speaking finishes
            try {
                this.recognition.start();
            } catch (e) {
                // Handle case where it's already started
                console.log("Recognition already active");
            }
        });
    }

    handleInput(text) {
        this.transcriptText.textContent = `Heard: "${text}"`;
        const lowerText = text.toLowerCase();
        
        // --- 1. Check for Global Commands ---
        if (lowerText.includes('stop') || lowerText.includes('cancel') || lowerText.includes('exit')) {
            this.speak("Stopping voice mode.");
            this.stop();
            return;
        }
        if (lowerText.includes('go to hpi') || lowerText.includes('open hpi') || lowerText.includes('finish vitals')) {
            this.speak("Navigating to HPI.", () => {
                if (this.nextUrl) {
                    window.location.href = this.nextUrl;
                } else {
                    this.speak("HPI link not found.");
                }
            });
            return;
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

        // --- 2. Check for Field-Specific Commands (Conversational Updates) ---
        // e.g. "Update height to 70", "Go back to weight"
        const commandMatch = this.parseCommand(lowerText);
        if (commandMatch) {
            if (commandMatch.value !== null) {
                // Case A: "Update height to 70" -> Update and continue
                this.fillField(commandMatch.fieldId, commandMatch.value);
                this.speak(`Updated ${commandMatch.label} to ${commandMatch.value}.`, () => {
                    // After updating a specific field, we usually return to the current flow
                    // unless the user explicitly navigated.
                    this.askCurrentField();
                });
            } else {
                // Case B: "Go back to height" (No value) -> Navigate
                const newIndex = this.fields.findIndex(f => f.id === commandMatch.fieldId);
                if (newIndex !== -1) {
                    this.currentIndex = newIndex;
                    this.speak(`Going to ${commandMatch.label}.`, () => {
                        this.askCurrentField();
                    });
                } else {
                    this.speak(`I couldn't find that field.`);
                    this.askCurrentField();
                }
            }
            return;
        }

        // --- 3. Standard Flow (Answer to Current Question) ---
        const field = this.fields[this.currentIndex];
        const value = this.parseValue(field.type, text);

        if (value !== null) {
            this.fillField(field.id, value);
            this.speak(`Recorded ${value}.`, () => {
                this.currentIndex++;
                this.askCurrentField();
            });
        } else {
            this.speak(`I couldn't understand the value for ${field.label}. Please repeat, or say skip.`, () => {
                this.recognition.start();
            });
        }
    }

    parseCommand(text) {
        // Map spoken keywords to field IDs
        const map = {
            'height': 'height_in',
            'weight': 'weight_lbs',
            'blood pressure': 'blood_pressure',
            'bp': 'blood_pressure',
            'heart rate': 'heart_rate',
            'pulse': 'heart_rate',
            'respiratory': 'respiratory_rate',
            'breathing': 'respiratory_rate',
            'temperature': 'temperature_f',
            'temp': 'temperature_f',
            'oxygen': 'oxygen_saturation',
            'o2': 'oxygen_saturation',
            'saturation': 'oxygen_saturation'
        };

        const keywords = Object.keys(map).join('|');
        // Regex looks for: [Action Verb] ... [Field Name]
        // e.g. "Update height", "Change weight", "Go back to bp", "Record temp"
        const regex = new RegExp(`(?:update|change|set|record|go\\s+to|back\\s+to|correct)\\s+(?:the\\s+)?(?:record\\s+)?(${keywords})`, 'i');
        
        const match = text.match(regex);
        if (match) {
            const keyword = match[1];
            const fieldId = map[keyword];
            const fieldConfig = this.fields.find(f => f.id === fieldId);
            
            // Try to extract a value from the REST of the sentence
            // e.g. "Update height to 70" -> " to 70"
            let value = null;
            if (fieldConfig) {
                // Remove the command part to isolate potential value
                const valuePart = text.replace(match[0], '').trim(); 
                if (valuePart) {
                    value = this.parseValue(fieldConfig.type, valuePart);
                }
            }

            return {
                fieldId: fieldId,
                label: fieldConfig ? fieldConfig.label : keyword,
                value: value
            };
        }
        return null;
    }

    fillField(id, value) {
        const input = document.getElementById(id);
        if (input) {
            input.value = value;
            // Trigger change event for Autosave
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
            
            // Visual feedback
            input.classList.add('bg-green-50');
            setTimeout(() => input.classList.remove('bg-green-50'), 1000);
        }
    }

    speak(text, onEndCallback) {
        this.synth.cancel(); // Stop any previous speech
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        
        if (onEndCallback) {
            utterance.onend = onEndCallback;
        }
        
        this.synth.speak(utterance);
    }

    updateUI(state, message) {
        if (message) this.statusText.textContent = message;
        
        if (state === 'listening') {
            this.micIcon.classList.add('text-red-600', 'animate-pulse');
            this.micIcon.classList.remove('text-gray-400');
            this.statusText.textContent = "Listening...";
        } else if (state === 'speaking') {
            this.micIcon.classList.remove('text-red-600', 'animate-pulse');
            this.micIcon.classList.add('text-indigo-600');
        } else {
            this.micIcon.classList.remove('text-red-600', 'animate-pulse', 'text-indigo-600');
            this.micIcon.classList.add('text-gray-400');
        }
    }

    // --- PARSING LOGIC ---
    parseValue(type, text) {
        text = text.toLowerCase().trim();
        
        // Common replacements for speech-to-text errors
        text = text.replace(/one/g, '1').replace(/to/g, '2').replace(/for/g, '4').replace(/too/g, '2');

        if (type === 'bp') {
            // Matches: "120 over 80", "120/80", "120 80"
            const match = text.match(/(\d{2,3})\s*(?:over|\/|\s)\s*(\d{2,3})/);
            if (match) return `${match[1]}/${match[2]}`;
            return null;
        }

        if (type === 'number') {
            // Extract first float/int
            const match = text.match(/(\d+(\.\d+)?)/);
            if (match) return parseFloat(match[1]);
            return null;
        }
        
        if (type === 'height') {
            // Handle "5 foot 8", "5 8", "68"
            // Check for feet/inches pattern first
            const feetMatch = text.match(/(\d+)\s*(?:foot|feet|ft|')\s*(\d+)?/);
            if (feetMatch) {
                const feet = parseInt(feetMatch[1]);
                const inches = feetMatch[2] ? parseInt(feetMatch[2]) : 0;
                return (feet * 12) + inches;
            }
            // Fallback to simple number (assumed inches)
            const numMatch = text.match(/(\d+(\.\d+)?)/);
            if (numMatch) return parseFloat(numMatch[1]);
            return null;
        }

        return null;
    }
}
