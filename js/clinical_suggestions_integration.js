/**
 * clinical_suggestions_integration.js
 * Handles the Dedicated Clinical Suggestions Modal.
 * 1. Fetches data from API.
 * 2. Populates the custom modal.
 * 3. Inserts selected text into the active Quill editor.
 */

(function() {
    'use strict';

    console.log('Initializing Dedicated Clinical Suggestions Modal...');

    const modalId = 'clinicalSuggestionsModal';
    const sidebarId = 'suggestions-sidebar';
    const contentId = 'suggestions-content';
    let clinicalSuggestions = null;
    let activeSection = null; // 'assessment' or 'plan'

    // --- 1. Fetch Data ---
    function loadSuggestions() {
        fetch('api/get_suggestions_for_notes.php', {
            credentials: 'include'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    clinicalSuggestions = data.suggestions;
                    console.log(`Loaded ${Object.keys(clinicalSuggestions).length} suggestion categories.`);
                }
            })
            .catch(err => console.error('Error loading suggestions:', err));
    }

    // --- 2. Open Modal ---
    function openSuggestionsModal(section) {
        activeSection = section;
        const modal = document.getElementById(modalId);
        if (!modal) return;

        if (!clinicalSuggestions) {
            alert('Suggestions are still loading... please try again in a moment.');
            loadSuggestions();
            return;
        }

        renderSidebar();
        modal.classList.remove('hidden');
        modal.classList.add('flex'); // Flex for centering

        // Select first category by default
        const firstCat = Object.keys(clinicalSuggestions)[0];
        if (firstCat) renderCategoryContent(firstCat);
    }

    // --- 3. Render Sidebar ---
    function renderSidebar() {
        const sidebar = document.getElementById(sidebarId);
        sidebar.innerHTML = '';

        Object.keys(clinicalSuggestions).forEach(category => {
            const div = document.createElement('div');
            div.className = 'suggestion-category';
            div.textContent = category;
            div.dataset.category = category;
            div.onclick = () => renderCategoryContent(category);
            sidebar.appendChild(div);
        });
    }

    // --- 4. Render Content ---
    function renderCategoryContent(category) {
        // Highlight active tab
        document.querySelectorAll('.suggestion-category').forEach(el => {
            el.classList.toggle('active', el.dataset.category === category);
        });

        const content = document.getElementById(contentId);
        content.innerHTML = '';

        const items = clinicalSuggestions[category] || [];

        // Section Header
        const h4 = document.createElement('h4');
        h4.className = 'text-lg font-bold text-gray-700 mb-4 border-b pb-2';
        h4.textContent = category;
        content.appendChild(h4);

        if (items.length === 0) {
            content.innerHTML += '<p class="text-gray-400 italic">No suggestions available.</p>';
            return;
        }

        items.forEach(text => {
            const card = document.createElement('div');
            card.className = 'suggestion-item flex justify-between items-center group';

            const span = document.createElement('span');
            span.textContent = text;
            span.className = 'text-sm text-gray-700';

            const icon = document.createElement('i');
            // Using lucide icon class if available, or simple text
            icon.className = 'text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity font-bold';
            icon.textContent = '+ Insert';

            card.appendChild(span);
            card.appendChild(icon);

            card.onclick = () => insertSuggestion(text);
            content.appendChild(card);
        });
    }

    // --- 5. Insert Logic ---
    function insertSuggestion(text) {
        const modal = document.getElementById(modalId);

        // Determine which editor to use
        let editorInstance = null;
        if (window.quillEditors) {
            if (activeSection === 'plan' && window.quillEditors.plan) {
                editorInstance = window.quillEditors.plan;
            } else if (activeSection === 'assessment' && window.quillEditors.assessment) {
                editorInstance = window.quillEditors.assessment;
            }
        }

        if (editorInstance) {
            // Insert text at current cursor position or end
            const length = editorInstance.getLength();
            const selection = editorInstance.getSelection();
            const index = selection ? selection.index : length;

            // Insert with a bullet point if it's a list-heavy section, or just text
            editorInstance.insertText(index, `\n• ${text}`);

            // Show feedback
            if (typeof window.showFloatingAlert === 'function') {
                window.showFloatingAlert('Suggestion inserted', 'success', 1500);
            }

            // Close modal after insert? Optional. Let's keep it open for multiple inserts.
            // modal.classList.add('hidden');
        } else {
            alert('Could not find active editor for ' + activeSection);
        }
    }

    // --- 6. Event Listeners ---
    document.addEventListener('DOMContentLoaded', function() {
        loadSuggestions();

        // Close Button
        document.getElementById('closeSuggestionsModal').addEventListener('click', () => {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        });

        // Click outside to close
        document.getElementById(modalId).addEventListener('click', (e) => {
            if (e.target === document.getElementById(modalId)) {
                e.target.classList.add('hidden');
                e.target.classList.remove('flex');
            }
        });

        // Delegate Click for the NEW buttons
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.clinical-suggestions-btn');
            if (btn) {
                e.preventDefault();
                const section = btn.dataset.targetSection; // 'plan' or 'assessment'
                openSuggestionsModal(section);
            }
        });
    });

})();