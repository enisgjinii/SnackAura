<?php
// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require 'db.php'; // Ensure this file sets up a PDO instance as $pdo

// Disable error display in production
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('display_startup_errors', 0); // Set to 1 for debugging
error_reporting(E_ALL);

// Function to log errors in Markdown format
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error_markdown($error_message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $safe_error_message = htmlspecialchars($error_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formatted_message = "### [$timestamp] Error\n\n**Message:** $safe_error_message\n\n";
    if ($context) {
        $safe_context = htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formatted_message .= "**Context:** $safe_context\n\n";
    }
    $formatted_message .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $formatted_message, FILE_APPEND | LOCK_EX);
}

// Custom exception handler
set_exception_handler(function ($exception) {
    log_error_markdown("Uncaught Exception: " . $exception->getMessage(), "File: " . $exception->getFile() . " Line: " . $exception->getLine());
    // Optionally, display a user-friendly message
    header("Location: index.php?error=unknown_error");
    exit;
});

// Custom error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Fetch products with allergies
    $stmt_with_allergies = $pdo->prepare("SELECT id, name, description, image_url, allergies FROM products WHERE allergies IS NOT NULL AND TRIM(allergies) != '' ORDER BY name ASC");
    $stmt_with_allergies->execute();
    $products_with_allergies = $stmt_with_allergies->fetchAll(PDO::FETCH_ASSOC);

    // Fetch products without allergies
    $stmt_without_allergies = $pdo->prepare("SELECT id, name, description, image_url FROM products WHERE allergies IS NULL OR TRIM(allergies) = '' ORDER BY name ASC");
    $stmt_without_allergies->execute();
    $products_without_allergies = $stmt_without_allergies->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Database Query Failed: " . $e->getMessage(), "Fetching Products");
    $products_with_allergies = [];
    $products_without_allergies = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Allergies Overview</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (Optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- DataTables Responsive CSS -->
    <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS (Optional) -->
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            margin-top: 50px;
            margin-bottom: 50px;
        }

        .table-container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header-title {
            margin-bottom: 30px;
        }

        .section-title {
            margin-top: 40px;
            margin-bottom: 20px;
        }

        /* Adjust DataTables search input */
        .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
        }

        /* Adjust DataTables length select */
        .dataTables_length select {
            width: auto;
            display: inline-block;
            margin-left: 0.5em;
        }

        /* Badge styling */
        .badge-allergy {
            background-color: #dc3545;
            /* Bootstrap danger color */
            color: #fff;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        /* Image thumbnail styling */
        .img-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .img-thumbnail {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Bar (Optional) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Restaurant</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAllergies" aria-controls="navbarAllergies" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAllergies">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <!-- Add more navigation links as needed -->
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="table-container">
            <h1 class="header-title text-center">Product Allergies Overview</h1>

            <!-- Products with Allergies -->
            <h2 class="section-title">Products with Allergies</h2>
            <?php if (!empty($products_with_allergies)): ?>
                <div class="table-responsive">
                    <table id="allergiesTable" class="table table-striped table-hover nowrap" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>Description</th>
                                <th>Allergies</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products_with_allergies as $index => $product): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['description']) ?></td>
                                    <td>
                                        <?php
                                        // Split the allergies string into an array, trim whitespace, and filter out empty values
                                        $allergies = array_filter(array_map('trim', explode(',', $product['allergies'])));
                                        // Display each allergy as a badge
                                        foreach ($allergies as $allergy) {
                                            echo '<span class="badge badge-allergy">' . htmlspecialchars($allergy) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <img src="admin/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail" onerror="this.src='https://via.placeholder.com/100?text=No+Image';">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    No products with allergies found.
                </div>
            <?php endif; ?>

            <!-- Products without Allergies -->
            <h2 class="section-title">Products without Allergies</h2>
            <?php if (!empty($products_without_allergies)): ?>
                <div class="table-responsive">
                    <table id="noAllergiesTable" class="table table-striped table-hover nowrap" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>Description</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products_without_allergies as $index => $product): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['description']) ?></td>
                                    <td>
                                        <img src="admin/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail" onerror="this.src='https://via.placeholder.com/100?text=No+Image';">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    No products without allergies found.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer (Optional) -->
    <footer class="bg-light text-muted pt-5 pb-4">
        <div class="container text-md-left">
            <div class="row text-md-left">
                <!-- Company Info -->
                <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                        <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" class="me-2">
                        Restaurant
                    </h5>
                    <p>
                        Experience the finest dining with us. We offer a variety of dishes crafted from the freshest ingredients to delight your palate.
                    </p>
                </div>
                <!-- Navigation Links -->
                <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="text-reset text-decoration-none">Home</a>
                        </li>
                        <li class="mb-2">
                            <a href="#menu" class="text-reset text-decoration-none">Menu</a>
                        </li>
                        <li class="mb-2">
                            <a href="#about" class="text-reset text-decoration-none">About Us</a>
                        </li>
                        <li class="mb-2">
                            <a href="#contact" class="text-reset text-decoration-none">Contact</a>
                        </li>
                    </ul>
                </div>
                <!-- Legal Links -->
                <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#agbModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> AGB
                            </button>
                        </li>
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#impressumModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> Impressum
                            </button>
                        </li>
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> Datenschutzerklärung
                            </button>
                        </li>
                    </ul>
                </div>
                <!-- Contact Information -->
                <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Contact Us</h5>
                    <p><i class="bi bi-geo-alt-fill me-2"></i> 123 Main Street, City, Country</p>
                    <p><i class="bi bi-envelope-fill me-2"></i> info@restaurant.com</p>
                    <p><i class="bi bi-telephone-fill me-2"></i> +1 234 567 890</p>
                    <p><i class="bi bi-clock-fill me-2"></i> Mon - Sun: 10:00 AM - 10:00 PM</p>
                </div>
            </div>
            <hr class="mb-4">
            <div class="row align-items-center">
                <!-- Social Media Icons -->
                <div class="col-md-7 col-lg-8">
                    <p>
                        © <?= date('Y') ?> <strong>Restaurant</strong>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="text-center text-md-end">
                        <div class="social-media">
                            <?php
                            // Assume $social_links is an associative array with social media URLs
                            // Example:
                            // $social_links = [
                            //     'facebook_link' => 'https://facebook.com/yourpage',
                            //     'twitter_link' => 'https://twitter.com/yourprofile',
                            //     'instagram_link' => 'https://instagram.com/yourprofile',
                            //     'linkedin_link' => 'https://linkedin.com/in/yourprofile',
                            //     'youtube_link' => 'https://youtube.com/yourchannel',
                            // ];
                            // Ensure $social_links is defined before using it
                            $social_links = $social_links ?? []; // Default to empty array if not set
                            ?>
                            <?php if (!empty($social_links['facebook_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['facebook_link']) ?>" target="_blank" rel="noopener noreferrer" class="me-3 text-reset">
                                    <i class="bi bi-facebook fs-4"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['twitter_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['twitter_link']) ?>" target="_blank" rel="noopener noreferrer" class="me-3 text-reset">
                                    <i class="bi bi-twitter fs-4"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['instagram_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['instagram_link']) ?>" target="_blank" rel="noopener noreferrer" class="me-3 text-reset">
                                    <i class="bi bi-instagram fs-4"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['linkedin_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['linkedin_link']) ?>" target="_blank" rel="noopener noreferrer" class="me-3 text-reset">
                                    <i class="bi bi-linkedin fs-4"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['youtube_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['youtube_link']) ?>" target="_blank" rel="noopener noreferrer" class="me-3 text-reset">
                                    <i class="bi bi-youtube fs-4"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    </footer>

    <!-- Modals for Legal Pages (Optional) -->
    <!-- Example: AGB Modal -->
    <div class="modal fade" id="agbModal" tabindex="-1" aria-labelledby="agbModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Allgemeine Geschäftsbedingungen (AGB)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- AGB Content Here -->
                    <p>Your AGB content goes here...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Similarly, create Impressum and Datenschutzerklärung modals -->

    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable for Products with Allergies
            $('#allergiesTable').DataTable({
                responsive: true,
                language: {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "zeroRecords": "No matching products found",
                    "info": "Showing _START_ to _END_ of _TOTAL_ products",
                    "infoEmpty": "Showing 0 to 0 of 0 products",
                    "infoFiltered": "(filtered from _MAX_ total products)"
                },
                columnDefs: [{
                        orderable: false,
                        targets: [3, 4]
                    } // Disable sorting on Allergies and Image columns
                ]
            });

            // Initialize DataTable for Products without Allergies
            $('#noAllergiesTable').DataTable({
                responsive: true,
                language: {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "zeroRecords": "No matching products found",
                    "info": "Showing _START_ to _END_ of _TOTAL_ products",
                    "infoEmpty": "Showing 0 to 0 of 0 products",
                    "infoFiltered": "(filtered from _MAX_ total products)"
                },
                columnDefs: [{
                        orderable: false,
                        targets: [3]
                    } // Disable sorting on Image column
                ]
            });

            // Optional: Add Export Buttons (Requires additional DataTables extensions)
            /*
            $('#allergiesTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });
            */
        });
    </script>
</body>

</html>
<?php
// Flush the output buffer
ob_end_flush();
?>