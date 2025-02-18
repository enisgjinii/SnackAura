</div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (necessary for DataTables and SweetAlert) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- DataTables JS and Extensions -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.1/js/buttons.colVis.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- jQuery UI -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>

<!-- Sidebar Toggle Script -->
<script>
    $(document).ready(function() {
        // Sidebar toggle functionality
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('collapsed');
            const isCollapsed = $('#sidebar').hasClass('collapsed');
            document.cookie = "sidebar_collapsed=" + isCollapsed + "; path=/; max-age=" + (60 * 60 * 24 * 30);
        });

        // Close sidebar on small screens when a link is clicked
        $('#sidebar .nav-link').on('click', function() {
            if ($(window).width() <= 768) {
                $('#sidebar').removeClass('active');
            }
        });

        // Responsive sidebar toggle
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