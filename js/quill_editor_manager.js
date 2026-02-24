// Filename: ec/js/quill_editor_manager.js
// Purpose: Manages the initialization and interaction with Quill editors.
// UPDATED: To include setFieldContent, getFieldContent, and clearFieldContent
// UPDATED: To accept an array of editor IDs instead of hard-coded values.

// Ensure the global object exists
window.quillEditors = window.quillEditors || {};

/**
 * Initializes the Quill editors for the given IDs.
 * @param {string[]} editorIds - An array of editor IDs (e.g., ['subjective', 'chief_complaint'])
 */
function initializeQuillEditors(editorIds = []) {
    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote'],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'header': [1, 2, 3, 4, false] }],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'font': [] }],
        [{ 'align': [] }],
        ['clean']
    ];
    editorIds.forEach(id => {
        // Use the standard container ID format: e.g., "subjective-editor-container"
        const containerSelector = `#${id}-editor-container`;
        const containerElement = document.querySelector(containerSelector);

        if (containerElement) {
            try {
                // Check if editor already exists
                if (!window.quillEditors[id]) {
                    window.quillEditors[id] = new Quill(containerSelector, {
                        modules: {
                            toolbar: toolbarOptions
                        },
                        theme: 'snow'
                    });
                }
            } catch (e) {
                console.error(`Failed to initialize Quill editor for ${id}:`, e);
                containerElement.innerHTML = `<p class="text-red-500">Error loading editor.</p>`;
            }
        } else {
            // This warning is expected for 'chief_complaint' if the ID is wrong, but let's check
            console.warn(`Quill container not found: ${containerSelector}`);
        }
    });
}

/**
 * --- NEW FUNCTION ---
 * Sets the content of a specific Quill editor.
 * @param {string} id - The ID of the editor (e.g., 'subjective').
 * @param {string} content - The HTML or text content to set.
 */
function setFieldContent(id, content) {
    const quill = window.quillEditors[id];
    if (quill) {
        // Check if content is HTML or plain text
        if (/<[a-z][\s\S]*>/i.test(content)) {
            quill.root.innerHTML = content || '';
        } else {
            quill.setText(content || '');
        }
    } else {
        console.warn(`setFieldContent: Quill editor '${id}' not found.`);
    }
}




/**
 * --- NEW FUNCTION ---
 * Gets the HTML content from a specific Quill editor.
 * @param {string} id - The ID of the editor (e.g., 'subjective').
 * @returns {string} The HTML content of the editor.
 */
function getFieldContent(id) {
    const quill = window.quillEditors[id];
    if (quill) {
        let content = quill.root.innerHTML;
        // Quill considers an empty editor to be '<p><br></p>'
        if (content === '<p><br></p>') {
            return '';
        }
        return content;
    } else {
        console.warn(`getFieldContent: Quill editor '${id}' not found.`);
        return '';
    }
}

/**
 * --- NEW FUNCTION ---
 * Clears the content of a specific Quill editor.
 * @param {string} id - The ID of the editor (e.g., 'subjective').
 */
function clearFieldContent(id) {
    const quill = window.quillEditors[id];
    if (quill) {
        quill.setText(''); // Clears content and history
    } else {
        console.warn(`clearFieldContent: Quill editor '${id}' not found.`);
    }
}