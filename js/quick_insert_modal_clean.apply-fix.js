// Replacement snippet for applyAndClose() - groups selected items and inserts compact HTML.
// Replace the applyAndClose function in js/quick_insert_modal_clean.js with this version.

function applyAndClose() {
    // build chosen items array preserving category grouping order
    const chosenByCat = []; // [{ title, items: [ {label, html} ] }, ...]
    Object.keys(state.data).forEach(catKey => {
        const cat = state.data[catKey];
        const chosen = (cat.items || []).filter(it => state.selected.has(it.id));
        if (chosen.length) {
            chosenByCat.push({
                title: cat.title || catKey,
                items: chosen.map(it => ({ label: it.label, html: it.html }))
            });
        }
    });

    if (chosenByCat.length === 0) return;

    // Build compact HTML string:
    // - one container .quick-inserted
    // - for each category a small header (h4) and a single ul with li elements
    // - li text only (use provided html if you must, but strip wrapper block margins)
    const fragments = [];
    chosenByCat.forEach(cat => {
        // sanitize/normalize item html if present, fallback to escaped label
        const lis = cat.items.map(it => {
            const contentHtml = (it.html && String(it.html).trim()) ? it.html.trim() : ('<span>' + escapeHtml(it.label) + '</span>');
            // ensure li has no extra margins by wrapping content and letting CSS handle spacing
            return '<li>' + contentHtml + '</li>';
        });
        fragments.push('<div class="quick-inserted" data-quick-insert="true">' +
            '<h4>' + escapeHtml(cat.title) + '</h4>' +
            '<ul>' + lis.join('') + '</ul>' +
            '</div>');
    });

    // Concatenate into one HTML blob with small separator between categories
    const blob = fragments.join('\n');

    // Insert into Quill:
    const editor = findTargetQuill();
    if (!editor) {
        console.warn('No editor found to insert into');
        closeModal();
        return;
    }

    try {
        // Prefer helper if available
        if (typeof window.insertHtmlIntoQuillAtCursor === 'function') {
            // Insert as single paste to preserve clean structure and undo grouping
            window.insertHtmlIntoQuillAtCursor(editor, blob);
        } else if (editor.clipboard && typeof editor.clipboard.dangerouslyPasteHTML === 'function') {
            const sel = editor.getSelection();
            const pos = (sel && typeof sel.index === 'number') ? sel.index : editor.getLength();
            editor.clipboard.dangerouslyPasteHTML(pos, blob, 'user');
            // move cursor after inserted content (approx): set selection to end of inserted block
            const newPos = (typeof pos === 'number') ? pos + 1 : editor.getLength();
            try { editor.setSelection(newPos, 0, 'user'); } catch (e) { /* ignore */ }
        } else {
            // Fallback: insert plain text lines for each selected item
            chosenByCat.forEach(cat => {
                editor.insertText(editor.getLength(), '\n' + cat.title + '\n', { bold: true });
                cat.items.forEach(it => {
                    editor.insertText(editor.getLength(), '- ' + it.label + '\n');
                });
            });
        }

        // dispatch event and close
        window.dispatchEvent(new CustomEvent('quickinsert:applied', { detail: { items: Array.from(state.selected) } }));
    } catch (err) {
        console.error('quick-insert apply error', err);
    } finally {
        closeModal();
    }
}

// Small utility escapeHtml used in this file (if not already defined globally)
function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; });
}