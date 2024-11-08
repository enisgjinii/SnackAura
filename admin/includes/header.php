<?php
// admin/includes/header.php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Restaurant Delivery</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons (Optional) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        /* Custom styles for the sidebar */
        body {
            overflow-x: hidden;
        }

        /* Sidebar */
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            transition: all 0.3s;
            background: #343a40;
            color: #fff;
        }

        #sidebar.collapsed {
            margin-left: -250px;
        }

        #sidebar .sidebar-header {
            padding: 20px;
            background: #3c4043;
            text-align: center;
        }

        #sidebar ul.components {
            padding: 20px 0;
        }

        #sidebar ul li {
            padding: 10px 20px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
        }

        #sidebar ul li a {
            color: #d1d1d1;
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 5px;
            /* Remove underline */
            width: 100%;
        }

        #sidebar ul li a:hover {
            color: #ffffff;
            background: #575757;
            text-decoration: none;
            /* Ensure no underline on hover */
            border-radius: 4px;
        }

        /* Active link styling with left border */
        #sidebar ul li.active>a {
            color: #fff;
            background: #007bff;
            border-left: 4px solid #ffffff;
            /* White left border */
            padding-left: 16px;
            /* Adjust padding to accommodate border */
            border-radius: 4px;
            /* Rounded corners */
            transition: border-left 0.3s, background 0.3s;
        }

        /* Icon styling */
        #sidebar ul li a .fas {
            margin-right: 10px;
            width: 20px;
            /* Fixed width for icons */
            text-align: center;
        }

        /* Content */
        #content {
            width: 100%;
            padding: 20px;
            transition: all 0.3s;
        }

        /* Toggle button */
        #sidebarCollapse {
            background: #343a40;
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 4px;
        }

        #sidebarCollapse:hover {
            background: #495057;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }

            #sidebar.collapsed {
                margin-left: 0;
            }

            #content {
                padding: 20px;
            }

            #sidebarCollapse {
                display: inline-block;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="d-flex">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>
            <ul class="list-unstyled components">
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>">
                    <a href="categories.php"><i class="fas fa-list"></i> Categories</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                    <a href="products.php"><i class="fas fa-box-open"></i> Products</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'sizes.php' ? 'active' : '' ?>">
                    <a href="sizes.php"><i class="fas fa-ruler"></i> Sizes</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'extras.php' ? 'active' : '' ?>">
                    <a href="extras.php"><i class="fas fa-plus-circle"></i> Extras</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                    <a href="settings.php"><i class="fas fa-cogs"></i> Settings</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'drinks.php' ? 'active' : '' ?>">
                    <a href="drinks.php"><i class="fas fa-mug-hot"></i> Drinks</a>
                </li>
                <!-- Sauces -->
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'sauces.php' ? 'active' : '' ?>">
                    <a href="sauces.php"><i class="fas fa-seedling"></i> Sauces</a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn">
                        <i class="fas fa-bars"></i> <!-- Hamburger icon -->
                    </button>
                    <div class="ms-auto d-flex align-items-center">
                        <span class="navbar-text me-3">
                            Logged in as <?= htmlspecialchars($_SESSION['username']) ?>
                        </span>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    </div>
                </div>
            </nav>