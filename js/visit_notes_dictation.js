/**
 * visit_notes_dictation.js
 * Handles Voice-to-Text functionality for Quill editors using Web Speech API.
 */

(function() {
    // 1. Register the "Voice" Icon (Microphone SVG)
    const VoiceIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mic"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';

    // Safely import Quill icons if available
    if (typeof Quill !== 'undefined') {
        const icons = Quill.import('ui/icons');
        icons['voice'] = VoiceIcon;
    }

    // 2. Define the Toolbar Handler
    window.handleVoiceDictation = function() {
        const quill = this.quill; // The specific editor instance

        // Check browser support
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            alert("Voice dictation is not supported in this browser. Please use Chrome, Edge, or Safari.");
            return;
        }

        // Initialize recognition instance for this editor if not exists
        if (!quill.voiceRecognition) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            recognition.continuous = true; // Keep recording even after pauses
            recognition.interimResults = true; // We can use this to show "..." typing effect if desired
            recognition.lang = 'en-US'; // Default language

            recognition.onstart = function() {
                quill.isRecording = true;
                updateMicButtonState(quill, true);

                // Optional: Visual cue in editor
                quill.root.classList.add('dictation-active');
            };

            recognition.onend = function() {
                quill.isRecording = false;
                updateMicButtonState(quill, false);
                quill.root.classList.remove('dictation-active');
            };

            recognition.onerror = function(event) {
                console.error("Dictation Error:", event.error);
                quill.isRecording = false;
                updateMicButtonState(quill, false);
            };

            recognition.onresult = function(event) {
                let finalTranscript = '';

                // Process results
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    }
                }

                if (finalTranscript) {
                    // Insert text at current cursor position
                    const range = quill.getSelection(true);
                    if (range) {
                        // Add a space before if needed
                        const textToInsert = (range.index > 0 ? ' ' : '') + finalTranscript.trim();
                        quill.insertText(range.index, textToInsert, 'user');
                        // Move cursor to end
                        quill.setSelection(range.index + textToInsert.length);
                    }
                }
            };

            quill.voiceRecognition = recognition;
            quill.isRecording = false;
        }

        // Toggle Start/Stop
        if (quill.isRecording) {
            quill.voiceRecognition.stop();
        } else {
            quill.voiceRecognition.start();
        }
    };

    // Helper to toggle visual state of the mic button
    function updateMicButtonState(quill, isRecording) {
        const toolbar = quill.getModule('toolbar');
        const btn = toolbar.container.querySelector('.ql-voice');
        if (btn) {
            if (isRecording) {
                btn.classList.add('is-recording');
                btn.style.color = '#dc2626'; // Red
                btn.classList.add('animate-pulse'); // Tailwind pulse
            } else {
                btn.classList.remove('is-recording');
                btn.style.color = '';
                btn.classList.remove('animate-pulse');
            }
        }
    }
})();