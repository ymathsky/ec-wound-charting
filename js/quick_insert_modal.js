// js/quick_insert_modal_fixes.js
// Robust runtime fix to keep "Insert Selected" enabled state in sync with checklist selections.
//
// - Locates the Insert Selected button by id or by text.
// - Watches modal body for changes and attaches handlers to checkbox elements and rows.
// - Uses quickInsertClean.getState() if available, falling back to DOM heuristics.
// - Safe to include after quick_insert_modal_clean.js (idempotent).

(function(){
    'use strict';

    const MODAL_ID = 'checklistModal';
    const MODAL_BODY_ID = 'checklistModalBody';
    const ITEM_ROW_SELECTOR = '.checklist-item-row';
    const CHECKBOX_SELECTOR = '.ci-checkbox';

    function findInsertButton(){
        let btn = document.getElementById('insertChecklistBtn');
        if(btn) return btn;
        btn = Array.from(document.querySelectorAll('button')).find(b => {
            const txt = (b.textContent||'').trim().toLowerCase();
            return txt.includes('insert selected') || txt === 'insert' || txt.includes('insert checked') ;
        });
        return btn || null;
    }

    function getSelectedCount(){
        try {
            if (window.quickInsertClean && typeof window.quickInsertClean.getState === 'function') {
                const s = window.quickInsertClean.getState();
                if (s && Array.isArray(s.selected)) return s.selected.length;
            }
        } catch(e){}
        try {
            const mb = document.getElementById(MODAL_BODY_ID) || document;
            const rows = mb.querySelectorAll(ITEM_ROW_SELECTOR);
            let n = 0;
            rows.forEach(r => {
                try {
                    const ac = r.getAttribute && r.getAttribute('aria-checked');
                    if (ac === 'true') { n++; return; }
                    if (r.classList.contains('selected') || r.classList.contains('is-selected')) { n++; return; }
                    // try finding a contained checkbox input
                    const cb = r.querySelector('input[type="checkbox"], .ci-checkbox');
                    if (cb) {
                        if ((cb.tagName === 'INPUT' && cb.checked) || cb.getAttribute('aria-checked') === 'true' || cb.classList.contains('checked')) n++;
                    }
                } catch(e) { /* ignore per-row errors */ }
            });
            return n;
        } catch(e) { return 0; }
    }

    function updateInsertButton(){
        const btn = findInsertButton();
        if(!btn) return;
        const n = getSelectedCount();
        const enabled = n > 0;
        try {
            btn.disabled = !enabled;
            btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            btn.classList.toggle('primary', enabled);
            btn.classList.toggle('disabled', !enabled);
        } catch(e){}
        // optional: update a selected-count indicator
        try {
            const selCountEl = document.querySelector(`#${MODAL_ID} .selected-count, #${MODAL_BODY_ID} .selected-count`);
            if(selCountEl) selCountEl.textContent = enabled ? `${n} item(s) selected` : '';
        } catch(e){}
    }

    // Attach click handler to checkbox-like nodes to ensure they update aria/class and trigger update
    function attachCheckboxHandlers(root){
        if(!root) root = document;
        try{
            const boxes = root.querySelectorAll('input[type="checkbox"], .ci-checkbox, .checkbox, .check-icon');
            boxes.forEach(cb => {
                if(cb.__qi_patched) return;
                cb.__qi_patched = true;
                cb.addEventListener('click', function(evt){
                    // allow native handler, then refresh
                    setTimeout(updateInsertButton, 8);
                }, true);
            });
        }catch(e){}
    }

    function attachRowClickHandlers(root){
        if(!root) root = document;
        try{
            const rows = root.querySelectorAll(ITEM_ROW_SELECTOR);
            rows.forEach(r => {
                if(r.__qi_row_patched) return;
                r.__qi_row_patched = true;
                r.addEventListener('click', function(evt){
                    // small delay to let row toggle state run
                    setTimeout(updateInsertButton, 8);
                }, true);
            });
        }catch(e){}
    }

    // Observe modal body for dynamic changes
    function observeModalBody(){
        const mb = document.getElementById(MODAL_BODY_ID);
        if(!mb) return false;
        attachCheckboxHandlers(mb);
        attachRowClickHandlers(mb);
        updateInsertButton();
        try{
            const mo = new MutationObserver((mutations)=>{
                let refresh = false;
                for(const m of mutations){
                    if(m.type === 'childList' && m.addedNodes && m.addedNodes.length) {
                        refresh = true;
                        m.addedNodes.forEach(node => {
                            if(node instanceof HTMLElement){
                                attachCheckboxHandlers(node);
                                attachRowClickHandlers(node);
                            }
                        });
                    } else if(m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'aria-checked')){
                        refresh = true;
                    }
                }
                if(refresh) setTimeout(updateInsertButton, 8);
            });
            mo.observe(mb, { childList:true, subtree:true, attributes:true, attributeFilter:['class','aria-checked'] });
        } catch(e){
            // fallback interval
            setInterval(updateInsertButton, 1000);
        }
        return true;
    }

    // Wait for modal to appear and then attach observers
    function waitForModal(retries = 12, delay = 200){
        const mb = document.getElementById(MODAL_BODY_ID);
        if(mb){ observeModalBody(); return; }
        if(retries<=0) return;
        setTimeout(()=> waitForModal(retries-1, delay), delay);
    }

    // If the modal element itself is added to DOM, try to attach once present
    function observeModalInsertion(){
        if(!window.MutationObserver) return;
        const mo = new MutationObserver((mutations)=>{
            for(const m of mutations){
                if(m.type==='childList' && m.addedNodes && m.addedNodes.length){
                    for(const n of m.addedNodes){
                        if(!(n instanceof HTMLElement)) continue;
                        if(n.id === MODAL_ID || n.querySelector && n.querySelector(`#${MODAL_BODY_ID}`)) {
                            // small defer
                            setTimeout(()=>{ waitForModal(); updateInsertButton(); }, 60);
                            return;
                        }
                    }
                }
            }
        });
        try{ mo.observe(document.body || document.documentElement, { childList:true, subtree:true }); }catch(e){}
    }

    // Init
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ()=>{ observeModalInsertion(); waitForModal(); });
    else { observeModalInsertion(); waitForModal(); }

    // Expose helper
    window.quickInsertFixes = Object.assign(window.quickInsertFixes || {}, {
        updateInsertButton,
        getSelectedCount
    });

})();