<?php
// admin/includes/footer.php
?>
</div> <!-- End of content -->
</div> <!-- End of d-flex -->

<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (optional, for easier DOM manipulation) -->
<!-- Custom JS -->
<script src="assets/js/scripts.js"></script>
<script>
    $(document).ready(function() {
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('collapsed');
        });
    });
</script>
</body>

</html>