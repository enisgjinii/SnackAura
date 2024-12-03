<?php
// admin/includes/header.php
require_once 'auth.php';
// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
// Get session user role
$userRole = $_SESSION['role'] ?? '';
// Define menu items for different roles with updated icons
$adminMenuItems = [
    ['dashboard.php', 'fas fa-chart-pie', 'Dashboard'],
    ['categories.php', 'fas fa-th-list', 'Categories'],
    ['products.php', 'fas fa-box', 'Products'],
    ['sizes.php', 'fas fa-arrows-alt', 'Sizes'],
    ['extras.php', 'fas fa-layer-group', 'Extras'],
    ['settings.php', 'fas fa-sliders-h', 'Settings'],
    ['orders.php', 'fas fa-receipt', 'Orders'],
    ['drinks.php', 'fas fa-coffee', 'Drinks'],
    ['sauces.php', 'fas fa-bottle-water', 'Sauces'],
    ['informations.php', 'fas fa-info', 'Informations'],
    ['users.php', 'fas fa-user-friends', 'Users'],
    ['products_mixes.php', 'fas fa-blender', 'Products Mixes'],
    ['reservations.php', 'fas fa-calendar-check', 'Reservations'],
    ['stores.php', 'fas fa-shop', 'Stores'],
    ['banners.php', 'fas fa-photo-video', 'Banners'],
    ['offers.php', 'fas fa-percentage', 'Offers'],
    ['statistics.php', 'fas fa-chart-bar', 'Statistics'],
];
$deliveryMenuItems = [
    ['dashboard.php', 'fas fa-chart-pie', 'Dashboard'],
    ['orders.php', 'fas fa-receipt', 'Orders'],
];
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
// settings_fetch.php
// Fetch legal settings (AGB, Impressum, Datenschutzerklärung) and social media links
try {
    // Define all required keys
    $legal_keys = ['agb', 'impressum', 'datenschutzerklaerung'];
    $social_keys = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    $cart_keys = ['cart_logo', 'cart_description']; // New Cart Settings
    // Merge all keys into a single array for a combined query
    $all_keys = array_merge($legal_keys, $social_keys, $cart_keys); // Include cart_keys
    // Create placeholders for the IN clause
    $placeholders = rtrim(str_repeat('?,', count($all_keys)), ',');
    // Prepare the SQL statement
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($placeholders)");
    // Execute the statement with all keys
    $stmt->execute($all_keys);
    // Fetch the results as an associative array
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    // Assign legal settings with default fallbacks
    $agb = $settings['agb'] ?? 'No AGB available.';
    $impressum = $settings['impressum'] ?? 'No Impressum available.';
    $datenschutzerklaerung = $settings['datenschutzerklaerung'] ?? 'No Datenschutzerklärung available.';
    // Assign social media links with default empty strings if not set
    $social_links = [
        'facebook_link' => $settings['facebook_link'] ?? '',
        'twitter_link' => $settings['twitter_link'] ?? '',
        'instagram_link' => $settings['instagram_link'] ?? '',
        'linkedin_link' => $settings['linkedin_link'] ?? '',
        'youtube_link' => $settings['youtube_link'] ?? '',
    ];
    // Assign cart settings with default fallbacks
    $cart_logo = $settings['cart_logo'] ?? ''; // Default empty string
    $cart_description = $settings['cart_description'] ?? ''; // Default empty string
    // Adjust cart_logo path if necessary (remove '../' if present)
    if (!empty($cart_logo)) {
        $cart_logo = str_replace('../', '', $cart_logo);
    }
} catch (PDOException $e) {
    // Log the error with context
    log_error_markdown("Failed to fetch settings: " . $e->getMessage(), "Fetching Settings");
    // Assign default values for legal settings in case of an error
    $agb = 'Error loading AGB.';
    $impressum = 'Error loading Impressum.';
    $datenschutzerklaerung = 'Error loading Datenschutzerklärung.';
    // Assign default empty strings for social media links in case of an error
    $social_links = [
        'facebook_link' => '',
        'twitter_link' => '',
        'instagram_link' => '',
        'linkedin_link' => '',
        'youtube_link' => '',
    ];
    // Assign default empty strings for cart settings in case of an error
    $cart_logo = '';
    $cart_description = '';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/jq-3.7.0/jszip-3.10.1/dt-2.1.8/af-2.7.0/b-3.2.0/b-colvis-3.2.0/b-html5-3.2.0/cr-2.0.4/date-1.5.4/fc-5.0.4/fh-4.0.1/kt-2.12.1/r-3.0.3/rg-1.5.1/rr-1.5.0/sc-2.4.3/sb-1.8.1/sp-2.3.3/sl-2.1.0/sr-1.4.1/datatables.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://kit.fontawesome.com/a076d05399.css" crossorigin="anonymous">
    <!-- Fav Icon -->
    <?php if (!empty($cart_logo)): ?>
        <link rel="icon" type="image/png" href="../<?php echo htmlspecialchars($cart_logo, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <style>
        * {
            font-family: "Inter", sans-serif;
            font-optical-sizing: auto;
        }

        /* Color Palette */
        :root {
            --sidebar-bg: #2C3E50;
            --sidebar-header-bg: #1A252F;
            --sidebar-hover-bg: #34495E;
            --sidebar-active-bg: #2980B9;
            --sidebar-text: #ECF0F1;
            --sidebar-hover-text: #FFFFFF;
            --navbar-bg: linear-gradient(90deg, #ffffff, #f8f9fa);
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
            height: fit-content;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s;
            position: fixed;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 15px;
            margin: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #sidebar.collapsed {
            min-width: 80px;
            max-width: 80px;
        }

        #sidebar .sidebar-header {
            padding: 1rem;
            background-color: var(--sidebar-header-bg);
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
        }

        #sidebar .list-unstyled {
            padding: 0;
            flex-grow: 1;
            overflow-y: auto;
            /* Enables vertical scrolling */
            max-height: calc(100vh - 120px);
            /* Adjust height as per your layout */
        }

        /* Optional: Customize the scrollbar appearance */
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

        #sidebar .list-unstyled li {
            width: 100%;
        }

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
            /* background-color: var(--content-bg); */
            min-height: 100vh;
        }

        #sidebar.collapsed+#content {
            width: calc(100% - 80px);
            margin-left: 80px;
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
                width: 100%;
                margin-left: 0;
            }

            #sidebar.active+#content {
                width: 100%;
                margin-left: 250px;
            }

            #sidebar.collapsed {
                min-width: 250px;
                max-width: 250px;
            }
        }

        /* Scrollbar Styling (Optional) */
        #sidebar .list-unstyled::-webkit-scrollbar {
            width: 6px;
        }

        #sidebar .list-unstyled::-webkit-scrollbar-thumb {
            background-color: rgba(236, 240, 241, 0.2);
            border-radius: 3px;
        }

        #sidebar .list-unstyled::-webkit-scrollbar-thumb:hover {
            background-color: rgba(236, 240, 241, 0.4);
        }

        /* Additional Styling Enhancements */
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
        <!-- Sidebar -->
        <nav id="sidebar" class="<?= isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : '' ?>" aria-label="Sidebar Navigation">
            <div class="sidebar-header">
                <h4><?= isset($_SESSION['company_name']) ? htmlspecialchars($_SESSION['company_name']) : 'Y' ?></h4>
            </div>
            <ul class="list-unstyled components">
                <?php if (!empty($menuItems)): ?>
                    <?php foreach ($menuItems as [$href, $icon, $label]): ?>
                        <li class="<?= $currentPage === $href ? 'active' : '' ?>">
                            <a href="<?= htmlspecialchars($href) ?>" class="nav-link" data-tooltip="<?= htmlspecialchars($label) ?>" aria-label="<?= htmlspecialchars($label) ?>">
                                <i class="<?= htmlspecialchars($icon) ?>"></i>
                                <span class="nav-label"><?= htmlspecialchars($label) ?></span>
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
        <!-- Page Content -->
        <div id="content" class="flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-light rounded-2 border  mb-4">
                <button type="button" id="sidebarCollapse" class="btn" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="container-fluid">
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle d-flex align-items-center" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton1">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-id-badge me-2"></i> Role: <?= htmlspecialchars($_SESSION['role']) ?></a></li>
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