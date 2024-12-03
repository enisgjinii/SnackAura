<!-- Header with Navbar -->
<header class="position-relative">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo Section -->
            <a href="index.php" class="d-flex align-items-center">
                <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                    <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo"
                        style="width: 100%; height: 80px; object-fit: cover" id="cart-logo" loading="lazy"
                        onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                <?php endif; ?>
            </a>
            <!-- Action Buttons -->
            <div class="d-flex align-items-center">
                <!-- Cart Button -->
                <button class="btn btn-secondary position-relative me-3 btn-add-to-cart" id="cart-button"
                    data-bs-toggle="modal" data-bs-target="#cartModal" aria-label="View Cart">
                    <i class="bi bi-cart fs-4"></i>
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                            <?= count($_SESSION['cart']) ?>
                        </span>
                    <?php endif; ?>
                </button>
                <!-- Reservation Button -->
                <button class="btn btn-secondary border-1 me-3" data-bs-toggle="modal" data-bs-target="#reservationModal">
                    <i class="bi bi-calendar-plus fs-4"></i> Rezervo
                </button>
                <!-- Ratings Button -->
                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                    <i class="bi bi-star fs-4"></i>
                </button>
                <!-- Cart Total -->
                <span class="fw-bold fs-5 ms-3" id="cart-total"><?= number_format($cart_total_with_tip, 2) ?>€</span>
            </div>
        </div>
    </nav>
    <!-- Language Switcher -->
    <div class="language-switcher">
        <a href="#" class="me-2"><span class="flag-icon flag-icon-us"></span></a>
        <a href="#"><span class="flag-icon flag-icon-al"></span></a>
    </div>
</header>