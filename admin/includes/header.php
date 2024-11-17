<?php
// admin/includes/header.php
require_once 'auth.php';
// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
// Get session user role
$userRole = $_SESSION['role'] ?? '';
// Define menu items for different roles
$adminMenuItems = [
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
    ['products_mixes.php', 'fas fa-utensils', 'Products Mixes'],
    ['reservations.php', 'fas fa-calendar-alt', 'Reservations'],
    ['stores.php', 'fas fa-store', 'Stores'],
    ['banners.php', 'fas fa-images', 'Banners'],
    ['offers.php', 'fas fa-tags', 'Offers'],
    ['statistics.php', 'fas fa-chart-line', 'Statistics'],
];
$deliveryMenuItems = [
    ['dashboard.php', 'fas fa-tachometer-alt', 'Dashboard'],
    ['orders.php', 'fas fa-shopping-cart', 'Orders'],
];
// You can define more menus for other roles like 'waiter' if needed
// Determine which menu items to display based on the role
switch ($userRole) {
    case 'admin':
        $menuItems = $adminMenuItems;
        break;
    case 'delivery':
        $menuItems = $deliveryMenuItems;
        break;
        // Add cases for other roles like 'waiter' here
    default:
        $menuItems = []; // No menu items if role is unrecognized
        break;
}
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
            position: fixed;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        #sidebar.collapsed {
            min-width: 80px;
            max-width: 80px;
        }
        #sidebar .sidebar-header {
            padding: 1rem;
            background-color: #3c4043;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        #sidebar .list-unstyled {
            padding: 0;
            flex-grow: 1;
            overflow-y: auto;
            /* Make the menu scrollable */
        }
        #sidebar .list-unstyled li {
            width: 100%;
        }
        #sidebar .list-unstyled .nav-link {
            color: #d1d1d1;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            transition: background 0.3s, color 0.3s;
            white-space: nowrap;
            position: relative;
        }
        #sidebar.collapsed .list-unstyled .nav-link {
            justify-content: center;
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
            font-size: 1.1rem;
        }
        #sidebar.collapsed .list-unstyled .nav-link .nav-label {
            display: none;
        }
        /* Tooltip Styling */
        #sidebar.collapsed .list-unstyled .nav-link .nav-label::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background-color: #343a40;
            color: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            margin-left: 0.5rem;
            z-index: 1000;
        }
        #sidebar.collapsed .list-unstyled .nav-link:hover .nav-label::after {
            opacity: 1;
        }
        /* Content Styling */
        #content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 1rem;
            transition: all 0.3s;
            flex-grow: 1;
        }
        #sidebar.collapsed+#content {
            width: calc(100% - 80px);
            margin-left: 80px;
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
        /* Navbar Styling */
        .navbar {
            padding: 0.5rem 1rem;
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            #sidebar.collapsed {
                margin-left: 0;
                min-width: 250px;
                max-width: 250px;
            }
            #content {
                width: 100%;
                margin-left: 0;
            }
            #sidebar.collapsed+#content {
                width: 100%;
                margin-left: 0;
            }
        }
        /* Scrollbar Styling (Optional) */
        #sidebar .list-unstyled::-webkit-scrollbar {
            width: 6px;
        }
        #sidebar .list-unstyled::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }
        #sidebar .list-unstyled::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="<?= isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : '' ?>">
            <div class="sidebar-header">
                <h4><?= isset($_SESSION['company_name']) ? htmlspecialchars($_SESSION['company_name']) : 'Admin Panel' ?></h4>
            </div>
            <ul class="list-unstyled components">
                <?php if (!empty($menuItems)): ?>
                    <?php foreach ($menuItems as [$href, $icon, $label]): ?>
                        <li class="<?= $currentPage === $href ? 'active' : '' ?>">
                            <a href="<?= htmlspecialchars($href) ?>" class="nav-link" data-tooltip="<?= htmlspecialchars($label) ?>">
                                <i class="<?= htmlspecialchars($icon) ?>"></i>
                                <span class="nav-label"><?= htmlspecialchars($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span class="nav-label">Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>
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
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                                <li><a class="dropdown-item" href="#">Profile</a></li>
                                <li><a class="dropdown-item" href="#">Role: <?= htmlspecialchars($_SESSION['role']) ?></a></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>