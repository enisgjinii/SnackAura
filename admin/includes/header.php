<?php
// admin/includes/header.php
require_once 'auth.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$menuItems = [
    ['dashboard.php', 'fas fa-tachometer-alt', 'Dashboard'],
    ['categories.php', 'fas fa-list', 'Categories'],
    ['products.php', 'fas fa-box-open', 'Products'],
    ['sizes.php', 'fas fa-ruler', 'Sizes'],
    ['extras.php', 'fas fa-plus-circle', 'Extras'],
    ['settings.php', 'fas fa-cogs', 'Settings'],
    ['orders.php', 'fas fa-shopping-cart', 'Orders'],
    ['drinks.php', 'fas fa-mug-hot', 'Drinks'],
    ['sauces.php', 'fas fa-seedling', 'Sauces'],
    ['informations.php', 'fas fa-info-circle', 'Informations'],
    ['users.php', 'fas fa-users', 'Users'],
    ['products_mixes.php', 'fas fa-mix', 'Products Mixes'],
    ['reservations.php', 'fas fa-calendar-alt', 'Reservations'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Restaurant Delivery</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        /* Sidebar Styling */
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            height: 100vh;
            background-color: #343a40;
            color: #fff;
            transition: all 0.3s;
        }

        #sidebar.collapsed {
            margin-left: -250px;
        }

        #sidebar .sidebar-header {
            padding: 1rem;
            background-color: #3c4043;
            text-align: center;
        }

        #sidebar .list-unstyled .nav-link {
            color: #d1d1d1;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            transition: background 0.3s, color 0.3s;
        }

        #sidebar .list-unstyled .nav-link:hover,
        #sidebar .list-unstyled .active>.nav-link {
            color: #fff;
            background-color: #007bff;
            border-left: 4px solid #fff;
        }

        #sidebar .list-unstyled .nav-link .fas {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Content Styling */
        #content {
            width: 100%;
            padding: 1rem;
            transition: margin 0.3s;
        }

        /* Toggle Button */
        #sidebarCollapse {
            background-color: #343a40;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 0.25rem;
            transition: background-color 0.3s;
        }

        #sidebarCollapse:hover {
            background-color: #495057;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }

            #sidebar.collapsed {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="<?= isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : '' ?>">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>
            <ul class="list-unstyled components">
                <?php foreach ($menuItems as [$href, $icon, $label]): ?>
                    <li class="<?= $currentPage === $href ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars($href) ?>" class="nav-link">
                            <i class="<?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content" class="flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-auto d-flex align-items-center">
                        <span class="navbar-text me-3">
                            Logged in as <?= htmlspecialchars($_SESSION['username']) ?>
                        </span>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    </div>
                </div>
            </nav>
            <!-- Your page content starts here -->