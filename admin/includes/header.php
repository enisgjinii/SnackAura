<?php
// admin/includes/header.php

// Include authentication script
require_once 'auth.php';

// Get the current page name
$currentPage = basename($_SERVER['PHP_SELF']);

// Retrieve user role from session
$userRole = $_SESSION['role'] ?? '';

// Define menu items for different user roles with appropriate icons
$menuItems = [];

$adminMenuItems = [
    ['dashboard.php', 'fas fa-chart-pie', 'Dashboard'],
    ['categories.php', 'fas fa-th-list', 'Categories'],
    ['products.php', 'fas fa-box', 'Products'],
    ['settings.php', 'fas fa-sliders-h', 'Settings'],
    ['orders.php', 'fas fa-receipt', 'Orders'],
    ['drinks.php', 'fas fa-coffee', 'Drinks'],
    ['cupons.php', 'fas fa-tags', 'Coupons'],
    ['users.php', 'fas fa-user-friends', 'Users'],
    ['reservations.php', 'fas fa-calendar-check', 'Reservations'],
    ['stores.php', 'fas fa-shop', 'Stores'],
    ['banners.php', 'fas fa-photo-video', 'Banners'],
    ['statistics.php', 'fas fa-chart-bar', 'Statistics'],
    ['github.php', 'fab fa-github', 'Github'],
];

$deliveryMenuItems = [
    ['dashboard.php', 'fas fa-chart-pie', 'Dashboard'],
    ['orders.php', 'fas fa-receipt', 'Orders'],
];

// Assign menu items based on user role
switch ($userRole) {
    case 'admin':
    case 'super-admin':
        $menuItems = $adminMenuItems;
        break;
    case 'delivery':
        $menuItems = $deliveryMenuItems;
        break;
        // Additional roles can be added here
    default:
        $menuItems = []; // No menu items for unrecognized roles
        break;
}

// Fetch settings from the database
try {
    // Define required keys
    $legalKeys = ['agb', 'impressum', 'datenschutzerklaerung'];
    $socialKeys = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    $cartKeys = ['cart_logo', 'cart_description'];

    // Combine all keys for the query
    $allKeys = array_merge($legalKeys, $socialKeys, $cartKeys);
    $placeholders = rtrim(str_repeat('?,', count($allKeys)), ',');

    // Prepare and execute the SQL statement
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($placeholders)");
    $stmt->execute($allKeys);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Assign settings with default fallbacks
    $agb = $settings['agb'] ?? 'No AGB available.';
    $impressum = $settings['impressum'] ?? 'No Impressum available.';
    $datenschutzerklaerung = $settings['datenschutzerklaerung'] ?? 'No Datenschutzerklärung available.';

    $socialLinks = [
        'facebook_link'    => $settings['facebook_link'] ?? '',
        'twitter_link'     => $settings['twitter_link'] ?? '',
        'instagram_link'   => $settings['instagram_link'] ?? '',
        'linkedin_link'    => $settings['linkedin_link'] ?? '',
        'youtube_link'     => $settings['youtube_link'] ?? '',
    ];

    $cartLogo = $settings['cart_logo'] ?? '';
    $cartDescription = $settings['cart_description'] ?? '';

    // Clean cart logo path if necessary
    if (!empty($cartLogo)) {
        $cartLogo = str_replace('../', '', $cartLogo);
    }
} catch (PDOException $e) {
    // Log error and assign default values
    log_error_markdown("Failed to fetch settings: " . $e->getMessage(), "Fetching Settings");
    $agb = 'Error loading AGB.';
    $impressum = 'Error loading Impressum.';
    $datenschutzerklaerung = 'Error loading Datenschutzerklärung.';
    $socialLinks = [
        'facebook_link'    => '',
        'twitter_link'     => '',
        'instagram_link'   => '',
        'linkedin_link'    => '',
        'youtube_link'     => '',
    ];
    $cartLogo = '';
    $cartDescription = '';
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    <!-- Favicon -->
    <?php if (!empty($cartLogo)): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars('../' . $cartLogo, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <style>
        /* Global Styles */
        * {
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }

        /* Color Palette Variables */
        :root {
            --sidebar-bg: #2C3E50;
            --sidebar-header-bg: #1A252F;
            --sidebar-hover-bg: #34495E;
            --sidebar-active-bg: #2980B9;
            --sidebar-text: #ECF0F1;
            --sidebar-hover-text: #FFFFFF;
            --navbar-bg: #ffffff;
            --content-bg: #F8F9FA;
            --toggle-btn-bg: #2C3E50;
            --toggle-btn-hover-bg: #1A252F;
            --dropdown-bg: #FFFFFF;
            --dropdown-item-hover-bg: #F1F1F1;
            --border-color: #E0E0E0;
        }

        /* Sidebar Styling */
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s;
            position: fixed;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 15px;
            margin: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #sidebar.collapsed {
            min-width: 80px;
            max-width: 80px;
        }

        /* Sidebar Header */
        #sidebar .sidebar-header {
            padding: 1rem;
            background-color: var(--sidebar-header-bg);
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
        }

        /* Sidebar Menu */
        #sidebar .list-unstyled {
            padding: 0;
            flex-grow: 1;
            overflow-y: auto;
            max-height: calc(100vh - 120px);
        }

        /* Scrollbar Styling */
        #sidebar .list-unstyled::-webkit-scrollbar {
            width: 8px;
        }

        #sidebar .list-unstyled::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        #sidebar .list-unstyled::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }

        /* Sidebar Links */
        #sidebar .list-unstyled .nav-link {
            color: var(--sidebar-text);
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            transition: background 0.3s, color 0.3s;
            white-space: nowrap;
            position: relative;
            border-left: 4px solid transparent;
        }

        #sidebar.collapsed .list-unstyled .nav-link {
            justify-content: center;
        }

        #sidebar .list-unstyled .nav-link:hover,
        #sidebar .list-unstyled .active>.nav-link {
            color: var(--sidebar-hover-text);
            background-color: var(--sidebar-hover-bg);
            border-left: 4px solid var(--sidebar-active-bg);
        }

        /* Icon Styling */
        #sidebar .list-unstyled .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        #sidebar.collapsed .list-unstyled .nav-link .nav-label {
            display: none;
        }

        /* Tooltip for Collapsed Sidebar */
        #sidebar.collapsed .list-unstyled .nav-link::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            margin-left: 0.5rem;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        #sidebar.collapsed .list-unstyled .nav-link:hover::after {
            opacity: 1;
        }

        /* Content Area */
        #content {
            margin-left: 250px;
            padding: 1rem;
            transition: all 0.3s;
            flex-grow: 1;
            background-color: var(--content-bg);
            min-height: 100vh;
        }

        #sidebar.collapsed+#content {
            margin-left: 80px;
            width: calc(100% - 80px);
        }

        /* Toggle Button */
        #sidebarCollapse {
            background-color: var(--toggle-btn-bg);
            color: var(--sidebar-text);
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 0.25rem;
            transition: background-color 0.3s;
        }

        #sidebarCollapse:hover {
            background-color: var(--toggle-btn-hover-bg);
        }

        /* Navbar Styling */
        .navbar {
            padding: 0.5rem 1rem;
            background: var(--navbar-bg);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-radius: 0.5rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }

            #sidebar.active {
                margin-left: 0;
            }

            #content {
                margin-left: 0;
                width: 100%;
            }

            #sidebar.active+#content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            #sidebar.collapsed {
                min-width: 250px;
                max-width: 250px;
            }
        }

        /* Dropdown Menu Styling */
        .dropdown-menu {
            min-width: 200px;
            background-color: var(--dropdown-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .dropdown-item:hover {
            background-color: var(--dropdown-item-hover-bg);
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <nav id="sidebar" class="<?= isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : '' ?>" aria-label="Sidebar Navigation">
            <div class="sidebar-header">
                <h4><?= htmlspecialchars($_SESSION['company_name'] ?? 'Your Company', ENT_QUOTES, 'UTF-8') ?></h4>
            </div>
            <ul class="list-unstyled components">
                <?php if (!empty($menuItems)): ?>
                    <?php foreach ($menuItems as [$href, $icon, $label]): ?>
                        <li class="<?= $currentPage === $href ? 'active' : '' ?>">
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="nav-link" data-tooltip="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span class="nav-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <a href="dashboard.php" class="nav-link" aria-label="Dashboard">
                            <i class="fas fa-chart-pie"></i>
                            <span class="nav-label">Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <div id="content" class="flex-grow-1">
            <!-- Top Navigation Bar -->
            <nav class="navbar navbar-expand-lg navbar-light rounded-2 border mb-4">
                <button type="button" id="sidebarCollapse" class="btn" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="container-fluid">
                    <div class="ms-auto d-flex align-items-center">
                        <!-- User Dropdown Menu -->
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-id-badge me-2"></i> Role: <?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?></a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>