<header class="position-relative">
    <nav class="navbar navbar-expand-lg navbar-light bg-light py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo Section -->
            <a href="index.php" class="navbar-brand d-flex align-items-center">
                <?php if (!empty($main_store['logo'])) : ?>
                    <img src="admin/<?= htmlspecialchars($main_store['logo']) ?>"
                        alt="Cart Logo"
                        class="img-fluid"
                        onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';"
                        style="width: 80px; height: 50px; object-fit: cover"
                        id="cart-logo">
                <?php endif; ?>
            </a>

            <!-- Toggle Button for Mobile View -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarActions" aria-controls="navbarActions" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Actions Section -->
            <div class="collapse navbar-collapse" id="navbarActions">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Cart Button -->
                    <li class="nav-item me-2">
                        <button class="btn btn-secondary position-relative" id="cart-button" data-bs-toggle="modal" data-bs-target="#cartModal">
                            <i class="bi bi-cart fs-5"></i>
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                                    <?= count($_SESSION['cart']) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    </li>

                    <!-- Reservation Button -->
                    <li class="nav-item me-2">
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#reservationModal">
                            <i class="bi bi-calendar-plus fs-5"></i>
                        </button>
                    </li>

                    <!-- Ratings Button -->
                    <li class="nav-item me-2">
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                            <i class="bi bi-star fs-5"></i>
                        </button>
                    </li>

                    <!-- Cart Total -->
                    <li class="nav-item">
                        <span class="fw-bold fs-6 ms-2" id="cart-total">
                            <?= number_format($cart_total_with_tip, 2) ?>€
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>