// Filename: ec/js/shoreline_checklist.js
// Purpose: Handle interactions and saving for the Skin Graft Checklist
// UPDATED: Added Graft Product Library and auto-population logic.

document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('saveChecklistBtn');
    const form = document.getElementById('graftChecklistForm');
    const alertContainer = document.getElementById('alert-container');

    // --- GRAFT PRODUCT LIBRARY ---
    const GRAFT_LIBRARY = [
        { name: "NuShield", cpt: "15271", qcode: "Q4160" },
        { name: "Apligraf", cpt: "15275", qcode: "Q4101" },
        { name: "Dermagraft", cpt: "15275", qcode: "Q4106" },
        { name: "EpiFix", cpt: "15271", qcode: "Q4131" },
        { name: "Grafix Core", cpt: "15271", qcode: "Q4140" },
        { name: "Grafix Prime", cpt: "15271", qcode: "Q4140" },
        { name: "Affinity", cpt: "15271", qcode: "Q4159" },
        { name: "PuraPly", cpt: "15271", qcode: "Q4196" },
        { name: "PuraPly AM", cpt: "15271", qcode: "Q4196" }
    ];

    // --- Populate Datalist ---
    const datalist = document.getElementById('graft_products');
    const productNameInput = document.getElementById('graft_product_name');
    const cptInput = document.getElementById('graft_cpt_code');
    const qCodeInput = document.getElementById('graft_q_code');

    if (datalist) {
        GRAFT_LIBRARY.forEach(product => {
            const option = document.createElement('option');
            option.value = product.name;
            datalist.appendChild(option);
        });
    }

    // --- Auto-Populate Codes on Selection ---
    if (productNameInput) {
        productNameInput.addEventListener('input', function() {
            const selectedName = this.value.trim();
            const matchedProduct = GRAFT_LIBRARY.find(p => p.name.toLowerCase() === selectedName.toLowerCase());

            if (matchedProduct) {
                if (cptInput && !cptInput.value) cptInput.value = matchedProduct.cpt;
                if (qCodeInput && !qCodeInput.value) qCodeInput.value = matchedProduct.qcode;
            }
        });
    }

    // --- Smart Billing Calculator Inputs ---
    const productSizeInput = document.getElementById('graft_product_size');
    const usedInput = document.getElementById('graft_sqcm_used');
    const discardedInput = document.getElementById('graft_sqcm_discarded');
    const jwCheckbox = document.getElementById('graft_check_jw_modifier');

    // Helper to show alert
    function showAlert(message, type = 'success') {
        const colorClass = type === 'success' ? 'bg-green-100 text-green-800 border-green-400' : 'bg-red-100 text-red-800 border-red-400';
        alertContainer.innerHTML = `
            <div class="p-4 rounded-md border ${colorClass} flex items-center justify-between shadow-sm">
                <div class="flex items-center">
                    <span class="font-semibold mr-2">${type === 'success' ? 'Success:' : 'Error:'}</span> ${message}
                </div>
                <button onclick="this.parentElement.remove()" class="text-sm font-bold">&times;</button>
            </div>
        `;
        if (type === 'success') {
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 3000);
        }
    }

    // --- Calculation Logic ---
    function calculateWastage() {
        const totalSize = parseFloat(productSizeInput.value) || 0;
        const usedAmount = parseFloat(usedInput.value) || 0;

        if (totalSize > 0) {
            if (usedAmount > totalSize) {
                // Error: Used cannot exceed product size
                showAlert("Amount Used cannot exceed Total Product Area!", "error");
                discardedInput.value = "0.00";
                usedInput.value = totalSize; // Cap it
                return;
            }

            const discarded = totalSize - usedAmount;
            discardedInput.value = discarded.toFixed(2); // Set discarded amount

            // Auto-check JW modifier if discarded > 0
            if (discarded > 0) {
                jwCheckbox.checked = true;
            } else {
                jwCheckbox.checked = false;
            }
        } else {
            // If no product size, we can't calculate discarded automatically,
            // but we leave manual entry open if needed.
        }
    }

    // Attach listeners
    if (productSizeInput && usedInput) {
        productSizeInput.addEventListener('input', calculateWastage);
        usedInput.addEventListener('input', calculateWastage);
    }


    // --- Saving Logic ---
    if (saveBtn) {
        saveBtn.addEventListener('click', async function(e) {
            e.preventDefault();

            // Change button state to loading
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.innerHTML = `<span class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full mr-2"></span> Saving...`;
            saveBtn.disabled = true;

            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            // Ensure checkbox values are captured (FormData usually skips unchecked)
            // But for your API, missing checkbox means 0, which is handled by intval()

            try {
                const response = await fetch('api/save_graft_checklist.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    const result = await response.json();
                    if (result.success) {
                        showAlert('Checklist saved successfully!');
                    } else {
                        showAlert(result.message || 'Failed to save checklist.', 'error');
                    }
                } else {
                    const text = await response.text();
                    console.error("Server Error:", text);
                    showAlert('Server error occurred. Check console.', 'error');
                }

            } catch (error) {
                console.error('Error saving checklist:', error);
                showAlert('Network error. Please try again.', 'error');
            } finally {
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        });
    }
});