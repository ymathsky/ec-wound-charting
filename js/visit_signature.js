/**
 * ec/js/visit_signature.js
 * Handles the signature pad logic on the visit_notes.php page.
 * Provides drawing capabilities on the canvas element.
 */

document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signature-pad');
    const clearButton = document.getElementById('clear-signature');
    const signatureInput = document.getElementById('signature_data');

    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    // Function to get correct coordinates relative to canvas
    function getMousePos(canvas, evt) {
        const rect = canvas.getBoundingClientRect();
        // Account for CSS scaling if canvas display size differs from actual size
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        return {
            x: (evt.clientX - rect.left) * scaleX,
            y: (evt.clientY - rect.top) * scaleY
        };
    }

    // Start drawing
    function startDrawing(e) {
        isDrawing = true;
        const pos = getMousePos(canvas, e.touches ? e.touches[0] : e);
        lastX = pos.x;
        lastY = pos.y;
    }

    // Draw
    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault(); // Prevent scrolling on touch devices

        const pos = getMousePos(canvas, e.touches ? e.touches[0] : e);

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.stroke();

        lastX = pos.x;
        lastY = pos.y;
    }

    // Stop drawing
    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            // Update hidden input when drawing stops
            // Note: We rely on the save handler to get the final data, but this can be used for live updates if needed.
        }
    }

    // Event Listeners
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    // Touch support
    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);

    // Clear Signature
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (signatureInput) signatureInput.value = '';
        });
    }

    // Optional: Initialize blank
    ctx.fillStyle = "#ffffff";
    // We don't fillRect here to keep it transparent or simple lines,
    // but ensuring it's clean on load is good practice.
    ctx.clearRect(0, 0, canvas.width, canvas.height);
});