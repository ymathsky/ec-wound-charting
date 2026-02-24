// Autosave endpoint config — use relative paths (no leading slash)
// This ensures requests are made relative to the current page path (e.g. /ec/api/...)
window.AUTOSAVE_ENDPOINTS = window.AUTOSAVE_ENDPOINTS || {
        SAVE: 'api/save_draft.php',
        LOAD: 'api/load_draft.php',
        DELETE: 'api/delete_draft.php'
    };