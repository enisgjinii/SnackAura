</div>
</div>

<!-- Bootstrap JS and dependencies (Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (optional, for easier DOM manipulation) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script>
    $(document).ready(function() {
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('collapsed');
            // Save the state in a cookie
            const isCollapsed = $('#sidebar').hasClass('collapsed');
            document.cookie = "sidebar_collapsed=" + isCollapsed + "; path=/; max-age=" + (60 * 60 * 24 * 30);
        });

        // Optional: Close sidebar on small screens when a link is clicked
        $('#sidebar .nav-link').on('click', function() {
            if ($(window).width() <= 768) {
                $('#sidebar').removeClass('active');
            }
        });

        // Toggle sidebar for responsive design
        function toggleSidebar() {
            if ($(window).width() <= 768) {
                $('#sidebar').addClass('active');
            } else {
                $('#sidebar').removeClass('active');
            }
        }

        toggleSidebar();
        $(window).resize(toggleSidebar);
    });
</script>
</body>

</html>