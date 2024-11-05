<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        /* Custom styles for the sidebar */
        body {
            overflow-x: hidden;
        }

        /* Sidebar styling */
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem;
            -webkit-transition: margin .25s ease-out;
            -moz-transition: margin .25s ease-out;
            -o-transition: margin .25s ease-out;
            transition: margin .25s ease-out;
        }

        #sidebar-wrapper .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
        }

        #sidebar-wrapper .list-group {
            width: 15rem;
        }

        /* Toggled sidebar */
        #wrapper.toggled #sidebar-wrapper {
            margin-left: 0;
        }

        /* Page content styling */
        #page-content-wrapper {
            min-width: 80vw;
        }

        /* Toggled page content */
        #wrapper.toggled #page-content-wrapper {
            margin-left: 0rem;
        }

        /* Responsive adjustments */
        @media (min-width: 768px) {
            #sidebar-wrapper {
                margin-left: 0;
            }

            #page-content-wrapper {
                margin-left: 0rem;
                min-width: 100vw;
            }

            #wrapper.toggled #sidebar-wrapper {
                margin-left: -15rem;
            }

            #wrapper.toggled #page-content-wrapper {
                margin-left: 0;
            }
        }

        /* Styling for active navigation links */
        .list-group-item-action.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        /* Hover effects for navigation links */
        .list-group-item-action:hover {
            background-color: #343a40;
            color: white;
        }

        /* Adjust content padding when sidebar is present */
        #page-content-wrapper {
            padding: 20px;
        }

        /* Smooth transition for the sidebar */
        #sidebar-wrapper,
        #page-content-wrapper {
            transition: margin .25s ease-out;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar-->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Admin Dashboard</div>
            <div class="list-group list-group-flush">
                <?php
                // Determine the current page
                $current_page = basename($_SERVER['PHP_SELF']);
                ?>
                <a href="products.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">Products</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <a href="categories.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">Categories</a>
                    <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">Settings</a>
                    <a href="banners.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo ($current_page == 'banners.php') ? 'active' : ''; ?>">Banners</a>
                    <a href="extras.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo ($current_page == 'extras.php') ? 'active' : ''; ?>">Extras</a>
                    <!-- Add more admin-specific links here -->
                <?php endif; ?>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content-->
        <div id="page-content-wrapper">
            <!-- Top Navigation Bar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle">☰</button>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION["username"]); ?>)</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container-fluid">