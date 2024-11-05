<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Flag Icons CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <style>
        /* CSS Variables for Theming */
        :root {
            --primary-color: #FF0000;
            /* Swiss Red */
            --secondary-color: #FFFFFF;
            /* White */
            --accent-color: #1976D2;
            /* Blue for accents */
            --neutral-light: #F5F5F5;
            --neutral-dark: #333333;
            --font-family: 'Saira', sans-serif;
            --border-radius: 8px;
            --transition-speed: 0.3s;
        }

        /* Global Styles */
        body {
            font-family: var(--font-family);
            background-color: var(--neutral-light);
            color: var(--neutral-dark);
            margin: 0;
            padding: 0;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Navbar */
        .navbar {
            background-color: var(--secondary-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
            position: relative;
        }

        .navbar-brand img {
            border-radius: 50%;
            border: 2px solid #e0e0e0;
            transition: transform var(--transition-speed);
        }

        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .restaurant-name {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-left: 0.5rem;
        }

        /* Language Switcher */
        .language-switcher {
            position: absolute;
            top: 1rem;
            right: 2rem;
            z-index: 1000;
        }

        .language-switcher .dropdown-toggle {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--neutral-dark);
            font-weight: 600;
        }

        .language-switcher .flag-icon {
            margin-right: 8px;
        }

        /* Promo Banner */
        .promo-banner {
            background-color: var(--secondary-color);
            padding: 4rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .promo-banner h1 {
            font-size: 3.5rem;
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .promo-banner h2 {
            font-size: 2.5rem;
            color: var(--accent-color);
            font-weight: 600;
            margin-top: 1rem;
        }

        .promo-banner p {
            font-size: 1.25rem;
            color: #555555;
            margin-top: 1rem;
            line-height: 1.6;
        }

        .promo-banner img {
            width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform var(--transition-speed);
        }

        .promo-banner img:hover {
            transform: scale(1.02);
        }

        /* Navigation Tabs */
        .nav-tabs {
            justify-content: center;
            border-bottom: none;
        }

        .nav-tabs .nav-link {
            color: #555555;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
            transition: color var(--transition-speed), border-bottom var(--transition-speed);
            padding: 0.75rem 1.5rem;
            white-space: nowrap;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
        }

        /* Menu Items */
        .menu-item img {
            width: 100%;
            height: auto;
            object-fit: cover;
            transition: transform var(--transition-speed);
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
        }

        .menu-item img:hover {
            transform: scale(1.05);
        }

        .menu-item .card-body {
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .menu-item .card-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .menu-item .card-text {
            flex-grow: 1;
            color: #777777;
            margin-bottom: 1rem;
        }

        .menu-item .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }

        .menu-item .btn-primary:hover {
            background-color: #cc0000;
            border-color: #cc0000;
        }

        /* Order Summary */
        .order-summary {
            max-height: 70vh;
            overflow-y: auto;
            padding: 1.5rem;
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 2rem;
        }

        .order-summary .order-title {
            font-size: 2rem;
            color: var(--accent-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .order-summary .card-text {
            font-size: 1.1rem;
            color: #555555;
        }

        .order-summary .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
            padding: 0.75rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .order-summary .btn-primary:hover {
            background-color: #115293;
            border-color: #115293;
        }

        /* Footer */
        footer {
            background-color: var(--secondary-color);
            border-top: 1px solid #e0e0e0;
            padding: 2rem 0;
        }

        footer img {
            border-radius: var(--border-radius);
            transition: transform var(--transition-speed);
        }

        footer img:hover {
            transform: scale(1.05);
        }

        footer a {
            color: var(--neutral-dark);
            margin-right: 1.5rem;
            transition: color var(--transition-speed);
            font-size: 1.5rem;
        }

        footer a:hover {
            color: var(--primary-color);
        }

        /* Modals */
        .modal-content {
            border-radius: var(--border-radius);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }

        .modal-header,
        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
        }

        .modal-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
        }

        /* Toasts */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1100;
        }

        /* Skeleton Loading */
        .skeleton {
            background-color: #e0e0e0;
            border-radius: var(--border-radius);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }

            100% {
                opacity: 1;
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .promo-banner h1 {
                font-size: 3rem;
            }

            .promo-banner h2 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 992px) {
            .navbar {
                padding: 0.75rem 1.5rem;
            }

            .restaurant-name {
                font-size: 1.25rem;
            }

            .promo-banner {
                padding: 3rem 0;
            }

            .promo-banner h1 {
                font-size: 2.5rem;
            }

            .promo-banner h2 {
                font-size: 1.8rem;
            }

            .promo-banner p {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .language-switcher {
                position: static;
                margin-top: 1rem;
                text-align: center;
            }

            .promo-banner {
                padding: 2rem 1rem;
            }

            .promo-banner h1 {
                font-size: 2rem;
            }

            .promo-banner h2 {
                font-size: 1.5rem;
            }

            .promo-banner p {
                font-size: 1rem;
            }

            .order-summary {
                position: static;
                max-height: none;
                margin-top: 2rem;
            }

            .order-summary .order-title {
                font-size: 1.5rem;
            }

            .menu-item .card-body {
                padding: 1rem;
            }

            .menu-item .card-title {
                font-size: 1.25rem;
            }

            .menu-item .card-text {
                font-size: 0.95rem;
            }

            .order-summary .btn-primary {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            footer a {
                margin-right: 1rem;
                font-size: 1.2rem;
            }
        }

        /* Button Animations */
        .btn {
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        /* Accessibility Focus Styles */
        .btn:focus,
        .nav-link:focus,
        .dropdown-item:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }

        /* Responsive Images in Modals */
        .product-modal-img {
            width: 100%;
            height: auto;
        }

        /* Hide Edit Sections Initially */
        .edit-section {
            display: none;
            transition: max-height 0.5s ease;
            overflow: hidden;
        }

        .edit-section.show {
            display: block;
        }

        /* Loading Spinner Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            display: none;
        }

        .loading-overlay.active {
            display: flex;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status" aria-label="Loading">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <!-- Checkout Confirmation Modal -->
    <div class="modal fade" id="checkout-confirmation-modal" tabindex="-1" aria-labelledby="checkout-confirmation-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="checkout-confirmation-modal-label">Confirm Your Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Order Summary -->
                    <h6>Customer Details:</h6>
                    <p>
                        <strong>Name:</strong> <span id="confirm-customer-name"></span><br>
                        <strong>Email:</strong> <span id="confirm-customer-email"></span><br>
                        <strong>Phone:</strong> <span id="confirm-customer-phone"></span><br>
                        <strong>Delivery Address:</strong> <span id="confirm-delivery-address"></span>
                    </p>
                    <h6>Cart Items:</h6>
                    <div id="confirm-cart-items">
                        <!-- Cart items will be dynamically inserted here -->
                    </div>
                    <h5 class="mt-3">Total Amount: <span id="confirm-total-amount"></span>€</h5>
                    <h6 class="mt-3">Special Instructions:</h6>
                    <p id="confirm-special-instructions"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirm-order-btn">
                        <i class="bi bi-check-circle me-2"></i>
                        Confirm Order
                    </button>
                </div>
            </div>
        </div>
    </div>
    <header class="position-relative">
        <nav class="navbar navbar-expand-lg">
            <div class="container d-flex justify-content-between align-items-center">
                <a class="navbar-brand d-flex align-items-center" href="#">
                    <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" onerror="this.onerror=null; this.src='https://via.placeholder.com/40';">
                    <span class="restaurant-name">Restaurant</span>
                </a>
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary position-relative me-3" type="button" id="cart-button" aria-label="View Cart">
                        <i class="bi bi-cart fs-4"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">0</span>
                    </button>
                    <span class="fw-bold fs-5" id="cart-total">0.00€</span>
                </div>
            </div>
        </nav>
        <div class="language-switcher">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="flag-icon flag-icon-de"></span> DE
                </button>
                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                    <li><a class="dropdown-item language-option" href="#" data-lang="de"><span class="flag-icon flag-icon-de"></span> Deutsch (DE)</a></li>
                    <li><a class="dropdown-item language-option" href="#" data-lang="sq"><span class="flag-icon flag-icon-al"></span> Shqip (SQ)</a></li>
                </ul>
            </div>
        </div>
    </header>
    <section class="promo-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <small class="text-uppercase text-muted">Offer</small>
                    <h1>BASKET MIX</h1>
                    <p class="mt-3">10X Chicken Tenders<br>10X Spicy Wings</p>
                    <h2 class="mt-3">11.99€</h2>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=400&w=600" alt="Basket Mix" class="img-fluid rounded shadow" onerror="this.onerror=null; this.src='https://via.placeholder.com/600x400';">
                </div>
            </div>
        </div>
    </section>
    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" id="myTab" role="tablist"></ul>
    <main class="container my-5">
        <div class="row">
            <!-- Menu Items Section -->
            <div class="col-lg-8">
                <div class="tab-content" id="myTabContent">
                    <!-- Offers Tab -->
                    <div class="tab-pane fade show active" id="offers" role="tabpanel">
                        <div class="row g-4" id="menu-items">
                            <!-- Skeleton Loader for Menu Items -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="skeleton" style="height: 220px;"></div>
                                    <div class="card-body">
                                        <div class="skeleton mb-2" style="width: 60%; height: 20px;"></div>
                                        <div class="skeleton mb-2" style="width: 80%; height: 15px;"></div>
                                        <div class="skeleton" style="width: 40%; height: 25px;"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Repeat skeletons as needed -->
                        </div>
                    </div>
                    <!-- New Products Tab -->
                    <div class="tab-pane fade" id="new" role="tabpanel">
                        <p class="fs-5 text-center">New products coming soon!</p>
                    </div>
                    <!-- Burgers Tab -->
                    <div class="tab-pane fade" id="burgers" role="tabpanel">
                        <p class="fs-5 text-center">Check out our burger selection!</p>
                    </div>
                </div>
            </div>
            <!-- Order Summary Section -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <p class="card-text">
                        <strong>Working hours:</strong> 10:00 - 21:45<br>
                        <strong>Minimum order:</strong> 5.00€
                    </p>
                    <div id="cart-items">
                        <!-- Skeleton Loader for Cart Items -->
                        <div class="skeleton mb-2" style="width: 100%; height: 50px;"></div>
                        <div class="skeleton" style="width: 80%; height: 50px;"></div>
                    </div>
                    <div class="mt-4">
                        <button id="checkout-btn" class="btn btn-primary w-100" disabled>
                            <i class="bi bi-check2 me-2"></i>
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- Footer Section -->
    <footer>
        <div class="container text-center text-md-start">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=32&w=100" alt="Footer Logo" width="100" height="32" class="rounded" onerror="this.onerror=null; this.src='https://via.placeholder.com/100x32';">
                </div>
                <div class="col-md-6">
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>
    <!-- Modals -->
    <!-- Location Modal -->
    <div class="modal fade" id="location-modal" tabindex="-1" aria-labelledby="location-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="location-modal-label">Select Your Location</h5>
                </div>
                <div class="modal-body">
                    <form id="location-form" novalidate>
                        <div class="mb-3">
                            <label for="city" class="form-label">City</label>
                            <select class="form-select" id="city" required>
                                <option value="">Choose a city</option>
                                <option value="Zurich">Zurich</option>
                                <option value="Geneva">Geneva</option>
                                <option value="Basel">Basel</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a city.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Delivery Address</label>
                            <input type="text" class="form-control" id="address" required>
                            <div class="invalid-feedback">
                                Please enter your delivery address.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-geo-alt me-2"></i>
                            Confirm Location
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Product Modal -->
    <div class="modal fade" id="product-modal" tabindex="-1" aria-labelledby="product-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="product-modal-label">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <img src="" alt="Product Image" class="img-fluid rounded product-modal-img shadow" id="product-modal-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/600x400';">
                        </div>
                        <div class="col-md-6">
                            <h2 id="product-modal-name" class="fw-bold"></h2>
                            <p id="product-modal-description" class="text-muted"></p>
                            <h3 class="mt-4">Sizes</h3>
                            <div id="product-modal-sizes" class="mb-3"></div>
                            <h3 class="mt-4">Extras</h3>
                            <div id="product-modal-extras" class="mb-3"></div>
                            <h3 class="mt-4">Drinks</h3>
                            <div id="product-modal-drinks" class="mb-3"></div>
                            <h3 class="mt-4">Special Instructions</h3>
                            <textarea class="form-control" id="product-modal-instructions" rows="3" placeholder="Any special requests?"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>
                        Close
                    </button>
                    <button type="button" class="btn btn-primary" id="add-to-cart-modal">
                        <i class="bi bi-cart-plus me-2"></i>
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Thank You Modal -->
    <div class="modal fade" id="thank-you-modal" tabindex="-1" aria-labelledby="thank-you-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title" id="thank-you-modal-label">Thank You for Your Order!</h5>
                </div>
                <div class="modal-body">
                    <p>Your order has been placed successfully. It will be delivered in approximately 40-60 minutes.</p>
                </div>
                <div class="modal-footer justify-content-center border-top-0">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="bi bi-check-circle me-2"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Toast Container -->
    <div class="toast-container"></div>
    <!-- Bootstrap Bundle with Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Enhanced jQuery Script -->
    <!-- ... [Previous HTML code remains unchanged] ... -->
    <script>
        $(document).ready(function() {
            const translations = {
                de: {
                    restaurantName: "Restaurant",
                    offer: "Angebot",
                    basketMixContent: "10X Hühnchen Tenders<br>10X Scharfe Flügel",
                    offers: "Angebote",
                    newProducts: "Neue Produkte",
                    burgersAndWraps: "Burger & Wraps",
                    newProductsText: "Neue Produkte kommen bald!",
                    burgerSelectionText: "Schauen Sie sich unsere Burger-Auswahl an!",
                    yourOrder: "IHRE BESTELLUNG",
                    workingHours: "Öffnungszeiten:",
                    minimumOrder: "Mindestbestellung:",
                    emptyCart: "Ihr Warenkorb ist leer!",
                    proceedToCheckout: "Zur Kasse",
                    selectLocation: "Wählen Sie Ihren Standort",
                    city: "Stadt",
                    chooseCity: "Wählen Sie eine Stadt",
                    deliveryAddress: "Lieferadresse",
                    confirmLocation: "Standort bestätigen",
                    productDetails: "Produktdetails",
                    extras: "Extras",
                    drinks: "Getränke",
                    specialInstructions: "Besondere Anweisungen",
                    close: "Schließen",
                    addToCart: "In den Warenkorb",
                    edit: "Bearbeiten",
                    save: "Speichern",
                    cancel: "Abbrechen",
                    updateCart: "Warenkorb aktualisieren",
                    locationSet: "Standort festgelegt",
                    thankYouForOrder: "Vielen Dank für Ihre Bestellung!",
                    thankYouPaymentMessage: "Vielen Dank für Ihre Zahlung. Ihre Bestellung wird in ungefähr 40-60 Minuten geliefert.",
                    quantity: "Menge",
                    remove: "Entfernen",
                    anySpecialRequests: "Haben Sie besondere Wünsche?",
                    invalidQuantity: "Ungültige Menge. Bitte geben Sie einen Wert zwischen 1 und 99 ein.",
                    duplicateItem: "Artikel ist bereits im Warenkorb. Die Menge wurde aktualisiert.",
                    removeConfirmation: "Möchten Sie dieses Element wirklich aus dem Warenkorb entfernen?",
                    failedToLoad: "Fehler beim Laden der Daten.",
                    failedToPlaceOrder: "Fehler beim Platzieren der Bestellung.",
                    failedToLoadSizes: "Fehler beim Laden der Größen.",
                    noSizesAvailable: "Keine Größen verfügbar.",
                    noExtrasAvailable: "Keine Extras verfügbar.",
                    noDrinksAvailable: "Keine Getränke verfügbar.",
                    // Add more translations as needed
                },
                sq: {
                    restaurantName: "Restoranti",
                    offer: "Ofertë",
                    basketMixContent: "10X Pulë Tenders<br>10X Krahë të Nxehtë",
                    offers: "Oferta",
                    newProducts: "Produkte të Reja",
                    burgersAndWraps: "Hamburgerë & Mbështjellës",
                    newProductsText: "Produkte të reja do të vijnë së shpejti!",
                    burgerSelectionText: "Shikoni përzgjedhjen tonë të hamburgerëve!",
                    yourOrder: "POROSIA JUAJ",
                    workingHours: "Orari i punës:",
                    minimumOrder: "Porosia minimale:",
                    emptyCart: "Shporta juaj është bosh!",
                    proceedToCheckout: "Vazhdo në Arkë",
                    selectLocation: "Zgjidhni vendndodhjen tuaj",
                    city: "Qyteti",
                    chooseCity: "Zgjidhni një qytet",
                    deliveryAddress: "Adresa e dorëzimit",
                    confirmLocation: "Konfirmo vendndodhjen",
                    productDetails: "Detajet e produktit",
                    extras: "Ekstra",
                    drinks: "Pijet",
                    specialInstructions: "Udhëzime të veçanta",
                    close: "Mbyll",
                    addToCart: "Shto në shportë",
                    edit: "Edito",
                    save: "Ruaj",
                    cancel: "Anulo",
                    updateCart: "Përditëso Shportën",
                    locationSet: "Vendndodhja e caktuar",
                    thankYouForOrder: "Faleminderit për Porosinë tuaj!",
                    thankYouPaymentMessage: "Faleminderit për pagesën tuaj. Porosia juaj do të dorëzohet brenda rreth 40-60 minutash.",
                    quantity: "Sasia",
                    remove: "Hiqe",
                    anySpecialRequests: "A keni kërkesa të veçanta?",
                    invalidQuantity: "Sasia e pavlefshme. Ju lutem vendosni një vlerë midis 1 dhe 99.",
                    duplicateItem: "Artikulli është tashmë në shportë. Sasia është përditësuar.",
                    removeConfirmation: "A dëshironi të hiqni këtë element nga shporta?",
                    failedToLoad: "Gabim në ngarkimin e të dhënave.",
                    failedToPlaceOrder: "Gabim në vendosjen e porosisë.",
                    failedToLoadSizes: "Gabim në ngarkimin e madhësive.",
                    noSizesAvailable: "Asnjë madhësi nuk është e disponueshme.",
                    noExtrasAvailable: "Asnjë ekstra nuk është e disponueshme.",
                    noDrinksAvailable: "Asnjë pije nuk është e disponueshme.",
                    // Add more translations as needed
                },
            };
            // State Variables
            let cart = [],
                userLocation = null,
                currentProduct = null,
                currentLanguage = 'de',
                isLoading = true,
                categories = [],
                extras = [],
                drinks = [],
                menuItems = [];
            // Initialize the application
            function init() {
                bindGlobalErrorHandler();
                fetchExtras()
                    .then(() => fetchCategories())
                    .then(() => {
                        renderTabs();
                        fetchProducts(); // Fetch products for the first category or all
                        updateLanguage(currentLanguage);
                        bindEventHandlers();
                        showLocationModalIfNeeded();
                    })
                    .catch(error => {
                        showToast(translations[currentLanguage].failedToLoad);
                    });
            }
            // Bind Global Error Handler
            function bindGlobalErrorHandler() {
                window.onerror = function(message, source, lineno, colno, error) {
                    console.error("Global Error: ", message, source, lineno, colno, error);
                    showToast("An unexpected error occurred. Please try again later.");
                };
            }
            // Fetch Categories from Server
            function fetchCategories() {
                return $.ajax({
                    url: 'get_categories.php',
                    method: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        showLoadingOverlay(true);
                    },
                    success: function(response) {
                        if (response.categories && Array.isArray(response.categories)) {
                            categories = response.categories;
                        } else {
                            console.warn("Categories data is invalid:", response);
                            showToast(translations[currentLanguage].failedToLoad);
                        }
                    },
                    error: function() {
                        showToast(translations[currentLanguage].failedToLoad);
                    },
                    complete: function() {
                        showLoadingOverlay(false);
                    }
                });
            }
            // Fetch Products based on Category
            function fetchProducts(category_id = 0) {
                isLoading = true;
                renderMenuItems();
                return $.ajax({
                    url: 'get_products.php',
                    method: 'GET',
                    data: {
                        category_id
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        showLoadingOverlay(true);
                    },
                    success: function(response) {
                        if (response.products && Array.isArray(response.products)) {
                            menuItems = response.products;
                            isLoading = false;
                            populateMenuItems();
                        } else {
                            console.warn("Products data is invalid:", response);
                            showToast(translations[currentLanguage].failedToLoad);
                        }
                    },
                    error: function() {
                        showToast(translations[currentLanguage].failedToLoad);
                    },
                    complete: function() {
                        showLoadingOverlay(false);
                    }
                });
            }
            // Fetch Extras from Server
            function fetchExtras() {
                return $.ajax({
                    url: 'get_extras.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.extras && Array.isArray(response.extras)) {
                            extras = response.extras;
                            drinks = extras.filter(extra => extra.category === 'drinks');
                        } else {
                            console.warn("Extras data is invalid:", response);
                            showToast(translations[currentLanguage].failedToLoad);
                        }
                    },
                    error: function() {
                        showToast(translations[currentLanguage].failedToLoad);
                    }
                });
            }
            // Render Navigation Tabs based on Categories
            function renderTabs() {
                const t = translations[currentLanguage];
                $('#myTab').empty();
                // Create an "Offers" tab (assuming "offers" is the first tab)
                $('#myTab').append(`
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="offers-tab" data-bs-toggle="tab" data-bs-target="#offers" type="button" role="tab" aria-controls="offers" aria-selected="true">
                            ${t.offers || 'Offers'}
                        </button>
                    </li>
                `);
                // Create a tab for each category
                categories.forEach((category, index) => {
                    $('#myTab').append(`
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="category-${category.id}-tab" data-bs-toggle="tab" data-bs-target="#category-${category.id}" type="button" role="tab" aria-controls="category-${category.id}" aria-selected="false">
                                ${category.name}
                            </button>
                        </li>
                    `);
                });
            }
            // Render Menu Items with Skeletons
            function renderMenuItems() {
                const $menu = $('#menu-items');
                $menu.empty();
                if (isLoading) {
                    // Show skeleton loaders
                    for (let i = 0; i < 6; i++) {
                        $menu.append(`
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="skeleton" style="height: 220px;"></div>
                                    <div class="card-body">
                                        <div class="skeleton mb-2" style="width: 60%; height: 20px;"></div>
                                        <div class="skeleton mb-2" style="width: 80%; height: 15px;"></div>
                                        <div class="skeleton" style="width: 40%; height: 25px;"></div>
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                }
            }
            // Populate Menu Items after loading
            function populateMenuItems() {
                const $menu = $('#menu-items');
                $menu.empty();
                if (menuItems.length === 0) {
                    const t = translations[currentLanguage];
                    $menu.html(`<p class="text-center w-100">${t.newProductsText}</p>`);
                    return;
                }
                $.each(menuItems, function(i, item) {
                    // Parse price as float
                    const price = parseFloat(item.price);
                    if (isNaN(price)) {
                        console.warn(`Product ID ${item.id} ("${item.name}") has an invalid price: "${item.price}". Setting to 0.00€.`);
                    }
                    // Ensure price is a number before calling toFixed
                    const displayPrice = !isNaN(price) ? price.toFixed(2) : '0.00';
                    $menu.append(`
                    <div class="col-md-6 mb-4">
                        <div class="card menu-item h-100 shadow-sm">
                            <img src="${item.image_url}" class="card-img-top" alt="${item.name}" onerror="this.onerror=null; this.src='https://via.placeholder.com/600x400';">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">${item.name}</h5>
                                <p class="card-text">${item.description}</p>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <strong>${displayPrice}€</strong>
                                    <button class="btn btn-sm btn-primary view-product" data-id="${item.id}" aria-label="View details of ${item.name}">
                                        <i class="bi bi-info-circle me-1"></i>
                                        ${translations[currentLanguage].addToCart || 'Add to Cart'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                });
            }
            // Update Language
            function updateLanguage(lang) {
                currentLanguage = lang;
                const t = translations[lang];
                // Update static text elements
                $('.restaurant-name').text(t.restaurantName);
                // Update Tab Labels
                renderTabs();
                // Re-render Menu Items to update button texts
                if (!isLoading) {
                    populateMenuItems();
                }
                // Update placeholders and other dynamic texts
                $('#offers-tab').text(t.offers || 'Offers');
                // Update modals' static text
                $('#location-modal-label').text(t.selectLocation);
                $('label[for="city"]').text(t.city);
                $('#city option:first').text(t.chooseCity);
                $('label[for="address"]').text(t.deliveryAddress);
                $('#location-form button').html(`<i class="bi bi-geo-alt me-2"></i> ${t.confirmLocation}`);
                $('#product-modal-label').text(t.productDetails);
                // Update extras and drinks labels in product modal
                $('#product-modal-extras').prev('h5').text(t.extras);
                $('#product-modal-drinks').prev('h5').text(t.drinks);
                $('#product-modal-instructions').attr('placeholder', t.anySpecialRequests);
                // Update Thank You Modal
                $('#thank-you-modal-label').text(t.thankYouForOrder);
                $('#thank-you-modal .modal-body p').text(t.thankYouPaymentMessage);
                // Update buttons in modals
                $('#product-modal .btn-secondary').html(`<i class="bi bi-x-circle me-2"></i> ${t.close}`);
                $('#thank-you-modal .btn-primary').html(`<i class="bi bi-check-circle me-2"></i> ${t.close}`);
                // Update Language Dropdown Button
                updateLanguageDropdown();
                // Update cart display
                updateCart();
            }
            // Update Language Dropdown Button
            function updateLanguageDropdown() {
                const t = translations[currentLanguage];
                const flagClass = currentLanguage === 'de' ? 'flag-icon-de' : 'flag-icon-al';
                const langLabel = currentLanguage === 'de' ? 'DE' : 'SQ';
                $('#languageDropdown').html(`<span class="flag-icon ${flagClass}"></span> ${langLabel} <i class="bi bi-chevron-down ms-1"></i>`);
            }
            // Update Cart Display
            function updateCart() {
                const t = translations[currentLanguage];
                const $cartItems = $('#cart-items');
                const $cartCount = $('#cart-count');
                $cartItems.empty();
                $cartCount.text(cart.length);
                if (cart.length === 0) {
                    $cartItems.html(`<p class="text-center">${t.emptyCart}</p>`);
                    $('#cart-total').text('0.00€');
                    $('#checkout-btn').prop('disabled', true);
                } else {
                    let total = 0;
                    $.each(cart, function(i, item) {
                        // Validate that item.totalPrice and item.quantity are numbers
                        const itemTotal = (typeof item.totalPrice === 'number' ? item.totalPrice * item.quantity : 0).toFixed(2);
                        total += parseFloat(itemTotal);
                        $cartItems.append(`
                            <div class="border-bottom pb-2 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${item.name} x${item.quantity}</span>
                                    <div>
                                        <strong>${itemTotal}€</strong>
                                        <button class="btn btn-sm btn-outline-secondary edit-cart-item ms-2" data-index="${i}" aria-label="${t.edit} ${item.name}">
                                            <i class="bi bi-chevron-expand"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger remove-from-cart ms-2" data-index="${i}" aria-label="${t.remove} ${item.name}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Editable Section -->
                                <div class="edit-section mt-3" id="edit-section-${i}" style="display: none;">
                                    <form class="edit-form" data-index="${i}" novalidate>
                                        <div class="mb-3">
                                            <label class="form-label">${t.quantity}:</label>
                                            <input type="number" class="form-control quantity-input" name="quantity" value="${item.quantity}" min="1" max="99" required>
                                            <div class="invalid-feedback">
                                                ${t.invalidQuantity}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">${t.extras}:</label>
                                            <div>
                                                ${extras.map(extra => `
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="extras" value="${extra.id}" id="edit-extra-${i}-${extra.id}" ${item.extras && item.extras.some(e => e.id === extra.id) ? 'checked' : ''}>
                                                        <label class="form-check-label" for="edit-extra-${i}-${extra.id}">
                                                            ${extra.name} (+${typeof extra.price === 'number' ? extra.price.toFixed(2) : '0.00'}€)
                                                        </label>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">${t.drinks}:</label>
                                            <div>
                                                ${drinks.map(drink => `
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="drink-${i}" value="${drink.id}" id="edit-drink-${i}-${drink.id}" ${item.drink && item.drink.id === drink.id ? 'checked' : ''}>
                                                        <label class="form-check-label" for="edit-drink-${i}-${drink.id}">
                                                            ${drink.name} (+${typeof drink.price === 'number' ? drink.price.toFixed(2) : '0.00'}€)
                                                        </label>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">${t.specialInstructions}:</label>
                                            <textarea class="form-control" name="instructions" rows="2" placeholder="${t.anySpecialRequests}">${item.specialInstructions || ''}</textarea>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-sm btn-primary me-2">${t.save || 'Save'}</button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-edit" data-index="${i}">${t.cancel || 'Cancel'}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        `);
                    });
                    $('#cart-total').text(total.toFixed(2) + '€');
                    $('#checkout-btn').prop('disabled', false);
                }
            }
            // Show Product Modal with Details
            function showProductModal(productId) {
                // Fetch product details from server if needed
                currentProduct = menuItems.find(item => item.id === productId);
                if (!currentProduct) {
                    showToast(translations[currentLanguage].failedToLoad);
                    return;
                }
                const t = translations[currentLanguage];
                $('#product-modal-name').text(currentProduct.name);
                $('#product-modal-description').text(currentProduct.description);
                $('#product-modal-img').attr('src', currentProduct.image_url).attr('alt', currentProduct.name);
                // Fetch Sizes
                $.ajax({
                        url: 'get_sizes.php',
                        method: 'GET',
                        data: {
                            product_id: productId
                        },
                        dataType: 'json',
                        beforeSend: function() {
                            $('#product-modal-sizes').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');
                        },
                        success: function(response) {
                            if (response.sizes && Array.isArray(response.sizes) && response.sizes.length > 0) {
                                const $sizes = $('#product-modal-sizes');
                                $sizes.empty();
                                $.each(response.sizes, function(i, size) {
                                    // Ensure size.price is a valid number
                                    size.price = parseFloat(size.price) || 0.00;
                                    const displaySizePrice = size.price.toFixed(2);

                                    $sizes.append(`
                        <div class="form-check">
                            <input class="form-check-input size-option" type="radio" name="size" value="${size.id}" id="size-${size.id}" data-price="${size.price}" required>
                            <label class="form-check-label" for="size-${size.id}">
                                ${size.name} (+${displaySizePrice}€)
                            </label>
                        </div>
                    `);
                                });
                            } else {
                                $('#product-modal-sizes').html(`<p>${translations[currentLanguage].noSizesAvailable}</p>`);
                            }
                        },
                        error: function() {
                            showToast(translations[currentLanguage].failedToLoadSizes);
                            $('#product-modal-sizes').html(`<p>${translations[currentLanguage].failedToLoadSizes}</p>`);
                        }


                    }

                );

                function fetchDrinks() {
                    return $.ajax({
                        url: 'get_drinks.php', // Create this endpoint to fetch drinks
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.drinks && Array.isArray(response.drinks)) {
                                populateDrinksDropdown(response.drinks);
                            } else {
                                console.warn("Drinks data is invalid:", response);
                                showToast("Failed to load drinks.");
                            }
                        },
                        error: function() {
                            showToast("Failed to load drinks.");
                        }
                    });
                }
                fetchDrinks();

                function populateDrinksDropdown(drinks) {
                    const $drinksContainer = $('#product-modal-drinks'); // Adjust selector as needed
                    $drinksContainer.empty();
                    if (drinks.length > 0) {
                        drinks.forEach(function(drink) {
                            $drinksContainer.append(`
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="drink" value="${drink.id}" id="drink-${drink.id}">
                    <label class="form-check-label" for="drink-${drink.id}">
                        ${drink.name} (+${drink.price.toFixed(2)}€)
                    </label>
                </div>
            `);
                        });
                    } else {
                        $drinksContainer.html(`<p>No drinks available.</p>`);
                    }
                }
                // Populate Extras
                const $extras = $('#product-modal-extras');
                $extras.empty();
                if (extras.length > 0) {
                    $.each(extras.filter(extra => extra.category !== 'drinks'), function(i, extra) {
                        // Validate that extra.price exists and is a number
                        if (typeof extra.price !== 'number') {
                            console.warn(`Extra ID ${extra.id} ("${extra.name}") is missing a valid price.`);
                            extra.price = 0.00;
                        }
                        const displayExtraPrice = typeof extra.price === 'number' ? extra.price.toFixed(2) : '0.00';
                        $extras.append(`
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${extra.id}" id="extra-${extra.id}">
                                <label class="form-check-label" for="extra-${extra.id}">
                                    ${extra.name} (+${displayExtraPrice}€)
                                </label>
                            </div>
                        `);
                    });
                } else {
                    $extras.html(`<p>${t.noExtrasAvailable}</p>`);
                }
                // Populate Drinks
                const $drinks = $('#product-modal-drinks');
                $drinks.empty();
                if (drinks.length > 0) {
                    $.each(drinks, function(i, drink) {
                        // Validate that drink.price exists and is a number
                        if (typeof drink.price !== 'number') {
                            console.warn(`Drink ID ${drink.id} ("${drink.name}") is missing a valid price.`);
                            drink.price = 0.00;
                        }
                        const displayDrinkPrice = typeof drink.price === 'number' ? drink.price.toFixed(2) : '0.00';
                        $drinks.append(`
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="drink" value="${drink.id}" id="drink-${drink.id}">
                                <label class="form-check-label" for="drink-${drink.id}">
                                    ${drink.name} (+${displayDrinkPrice}€)
                                </label>
                            </div>
                        `);
                    });
                } else {
                    $drinks.html(`<p>${t.noDrinksAvailable}</p>`);
                }
                // Reset Instructions
                $('#product-modal-instructions').val('');
                // Reset size selection
                $('#product-modal-sizes').find('.size-option').prop('checked', false);
                // Reset extras selection
                $('#product-modal-extras').find('.form-check-input').prop('checked', false);
                // Reset drinks selection
                $('#product-modal-drinks').find('.form-check-input').prop('checked', false);
                // Show Modal
                $('#product-modal').modal('show');
            }
            // Add Product to Cart from Modal
            function addToCartFromModal() {
                const t = translations[currentLanguage];
                // Disable the button to prevent multiple clicks
                const $button = $('#add-to-cart-modal');
                $button.prop('disabled', true).html(`<i class="bi bi-cart-plus me-2"></i> Adding...`);
                try {
                    const selectedSizeId = $('input[name="size"]:checked').val();
                    if (!selectedSizeId) {
                        showToast(t.failedToLoadSizes); // Alternatively, prompt to select size
                        $button.prop('disabled', false).html(`<i class="bi bi-cart-plus me-2"></i> ${t.addToCart}`);
                        return;
                    }
                    // Fetch the selected size's price from the DOM
                    const selectedSizePrice = parseFloat($(`input[name="size"]:checked`).data('price'));
                    if (isNaN(selectedSizePrice)) {
                        console.warn(`Selected size ID ${selectedSizeId} has an invalid price: "${$(`input[name="size"]:checked`).data('price')}". Setting to 0.00€.`);
                    }
                    const displaySizePrice = !isNaN(selectedSizePrice) ? selectedSizePrice.toFixed(2) : '0.00';
                    const selectedSizeName = $(`label[for="size-${selectedSizeId}"]`).text().split(' (+')[0];
                    const selectedExtras = $('input[type="checkbox"]:checked').map(function() {
                        const extraId = parseInt($(this).val());
                        const extraName = $(`label[for="extra-${extraId}"]`).text().split(' (+')[0];
                        const extraPriceText = $(`label[for="extra-${extraId}"]`).text().match(/\+\d+\.\d{2}€/);
                        let extraPrice = 0.00;
                        if (extraPriceText) {
                            extraPrice = parseFloat(extraPriceText[0].replace('+', '').replace('€', ''));
                            if (isNaN(extraPrice)) {
                                console.warn(`Extra ID ${extraId} ("${extraName}") has an invalid price: "${extraPriceText[0]}". Setting to 0.00€.`);
                                extraPrice = 0.00;
                            }
                        }
                        return {
                            id: extraId,
                            name: extraName,
                            price: extraPrice
                        };
                    }).get();
                    const selectedDrinkInput = $('input[name="drink"]:checked');
                    let selectedDrink = null;
                    if (selectedDrinkInput.length > 0) {
                        const drinkId = parseInt(selectedDrinkInput.val());
                        const drinkName = $(`label[for="drink-${drinkId}"]`).text().split(' (+')[0];
                        const drinkPriceText = $(`label[for="drink-${drinkId}"]`).text().match(/\+\d+\.\d{2}€/);
                        let drinkPrice = 0.00;
                        if (drinkPriceText) {
                            drinkPrice = parseFloat(drinkPriceText[0].replace('+', '').replace('€', ''));
                            if (isNaN(drinkPrice)) {
                                console.warn(`Drink ID ${drinkId} ("${drinkName}") has an invalid price: "${drinkPriceText[0]}". Setting to 0.00€.`);
                                drinkPrice = 0.00;
                            }
                        }
                        selectedDrink = {
                            id: drinkId,
                            name: drinkName,
                            price: drinkPrice
                        };
                    }
                    const specialInstructions = $('#product-modal-instructions').val().trim();
                    // Calculate Total Price
                    const extrasTotal = selectedExtras.reduce((sum, extra) => sum + extra.price, 0);
                    const drinkPrice = selectedDrink ? selectedDrink.price : 0;
                    const totalPrice = selectedSizePrice + extrasTotal + drinkPrice;
                    // Check for Duplicate Item
                    const duplicateIndex = cart.findIndex(item => item.id === currentProduct.id &&
                        item.size.id === parseInt(selectedSizeId) &&
                        JSON.stringify(item.extras) === JSON.stringify(selectedExtras) &&
                        ((item.drink && selectedDrink && item.drink.id === selectedDrink.id) || (!item.drink && !selectedDrink)) &&
                        item.specialInstructions === specialInstructions
                    );
                    if (duplicateIndex !== -1) {
                        // Increment quantity if duplicate found
                        cart[duplicateIndex].quantity += 1;
                        cart[duplicateIndex].totalPrice += totalPrice;
                        updateCart();
                        $('#product-modal').modal('hide');
                        showToast(`${currentProduct.name} ${t.duplicateItem}`);
                        $button.prop('disabled', false).html(`<i class="bi bi-cart-plus me-2"></i> ${t.addToCart}`);
                        return;
                    }
                    // Add to Cart
                    cart.push({
                        id: currentProduct.id,
                        name: currentProduct.name,
                        size: {
                            id: parseInt(selectedSizeId),
                            name: selectedSizeName,
                            price: selectedSizePrice
                        },
                        extras: selectedExtras,
                        drink: selectedDrink,
                        specialInstructions: specialInstructions,
                        totalPrice: totalPrice,
                        quantity: 1
                    });
                    // Update UI
                    updateCart();
                    $('#product-modal').modal('hide');
                    // Show a toast or notification
                    showToast(`${currentProduct.name} ${t.addToCart}`);
                } catch (error) {
                    console.error("Add to Cart Error:", error);
                    showToast(t.failedToLoad);
                } finally {
                    // Re-enable the button
                    $button.prop('disabled', false).html(`<i class="bi bi-cart-plus me-2"></i> ${t.addToCart}`);
                }
            }
            // Remove Item from Cart
            function removeFromCart(index) {
                const t = translations[currentLanguage];
                if (confirm(t.removeConfirmation)) {
                    const removedItem = cart.splice(index, 1)[0];
                    updateCart();
                    showToast(`${removedItem.name} ${t.remove}`);
                }
            }
            // Show Toast Notification
            function showToast(message) {
                const toastId = `toast-${Date.now()}`;
                $('.toast-container').append(`
                    <div id="${toastId}" class="toast align-items-center text-bg-primary border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `);
                const toastElement = new bootstrap.Toast(`#${toastId}`, {
                    delay: 5000
                });
                toastElement.show();
                // Remove the toast element after it hides
                $(`#${toastId}`).on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }
            // Proceed to Checkout
            function proceedToCheckout() {
                const t = translations[currentLanguage];
                // Check if the cart is empty
                if (cart.length === 0) {
                    showToast(t.emptyCart);
                    return;
                }
                // Check if the user has set their location
                if (!userLocation) {
                    // Prompt the user to set their location
                    $('#location-modal').modal('show');
                    return;
                }
                // Validate userLocation fields
                if (!userLocation.name || !userLocation.email || !userLocation.phone || !userLocation.address || !userLocation.city) {
                    showToast(t.failedToLoad); // Or a more specific error message
                    return;
                }
                // Calculate total amount
                let totalAmount = 0.00;
                cart.forEach(item => {
                    totalAmount += item.totalPrice * item.quantity;
                });
                totalAmount = totalAmount.toFixed(2);
                // Populate the confirmation modal with order details
                $('#confirm-customer-name').text(userLocation.name || 'Guest');
                $('#confirm-customer-email').text(userLocation.email || 'guest@example.com');
                $('#confirm-customer-phone').text(userLocation.phone || '1234567890');
                $('#confirm-delivery-address').text(`${userLocation.address}, ${userLocation.city}`);
                // Populate cart items in the modal
                const $confirmCartItems = $('#confirm-cart-items');
                $confirmCartItems.empty(); // Clear previous entries
                cart.forEach((item, index) => {
                    let extrasList = '';
                    if (item.extras && item.extras.length > 0) {
                        extrasList = '<ul>';
                        item.extras.forEach(extra => {
                            extrasList += `<li>${extra.name} (+${extra.price}€)</li>`;
                        });
                        extrasList += '</ul>';
                    }
                    let drinkInfo = '';
                    if (item.drink) {
                        drinkInfo = `, Drink: ${item.drink.name} (+${item.drink.price}€)`;
                    }
                    $confirmCartItems.append(`
            <div class="mb-3">
                <strong>${item.name}</strong> (Size: ${item.size.name || 'N/A'}) x${item.quantity}${drinkInfo}<br>
                ${extrasList}
                ${item.specialInstructions ? `<em>Instructions:</em> ${item.specialInstructions}` : ''}
                <hr>
            </div>
        `);
                });
                $('#confirm-total-amount').text(totalAmount);
                // Special Instructions (if any)
                let allInstructions = '';
                cart.forEach(item => {
                    if (item.specialInstructions) {
                        allInstructions += `- ${item.specialInstructions}<br>`;
                    }
                });
                $('#confirm-special-instructions').html(allInstructions || 'None');
                // Show the confirmation modal
                $('#checkout-confirmation-modal').modal('show');
            }
            // Handle Order Confirmation
            $('#confirm-order-btn').on('click', function() {
                const t = translations[currentLanguage];
                // Prepare the order data
                const orderData = {
                    customer_name: userLocation.name.trim() || 'Guest',
                    customer_email: userLocation.email.trim() || 'guest@example.com',
                    customer_phone: userLocation.phone.trim() || '1234567890',
                    delivery_address: `${userLocation.address.trim()}, ${userLocation.city.trim()}`,
                    cart: cart.map(item => ({
                        id: item.id,
                        quantity: item.quantity,
                        totalPrice: parseFloat(item.totalPrice.toFixed(2)),
                        size: {
                            id: item.size.id
                        },
                        extras: item.extras ? item.extras.map(extra => ({
                            id: extra.id,
                            price: parseFloat(extra.price.toFixed(2))
                        })) : [],
                        drink: item.drink ? {
                            id: item.drink.id,
                            price: parseFloat(item.drink.price.toFixed(2))
                        } : null,
                        specialInstructions: item.specialInstructions ? item.specialInstructions.trim() : ''
                    }))
                };
                // Optional: Log the orderData for debugging (Remove or disable in production)
                console.log("Order Data:", orderData);
                // Send order data to the server
                $.ajax({
                    url: 'place_order.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(orderData),
                    dataType: 'json',
                    beforeSend: function() {
                        showLoadingOverlay(true);
                        $('#confirm-order-btn').prop('disabled', true).html(`<i class="bi bi-check-circle me-2"></i> Confirming...`);
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#checkout-confirmation-modal').modal('hide');
                            $('#thank-you-modal').modal('show');
                            // Reset cart after successful order
                            cart = [];
                            updateCart();
                        } else if (response.error) {
                            showToast(response.error);
                        } else {
                            showToast(t.failedToPlaceOrder);
                        }
                    },
                    error: function(xhr) {
                        let response = {};
                        try {
                            response = xhr.responseJSON;
                        } catch (e) {
                            console.error("Failed to parse error response:", e);
                        }
                        if (response && response.error) {
                            showToast(response.error);
                        } else {
                            showToast(t.failedToPlaceOrder);
                        }
                    },
                    complete: function() {
                        showLoadingOverlay(false);
                        $('#confirm-order-btn').prop('disabled', false).html(`<i class="bi bi-check-circle me-2"></i> Confirm Order`);
                    }
                });
            });
            // Handle Location Form Submission
            function handleLocationForm(e) {
                e.preventDefault();
                const form = e.target;
                if (form.checkValidity() === false) {
                    e.stopPropagation();
                    $(form).addClass('was-validated');
                    return;
                }
                const city = $('#city').val();
                const address = $('#address').val().trim();
                if (city && address) {
                    userLocation = {
                        city,
                        address,
                        // Optionally, collect name, email, phone here or elsewhere
                        name: 'Guest', // Replace with actual data collection if available
                        email: 'guest@example.com',
                        phone: '1234567890'
                    };
                    $('#location-modal').modal('hide');
                    showToast(`${translations[currentLanguage].locationSet}: ${address}, ${city}`);
                    // Optionally, proceed to checkout automatically
                    // proceedToCheckout();
                }
            }
            // Toggle Edit Section in Cart
            function toggleEditSection(index) {
                const $editSection = $(`#edit-section-${index}`);
                const $editButtonIcon = $(`.edit-cart-item[data-index="${index}"] i`);
                if ($editSection.is(':visible')) {
                    $editSection.slideUp();
                    $editButtonIcon.removeClass('bi-chevron-up').addClass('bi-chevron-expand');
                } else {
                    // Close any other open edit sections
                    $('.edit-section').slideUp();
                    $('.edit-cart-item i').removeClass('bi-chevron-up').addClass('bi-chevron-expand');
                    // Open the selected edit section
                    $editSection.slideDown();
                    $editButtonIcon.removeClass('bi-chevron-expand').addClass('bi-chevron-up');
                }
            }
            // Save Edited Cart Item
            function saveEditedCartItem(index, formData) {
                const t = translations[currentLanguage];
                const quantity = parseInt(formData.quantity);
                const selectedExtras = extras.filter(extra => formData.extras && formData.extras.includes(extra.id.toString())).map(extra => ({
                    id: extra.id,
                    name: extra.name,
                    price: extra.price
                }));
                const selectedDrink = drinks.find(drink => drink.id === parseInt(formData[`drink-${index}`])) || null;
                const specialInstructions = formData.instructions.trim();
                // Validate quantity
                if (isNaN(quantity) || quantity < 1 || quantity > 99) {
                    showToast(t.invalidQuantity);
                    return;
                }
                // Calculate new total price
                const sizePrice = cart[index].size.price;
                const extrasTotal = selectedExtras.reduce((sum, extra) => sum + extra.price, 0);
                const drinkPrice = selectedDrink ? selectedDrink.price : 0;
                const newTotalPrice = sizePrice + extrasTotal + drinkPrice;
                // Check for Duplicate Item
                const duplicateIndex = cart.findIndex((item, idx) => idx !== index &&
                    item.id === cart[index].id &&
                    item.size.id === cart[index].size.id &&
                    JSON.stringify(item.extras) === JSON.stringify(selectedExtras) &&
                    ((item.drink && selectedDrink && item.drink.id === selectedDrink.id) || (!item.drink && !selectedDrink)) &&
                    item.specialInstructions === specialInstructions
                );
                if (duplicateIndex !== -1) {
                    // Merge with existing duplicate item
                    cart[duplicateIndex].quantity += quantity;
                    cart[duplicateIndex].totalPrice += newTotalPrice;
                    // Remove the original item
                    cart.splice(index, 1);
                    updateCart();
                    showToast(`${cart[duplicateIndex].name} ${t.duplicateItem}`);
                } else {
                    // Update cart item
                    cart[index] = {
                        id: cart[index].id,
                        name: cart[index].name,
                        size: cart[index].size,
                        extras: selectedExtras,
                        drink: selectedDrink,
                        specialInstructions: specialInstructions,
                        totalPrice: newTotalPrice,
                        quantity: quantity
                    };
                    updateCart();
                    showToast(`${cart[index].name} ${t.updateCart}`);
                }
                // Close the edit section
                toggleEditSection(index);
            }
            // Cancel Editing Cart Item
            function cancelEditing(index) {
                toggleEditSection(index);
            }
            // Show Location Modal if user hasn't set location
            function showLocationModalIfNeeded() {
                if (!userLocation) {
                    $('#location-modal').modal({
                        backdrop: 'static',
                        keyboard: false
                    });
                }
            }
            // Show Loading Overlay
            function showLoadingOverlay(show) {
                if (show) {
                    $('#loading-overlay').addClass('active').attr('aria-hidden', 'false');
                } else {
                    $('#loading-overlay').removeClass('active').attr('aria-hidden', 'true');
                }
            }
            // Bind Event Handlers
            function bindEventHandlers() {
                // Language Switcher
                $('.language-switcher').on('click', '.language-option', function(e) {
                    e.preventDefault();
                    const selectedLang = $(this).data('lang');
                    updateLanguage(selectedLang);
                });
                // View Product
                $('#menu-items').on('click', '.view-product', function() {
                    const productId = $(this).data('id');
                    showProductModal(productId);
                });
                // Add to Cart from Modal
                $('#add-to-cart-modal').on('click', addToCartFromModal);
                // Remove from Cart
                $('#cart-items').on('click', '.remove-from-cart', function() {
                    const index = $(this).data('index');
                    removeFromCart(index);
                });
                // Checkout Button
                $('#checkout-btn').on('click', proceedToCheckout);
                // Close Cart Sidebar (if applicable)
                $('#close-cart-btn').on('click', function() {
                    $('#cart-sidebar').removeClass('active').attr('aria-hidden', 'true');
                });
                // Cart Button to Toggle Sidebar (if applicable)
                $('#cart-button').on('click', function() {
                    $('#cart-sidebar').toggleClass('active');
                    const isActive = $('#cart-sidebar').hasClass('active');
                    $('#cart-sidebar').attr('aria-hidden', !isActive);
                });
                // Location Form Submission
                $('#location-form').on('submit', handleLocationForm);
                // Edit Cart Item
                $('#cart-items').on('click', '.edit-cart-item', function() {
                    const index = $(this).data('index');
                    toggleEditSection(index);
                });
                // Save Edited Cart Item
                $('#cart-items').on('submit', '.edit-form', function(e) {
                    e.preventDefault();
                    const form = e.target;
                    if (form.checkValidity() === false) {
                        e.stopPropagation();
                        $(form).addClass('was-validated');
                        return;
                    }
                    const index = $(this).data('index');
                    const formData = $(this).serializeArray().reduce((acc, field) => {
                        if (acc[field.name]) {
                            if (Array.isArray(acc[field.name])) {
                                acc[field.name].push(field.value);
                            } else {
                                acc[field.name] = [acc[field.name], field.value];
                            }
                        } else {
                            acc[field.name] = field.value;
                        }
                        return acc;
                    }, {});
                    saveEditedCartItem(index, formData);
                });
                // Cancel Editing Cart Item
                $('#cart-items').on('click', '.cancel-edit', function() {
                    const index = $(this).data('index');
                    cancelEditing(index);
                });
                // Handle Enter key for accessibility in modals
                $('.modal').on('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $(this).find('button[type="button"], button[type="submit"]').first().click();
                    }
                });
                // Image Error Handling
                $('img').on('error', function() {
                    $(this).attr('src', 'https://via.placeholder.com/600x400').addClass('img-fluid rounded');
                });
            }
            // Initialize the app
            init();
        });
    </script>
    <!-- ... [Remaining HTML code] ... -->
</body>

</html>