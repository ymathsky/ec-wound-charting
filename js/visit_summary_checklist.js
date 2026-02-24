/**
 * visit_summary_checklist.js
 * Logic for the Quick Insert Checklist Modal in visit_summary.php
 */

(function() {
    'use strict';

    // Helper to open a modal
    function openModal(modal, dialog) {
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        // Small delay to allow display:flex to apply before adding opacity class for transition
        setTimeout(() => {
            if (dialog) {
                dialog.classList.add('show-modal');
            }
        }, 10);
    }

    // Helper to close a modal
    function closeModal(modal, dialog) {
        if (!modal) return;
        if (dialog) {
            dialog.classList.remove('show-modal');
        }
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }, 200); // Match transition duration
    }

    // Expose globally if needed
    window.openModal = openModal;
    window.closeModal = closeModal;

    // Main function to open the checklist
    window.openChecklistModal = async function(section) {
        console.log("Opening checklist modal for:", section);
        window.activeChecklistSection = section;

        const modal = document.getElementById('checklistModal');
        const modalDialog = document.getElementById('checklistModalDialog');
        const title = document.getElementById('checklist-modal-title');
        const categoriesDiv = document.getElementById('checklist-categories');
        const itemsDiv = document.getElementById('checklist-items');

        if (!modal) { console.error("Checklist Modal not found!"); return; }

        // Theme Application (Simplified for summary view)
        const themeColors = {
            chief_complaint: 'theme-yellow',
            subjective: 'theme-blue',
            objective: 'theme-green',
            assessment: 'theme-orange',
            plan: 'theme-indigo',
            lab_orders: 'theme-teal',
            imaging_orders: 'theme-teal',
            skilled_nurse_orders: 'theme-teal'
        };
        const themeClass = themeColors[section] || 'theme-gray';

        // Reset classes
        if (modalDialog) {
            modalDialog.classList.remove('theme-yellow', 'theme-blue', 'theme-green', 'theme-orange', 'theme-indigo', 'theme-gray', 'theme-teal');
            modalDialog.classList.add(themeClass);
        }

        if (title) {
            const pretty = section.charAt(0).toUpperCase() + section.slice(1).replace('_',' ');
            title.innerHTML = `<span class="opacity-70 font-normal">Quick Insert:</span> ${pretty}`;
        }

        if (categoriesDiv) categoriesDiv.innerHTML = '<div class="flex justify-center p-4"><div class="ai-spinner border-gray-400"></div></div>';
        if (itemsDiv) itemsDiv.innerHTML = '';

        openModal(modal, modalDialog);

        try {
            const response = await fetch(`api/get_soap_checklist.php?section=${encodeURIComponent(section)}`);
            const json = await response.json();
            
            let dataToRender = [];
            if (json && json.checklist && json.checklist[section]) {
                dataToRender = json.checklist[section];
            } else if (json && typeof json === 'object') {
                dataToRender = json;
            }

            // Normalize data structure
            if (!Array.isArray(dataToRender)) {
                const converted = [];
                if (dataToRender && typeof dataToRender === 'object') {
                    Object.keys(dataToRender).forEach(k => {
                        if (k === 'success' || k === 'message') return;
                        converted.push({ category_name: k, items: dataToRender[k] });
                    });
                }
                dataToRender = converted;
            }

            renderChecklist(dataToRender, categoriesDiv, itemsDiv);

        } catch (e) {
            console.error("Error fetching checklist:", e);
            if (categoriesDiv) categoriesDiv.innerHTML = '<p class="text-red-500 p-4">Error loading checklist.</p>';
        }
    };

    function renderChecklist(data, categoriesDiv, itemsDiv) {
        if (!categoriesDiv || !itemsDiv) return;
        
        categoriesDiv.innerHTML = '';
        itemsDiv.innerHTML = '';

        if (!data || data.length === 0) {
            categoriesDiv.innerHTML = '<p class="p-4 text-gray-500">No items found.</p>';
            return;
        }

        // Render Categories
        data.forEach((cat, index) => {
            const catEl = document.createElement('div');
            catEl.className = `checklist-category ${index === 0 ? 'active' : ''}`;
            catEl.textContent = cat.category_name;
            catEl.dataset.index = index;
            catEl.onclick = () => {
                document.querySelectorAll('.checklist-category').forEach(el => el.classList.remove('active'));
                catEl.classList.add('active');
                renderItems(cat.items);
            };
            categoriesDiv.appendChild(catEl);
        });

        // Render Initial Items
        if (data.length > 0) {
            renderItems(data[0].items);
        }

        function renderItems(items) {
            itemsDiv.innerHTML = '';
            if (!items || items.length === 0) {
                itemsDiv.innerHTML = '<p class="text-gray-500">No items in this category.</p>';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'checklist-grid';

            items.forEach(item => {
                const label = document.createElement('label');
                label.className = 'checklist-item-label';
                
                // Handle object vs string items
                let displayValue = item;
                let actualValue = item;
                
                if (typeof item === 'object' && item !== null) {
                    displayValue = item.text || item.item_text || item.title || JSON.stringify(item);
                    actualValue = item.text || item.item_text || JSON.stringify(item);
                }

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'checklist-item-input';
                input.value = actualValue;

                const box = document.createElement('div');
                box.className = 'checklist-item-box';

                const text = document.createElement('span');
                text.className = 'checklist-item-text';
                text.textContent = displayValue;

                label.appendChild(input);
                label.appendChild(box);
                label.appendChild(text);
                grid.appendChild(label);
            });

            itemsDiv.appendChild(grid);
        }
    }

    // Insert Logic
    document.addEventListener('DOMContentLoaded', () => {
        const insertBtn = document.getElementById('insertChecklistBtn');
        const closeBtn = document.getElementById('closeChecklistBtn');
        const closeXBtn = document.getElementById('closeChecklistModalBtn');

        if (insertBtn) {
            insertBtn.addEventListener('click', () => {
                const selected = [];
                document.querySelectorAll('.checklist-item-input:checked').forEach(input => {
                    selected.push(input.value);
                });

                if (selected.length > 0) {
                    // Format as numbered list if multiple items, or just text if one?
                    // User requested numbered list format.
                    let textToInsert = '';
                    if (selected.length > 1) {
                        textToInsert = selected.map((item, index) => `${index + 1}. ${item}`).join('\n');
                        // Prepend newline for separation
                        textToInsert = '\n' + textToInsert;
                    } else {
                        textToInsert = selected[0];
                    }
                    insertTextToActiveEditor(textToInsert);
                }

                closeModal(document.getElementById('checklistModal'), document.getElementById('checklistModalDialog'));
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                closeModal(document.getElementById('checklistModal'), document.getElementById('checklistModalDialog'));
            });
        }

        if (closeXBtn) {
            closeXBtn.addEventListener('click', () => {
                closeModal(document.getElementById('checklistModal'), document.getElementById('checklistModalDialog'));
            });
        }

        // Delegate click for Quick Insert buttons
        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.quick-insert-btn');
            if (btn) {
                // Ensure the editor is in edit mode
                const container = btn.closest('.editable-container');
                if (container) {
                    // If not already editing, start editing
                    const view = container.querySelector('.editable-view');
                    if (view && !view.classList.contains('hidden')) {
                        // Trigger the edit button click to switch modes
                        const editBtn = container.querySelector('.edit-btn');
                        if (editBtn) editBtn.click(); // This relies on existing logic to start edit
                    }
                    
                    // Set active quill if possible (will be handled by startEdit logic update)
                }

                const section = btn.dataset.section;
                if (section) {
                    window.openChecklistModal(section);
                }
            }
        });
    });

    function insertTextToActiveEditor(text) {
        if (window.activeQuill) {
            const range = window.activeQuill.getSelection(true);
            if (range) {
                window.activeQuill.insertText(range.index, text + ' ');
                window.activeQuill.setSelection(range.index + text.length + 1);
            } else {
                // Append if no selection
                const length = window.activeQuill.getLength();
                window.activeQuill.insertText(length - 1, text + ' ');
            }
        } else {
            console.warn("No active Quill editor found.");
            // Fallback: try to find the visible quill editor in the active container
            const visibleEditor = document.querySelector('.editable-input:not(.hidden) .quill-editor');
            if (visibleEditor) {
                // This is tricky because we need the Quill instance, not the element.
                // In visit_summary.php, instances are stored in a Map or created on the fly.
                // We rely on window.activeQuill being set correctly.
                alert("Please click inside the text editor first.");
            }
        }
    }

})();
