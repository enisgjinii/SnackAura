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
<script>
    // Adjust the path to your MP3 or WAV file:
    const orderSound = new Audio("alert.mp3");

    // Keep track of the last known count:
    let lastCount = 0;

    // Poll the server every X seconds (e.g., 5 seconds)
    setInterval(() => {
        fetch("./order_notifier.php")
            .then(response => response.json())
            .then(data => {
                // data.countNew is the number of orders with "New Order" status
                const currentCount = data.countNew ?? 0;

                // If there are *more* new orders than before, we can play a sound:
                // if (currentCount > lastCount) {
                //     // Play the notification sound:
                //     orderSound.play();
                // }

                // If you want the sound to play *continuously* as long as there's *any* "New Order",
                // you can do this check:
                if (currentCount > 0) {
                  orderSound.play();
                }

                lastCount = currentCount;
            })
            .catch(err => {
                console.error("Notifier error:", err);
            });
    }, 5000);
</script>


</body>

</html>