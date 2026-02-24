/**
 * js/hpi_dictation.js
 * Handles Voice-to-Text functionality for standard HTML inputs (HPI Form).
 */

(function() {
    window.activeRecognition = null;
    window.activeButton = null;

    window.toggleHpiDictation = function(inputElement, buttonElement) {
        // Check browser support
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            alert("Voice dictation is not supported in this browser. Please use Chrome, Edge, or Safari.");
            return;
        }

        // If we are already recording on THIS button, stop it.
        if (window.activeButton === buttonElement && window.activeRecognition) {
            window.activeRecognition.stop();
            return;
        }

        // If we are recording on ANOTHER button, stop that one first.
        if (window.activeRecognition) {
            window.activeRecognition.stop();
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.continuous = false; // Stop after one sentence/pause for inputs
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        let finalTranscript = '';
        let originalValue = inputElement.value;

        recognition.onstart = function() {
            window.activeRecognition = recognition;
            window.activeButton = buttonElement;
            updateButtonState(buttonElement, true);
        };

        recognition.onend = function() {
            window.activeRecognition = null;
            window.activeButton = null;
            updateButtonState(buttonElement, false);
            
            // Trigger change event for autosave
            const event = new Event('change', { bubbles: true });
            inputElement.dispatchEvent(event);
        };

        recognition.onerror = function(event) {
            console.error("Dictation Error:", event.error);
            window.activeRecognition = null;
            window.activeButton = null;
            updateButtonState(buttonElement, false);
        };

        recognition.onresult = function(event) {
            let interimTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                } else {
                    interimTranscript += event.results[i][0].transcript;
                }
            }

            // Update input value
            // We append to existing text if it's not empty and we just started
            // But for simplicity in this "one-shot" mode, let's just append.
            
            let separator = (originalValue && !originalValue.endsWith(' ')) ? ' ' : '';
            if (originalValue === '') separator = '';

            // Show interim results
            inputElement.value = originalValue + separator + finalTranscript + interimTranscript;
            
            // If final, update originalValue for next segment (if continuous was true, but it's false here)
             if (finalTranscript) {
                 // For continuous=false, this runs once at the end usually, but let's be safe
             }
        };

        recognition.start();
    };

    function updateButtonState(btn, isRecording) {
        if (isRecording) {
            btn.classList.add('text-red-600', 'animate-pulse');
            btn.classList.remove('text-gray-400', 'hover:text-gray-600');
        } else {
            btn.classList.remove('text-red-600', 'animate-pulse');
            btn.classList.add('text-gray-400', 'hover:text-gray-600');
        }
    }
})();
