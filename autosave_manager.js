/**
 * ec/autosave_manager.js
 * * Centralized module for managing Autosave functionality, status feedback,
 * and floating notifications across the entire visit workflow (Vitals, HPI, etc.).
 * * Dependencies:
 * - Assumes 'lucide' icons library is loaded globally for updateAutosaveStatus.
 */

// --- AUTOSAVE CONFIGURATION CONSTANTS ---
const AUTOSAVE_DELAY = 3000; // 3 seconds delay for autosave (debounce)
const AUTOSAVE_NOTIFICATION_COOLDOWN = 30000; // 30 seconds cooldown for floating notifications
let autosaveTimer = null;
let lastNotificationTime = 0;

// --- DOM REFERENCES (Must be set by the host page before calling initAutosave) ---
let autosaveStatusDesktop = null;
let autosaveStatusMobile = null;
let autosaveMessageContainer = null;

// ====================================================================
// === CORE UTILITY FUNCTIONS =========================================
// ====================================================================

/**
 * Updates the Autosave Status Indicator text and color.
 * The host page must ensure the elements (#autosave-status-desktop, #autosave-status) exist.
 * @param {string} status 'ready', 'saving', 'saved', or 'error'
 */
export function updateAutosaveStatus(status) {
    // Check for initialization: if references are null, exit silently
    if (!autosaveStatusDesktop || !autosaveMessageContainer) {
        console.warn("Autosave DOM elements not initialized. Ensure 'initAutosaveManager' is called.");
        return;
    }

    const desktopDiv = autosaveStatusDesktop;
    // Mobile status indicator is inside a wrapper div
    const mobileDiv = document.getElementById('autosave-status');

    // Reset classes
    [desktopDiv, mobileDiv].forEach(el => {
        if (!el) return; // Guard for mobile status
        el.classList.remove('saving', 'bg-green-500', 'bg-yellow-600', 'text-gray-800', 'bg-red-600', 'text-white', 'bg-gray-300', 'text-gray-700');
        el.innerHTML = '';
    });

    if (status === 'saving') {
        [desktopDiv, mobileDiv].forEach(el => {
            if (!el) return;
            el.classList.add('saving', 'bg-yellow-600', 'text-gray-800');
            el.innerHTML = '<i data-lucide="loader" class="w-5 h-5 mr-1 animate-spin"></i> AutoSaving...';
        });
    } else if (status === 'saved') {
        [desktopDiv, mobileDiv].forEach(el => {
            if (!el) return;
            el.classList.add('bg-green-500', 'text-white');
            el.innerHTML = '<i data-lucide="check" class="w-5 h-5 mr-1"></i> Autosaved';
        });
    } else if (status === 'error') {
        [desktopDiv, mobileDiv].forEach(el => {
            if (!el) return;
            el.classList.add('bg-red-600', 'text-white');
            el.innerHTML = '<i data-lucide="alert-triangle" class="w-5 h-5 mr-1"></i> Error Saving!';
        });
    } else { // ready
        [desktopDiv, mobileDiv].forEach(el => {
            if (!el) return;
            el.classList.add('bg-gray-300', 'text-gray-700');
            el.innerHTML = 'Ready for Input';
        });
    }
    // Re-render lucide icons
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
}


/**
 * Shows a temporary floating alert message.
 * @param {string} message The message content.
 * @param {string} type 'success', 'error', or 'info'.
 */
export function showAutosaveMessage(message, type) {
    const element = autosaveMessageContainer.querySelector('#autosave-message');
    if (!element) return;

    // Remove existing styling classes for a clean slate
    element.classList.remove('bg-red-600', 'text-white', 'bg-green-600', 'bg-blue-600', 'visible', 'p-3', 'rounded-lg', 'shadow-xl');

    element.textContent = message;

    // Apply color classes
    if (type === 'error') element.classList.add('bg-red-600', 'text-white');
    else if (type === 'success') element.classList.add('bg-green-600', 'text-white');
    else element.classList.add('bg-blue-600', 'text-white');

    element.classList.add('p-3', 'rounded-lg', 'shadow-xl');

    // Make it visible
    element.classList.add('visible');

    // Hide it after 5 seconds
    setTimeout(() => {
        element.classList.remove('visible');
    }, 5000);
}

// ====================================================================
// === AUTOSAVE LOGIC =================================================
// ====================================================================

/**
 * Clears any pending autosave and starts a new one (Debounce logic).
 * * @param {HTMLElement} formElement The <form> element being monitored.
 * @param {function} submitFormCallback The function that handles the actual API submission.
 * @param {boolean} [shouldIgnoreEmpty=true] If true, prevents saving if all non-hidden inputs are empty.
 */
export function resetAutosaveTimer(formElement, submitFormCallback, shouldIgnoreEmpty = true) {
    updateAutosaveStatus('saving');
    clearTimeout(autosaveTimer);

    // Determine if notification should be shown (cooldown check)
    const shouldNotify = (Date.now() - lastNotificationTime) > AUTOSAVE_NOTIFICATION_COOLDOWN;

    autosaveTimer = setTimeout(() => {
        let shouldSave = true;

        if (shouldIgnoreEmpty) {
            // Check if all fields are empty (excluding hidden inputs and BMI readout)
            const allInputsEmpty = Array.from(formElement.elements)
                .filter(e => e.type !== 'hidden' && e.name !== 'bmi' && e.type !== 'button')
                .every(e => e.value.trim() === '' || e.value === '0');

            if (allInputsEmpty) {
                shouldSave = false;
            }
        }

        if (shouldSave) {
            // Trigger save and pass the notification flag based on cooldown check
            submitFormCallback(shouldNotify).then(success => {
                if (success) {
                    updateAutosaveStatus('saved');
                    if (shouldNotify) {
                        lastNotificationTime = Date.now(); // Update cooldown time
                        // Use a short delay so the status indicator can change state first
                        setTimeout(() => showAutosaveMessage('Autosaved!', 'success'), 100);
                    }
                } else {
                    updateAutosaveStatus('error');
                    // showAutosaveMessage already called by submitFormCallback on error
                }
            });
        } else {
            // If ignored because empty, restore 'ready' state
            updateAutosaveStatus('ready');
        }
    }, AUTOSAVE_DELAY);
}

// ====================================================================
// === MODULE INITIALIZATION ==========================================
// ====================================================================

/**
 * Initializes the common DOM element references. Must be called once on DOMContentLoaded.
 */
export function initAutosaveManager() {
    autosaveStatusDesktop = document.getElementById('autosave-status-desktop');
    autosaveMessageContainer = document.getElementById('autosave-message-container');
    // Note: Mobile status uses the same ID inside a different wrapper,
    // but the main function is referenced by checking the standard '#autosave-status' inside the mobile FAB.

    // If a common message container doesn't exist, create it dynamically
    if (!autosaveMessageContainer) {
        console.error("Missing critical DOM element: #autosave-message-container");
    }

    // Set initial status
    updateAutosaveStatus('ready');
}

/**
 * Attaches the autosave listener logic to all relevant form fields.
 * * @param {HTMLElement} formElement The <form> element to monitor.
 * @param {function} submitFormCallback The page-specific function to call when a save is triggered.
 * @param {function} [onInputChangeCallback=null] An optional page-specific function to run on every input change (e.g., BMI calculation).
 * @param {function} [inputFilterCallback=null] An optional page-specific function to run on every input change for filtering (e.g., Blood Pressure).
 */
export function attachAutosaveListeners(formElement, submitFormCallback, onInputChangeCallback = null, inputFilterCallback = null) {
    if (!formElement) {
        console.error("Cannot attach listeners: form element is null.");
        return;
    }

    // Attach the debounce logic to input and change events for all relevant inputs
    Array.from(formElement.elements).forEach(element => {
        // Exclude hidden inputs, button/submit types, and read-only fields (like BMI/readouts)
        if (element.type !== 'hidden' && element.type !== 'button' && element.readOnly !== true) {

            if (inputFilterCallback) {
                // Check if the element should use the specialized filter (like BP)
                if (element.id === 'blood_pressure') {
                    element.addEventListener('input', inputFilterCallback);
                    return; // Skip standard listener for filtered inputs
                }
            }

            // Standard debounce listener
            element.addEventListener('input', () => {
                // Run page-specific change logic first (e.g., BMI calculation)
                if (onInputChangeCallback) {
                    onInputChangeCallback();
                }
                // Then reset the autosave timer
                resetAutosaveTimer(formElement, submitFormCallback);
            });

            // Use 'change' event as a fallback/redundancy for select/radio buttons
            element.addEventListener('change', () => {
                resetAutosaveTimer(formElement, submitFormCallback);
            });
        }
    });
}