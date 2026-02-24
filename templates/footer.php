<?php
// Filename: templates/footer.php
?>
</main>
</div>
</div>
<!-- End of MAIN APP CONTAINER -->

<!-- Ensure lucide icons are rendered -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
<!-- Smart Voice Logic (Global) -->
<script src="js/smart_command_logic.js?v=<?php echo time(); ?>"></script>

</body>
</html>