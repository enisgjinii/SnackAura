<header class="position-relative">
    <nav class="navbar navbar-expand-lg navbar-light bg-light py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo and Brand -->
            <a href="index.php" class="navbar-brand d-flex align-items-center">
                <?php if (!empty($main_store['logo'])) : ?>
                    <img src="admin/<?= htmlspecialchars($main_store['logo']) ?>"
                        alt="Cart Logo"
                        class="img-fluid navbar-logo"
                        onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';"
                        id="cart-logo">
                <?php endif; ?>
                <span class="navbar-brand-text ms-2">SnackAura</span>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarActions" aria-controls="navbarActions" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Actions -->
            <div class="collapse navbar-collapse" id="navbarActions">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Cart Button -->
                    <li class="nav-item me-3">
                        <button class="btn btn-cart position-relative" id="cart-button" data-bs-toggle="modal" data-bs-target="#cartModal">
                            <i class="bi bi-cart3 fs-5"></i>
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge" id="cart-count">
                                    <?= count($_SESSION['cart']) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    </li>
                    
                    <!-- Reservation Button -->
                    <li class="nav-item me-3">
                        <button class="btn btn-action" data-bs-toggle="modal" data-bs-target="#reservationModal">
                            <i class="bi bi-calendar-plus fs-5"></i>
                            <span class="btn-text">Reserve</span>
                        </button>
                    </li>
                    
                    <!-- Ratings Button -->
                    <li class="nav-item me-3">
                        <button class="btn btn-action" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                            <i class="bi bi-star fs-5"></i>
                            <span class="btn-text">Rate</span>
                        </button>
                    </li>
                    
                    <!-- Cart Total -->
                    <li class="nav-item">
                        <div class="cart-total-display">
                            <span class="cart-total-label">Total:</span>
                            <span class="fw-bold fs-6 ms-2 cart-total-amount" id="cart-total">
                                <?= number_format($cartTotalWithTip ?? 0.00, 2) ?>€
                            </span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>