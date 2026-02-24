/**
 * Filename: ec/js/soap_checklist_loader.js
 * Purpose: Fetches SOAP checklist items and handles dropdown interactions manually
 * to bypass potential Bootstrap/Flowbite loading errors.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[SOAP Loader] Initializing...');

    // 1. Load Data
    loadSoapChecklist();

    // 2. Initialize Manual Dropdown Logic (Fix for blocked libraries)
    initManualDropdownToggles();
});

/**
 * Manually handles opening/closing of dropdowns using the data-dropdown-toggle attribute.
 * This ensures menus work even if Bootstrap/Flowbite JS is blocked.
 */
function initManualDropdownToggles() {
    const triggers = document.querySelectorAll('[data-dropdown-toggle]');

    triggers.forEach(btn => {
        // Remove old listeners to prevent duplicates if re-initialized
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const targetId = newBtn.getAttribute('data-dropdown-toggle');
            const targetMenu = document.getElementById(targetId);

            if (targetMenu) {
                // Close all other open menus first
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.add('hidden');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('hidden');
                console.log(`[SOAP Loader] Toggled menu: ${targetId}`);
            }
        });
    });

    // Close menus when clicking anywhere else on the page
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('[data-dropdown-toggle]')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
}

async function loadSoapChecklist() {
    try {
        const response = await fetch('api/get_soap_checklist.php');
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

        const data = await response.json();
        console.log('[SOAP Loader] Data received:', data);

        if (data.success && data.checklist) {
            populateQuickInsertMenu('subjective', data.checklist.subjective);
            populateQuickInsertMenu('objective', data.checklist.objective);
            populateQuickInsertMenu('assessment', data.checklist.assessment);
            populateQuickInsertMenu('plan', data.checklist.plan);
        } else {
            showErrorInMenus('No items found.');
        }
    } catch (error) {
        console.error('[SOAP Loader] Error:', error);
        showErrorInMenus('Error loading snippets.');
    }
}

function showErrorInMenus(msg) {
    ['subjective', 'objective', 'assessment', 'plan'].forEach(section => {
        const el = document.getElementById(`${section}-quick-insert-menu`);
        if (el) el.innerHTML = `<span class="block px-4 py-2 text-xs text-red-500">${msg}</span>`;
    });
}

function populateQuickInsertMenu(section, categories) {
    const containerId = `${section}-quick-insert-menu`;
    const container = document.getElementById(containerId);

    if (!container) return;

    container.innerHTML = '';

    if (!categories || Object.keys(categories).length === 0) {
        container.innerHTML = '<span class="block px-4 py-2 text-xs text-gray-400">No snippets.</span>';
        return;
    }

    for (const [categoryName, items] of Object.entries(categories)) {
        const catHeader = document.createElement('div');
        catHeader.className = 'px-3 py-1 text-xs font-bold text-gray-500 uppercase bg-gray-100 border-b border-t mt-1';
        catHeader.textContent = categoryName;
        container.appendChild(catHeader);

        if (Array.isArray(items)) {
            items.forEach(itemText => {
                const itemLink = document.createElement('div'); // Div instead of A to prevent nav
                itemLink.className = 'dropdown-item block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer transition-colors';
                itemLink.textContent = itemText;

                itemLink.addEventListener('click', (e) => {
                    e.stopPropagation(); // Keep menu open
                    insertTextIntoEditor(section, itemText);

                    // Visual feedback
                    itemLink.classList.add('bg-green-50', 'text-green-700');
                    setTimeout(() => itemLink.classList.remove('bg-green-50', 'text-green-700'), 300);
                });

                container.appendChild(itemLink);
            });
        }
    }
}

function insertTextIntoEditor(section, text) {
    if (!window.quillEditors || !window.quillEditors[section]) {
        console.error(`[SOAP Loader] Editor for ${section} not found.`);
        return;
    }

    const editor = window.quillEditors[section];
    editor.focus();

    const range = editor.getSelection() || { index: editor.getLength() - 1, length: 0 };
    editor.insertText(range.index, text + ' ');
    editor.setSelection(range.index + text.length + 1);

    console.log(`[SOAP Loader] Inserted: "${text}"`);
}