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
    // ['settings.php', 'fas fa-sliders-h', 'Settings'],
    ['extras.php', 'fas fa-plus', 'Extras'],
    ['orders.php', 'fas fa-receipt', 'Orders'],
    ['drinks.php', 'fas fa-coffee', 'Drinks'],
    ['cupons.php', 'fas fa-tags', 'Coupons'],
    ['users.php', 'fas fa-user-friends', 'Users'],
    ['reservations.php', 'fas fa-calendar-check', 'Reservations'],
    ['stores.php', 'fas fa-shop', 'Stores'],
    ['banners.php', 'fas fa-photo-video', 'Banners'],
    ['statistics.php', 'fas fa-chart-bar', 'Statistics'],
    ['logs.php', 'fas fa-history', 'Audit'],


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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

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

        /* Clean Color Palette */
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-header-bg: #f8fafc;
            --sidebar-hover-bg: #f1f5f9;
            --sidebar-active-bg: #0f172a;
            --sidebar-text: #64748b;
            --sidebar-hover-text: #334155;
            --sidebar-active-text: #ffffff;
            --navbar-bg: #ffffff;
            --content-bg: #f8fafc;
            --toggle-btn-bg: #f1f5f9;
            --toggle-btn-hover-bg: #e2e8f0;
            --dropdown-bg: #ffffff;
            --dropdown-item-hover-bg: #f1f5f9;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        /* Main Layout */
        body {
            margin: 0;
            padding: 0;
            background: var(--content-bg);
        }

        .d-flex {
            display: flex;
            min-height: 100vh;
        }

        /* Full Left Sidebar */
        #sidebar {
            width: 280px;
            min-width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        #sidebar.collapsed {
            width: 80px;
            min-width: 80px;
        }

        /* Clean Sidebar Header */
        #sidebar .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            background: var(--sidebar-header-bg);
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        #sidebar .sidebar-header h4 {
            margin: 0;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.025em;
        }

        /* Clean Sidebar Menu */
        #sidebar .list-unstyled {
            padding: 1rem 0;
            flex-grow: 1;
            margin: 0;
        }

        #sidebar .list-unstyled li {
            margin: 0;
            padding: 0;
        }

        /* Clean Sidebar Links */
        #sidebar .list-unstyled .nav-link {
            color: var(--sidebar-text);
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            margin: 0.25rem 1rem;
            transition: all 0.15s ease;
            white-space: nowrap;
            position: relative;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
        }

        #sidebar.collapsed .list-unstyled .nav-link {
            justify-content: center;
            margin: 0.25rem 0.5rem;
            padding: 0.875rem 0.5rem;
        }

        #sidebar .list-unstyled .nav-link:hover {
            color: var(--sidebar-hover-text);
            background: var(--sidebar-hover-bg);
        }

        #sidebar .list-unstyled .active>.nav-link {
            color: var(--sidebar-active-text);
            background: var(--sidebar-active-bg);
            font-weight: 600;
        }

        /* Clean Icon Styling */
        #sidebar .list-unstyled .nav-link i {
            margin-right: 0.875rem;
            width: 20px;
            text-align: center;
            font-size: 1.125rem;
        }

        #sidebar.collapsed .list-unstyled .nav-link i {
            margin-right: 0;
            font-size: 1.25rem;
        }

        #sidebar.collapsed .list-unstyled .nav-link .nav-label {
            display: none;
        }

        /* Clean Tooltip for Collapsed Sidebar */
        #sidebar.collapsed .list-unstyled .nav-link::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #0f172a;
            color: #ffffff;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
            margin-left: 0.75rem;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            font-size: 0.75rem;
            font-weight: 500;
        }

        #sidebar.collapsed .list-unstyled .nav-link:hover::after {
            opacity: 1;
        }

        /* Content Area */
        #content {
            flex: 1;
            background: var(--content-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Clean Navbar */
        .navbar {
            background: var(--navbar-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
        }

        .navbar .container-fluid {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Navbar Left Section */
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-left: 0.5rem;
        }

        /* Navbar Right Section */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-item {
            display: flex;
            align-items: center;
        }

        /* Icon Button */
        .btn-icon {
            background: var(--toggle-btn-bg);
            color: #64748b;
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.15s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--toggle-btn-hover-bg);
            color: #334155;
        }

        /* User Button */
        .btn-user {
            background: var(--toggle-btn-bg);
            color: #64748b;
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            min-width: 180px;
        }

        .btn-user:hover {
            background: var(--toggle-btn-hover-bg);
            color: #334155;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 1;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.2;
        }

        .btn-user .fas.fa-chevron-down {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Clean Toggle Button */
        #sidebarCollapse {
            background: var(--toggle-btn-bg);
            color: #64748b;
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.15s ease;
            font-weight: 500;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #sidebarCollapse:hover {
            background: var(--toggle-btn-hover-bg);
            color: #334155;
        }

        /* Clean Dropdown */
        .dropdown-menu {
            min-width: 200px;
            background: var(--dropdown-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: 6px;
            margin: 0.125rem 0;
            transition: background-color 0.15s ease;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .dropdown-item:hover {
            background: var(--dropdown-item-hover-bg);
        }

        /* Clean Button Styling */
        .btn-secondary {
            background: #0f172a;
            color: #ffffff;
            border: 1px solid #0f172a;
            border-radius: 6px;
            transition: all 0.15s ease;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .btn-secondary:hover {
            background: #1e293b;
            border-color: #1e293b;
            color: #ffffff;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .d-flex {
                flex-direction: column;
            }

            #sidebar {
                width: 100%;
                min-width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            #sidebar.collapsed {
                width: 100%;
                min-width: 100%;
            }

            #content {
                min-height: auto;
            }
        }

        /* Scrollbar Styling */
        #sidebar::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        #sidebar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <nav id="sidebar" class="<?= isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : '' ?>" aria-label="Sidebar Navigation">
            <div class="sidebar-header">
                <h4><?= htmlspecialchars($_SESSION['company_name'] ?? 'Yumiis', ENT_QUOTES, 'UTF-8') ?></h4>
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
            <nav class="navbar">
                <div class="container-fluid">
                    <div class="navbar-left">
                        <button type="button" id="sidebarCollapse" class="btn" aria-label="Toggle Sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="navbar-title">Dashboard</span>
                    </div>
                    
                    <div class="navbar-right">
                        <!-- Notifications -->
                        <div class="navbar-item">
                            <button class="btn btn-icon" aria-label="Notifications">
                                <i class="fas fa-bell"></i>
                            </button>
                        </div>
                        
                        <!-- User Dropdown Menu -->
                        <div class="dropdown">
                            <button class="btn btn-user dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="user-role"><?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-id-badge me-2"></i> Account Settings</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> System Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>