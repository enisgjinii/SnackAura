<header class="position-relative">
    <nav class="navbar navbar-expand-lg navbar-light bg-light py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="d-flex align-items-center">
                <?php if (!empty($main_store['logo'])) : ?>
                    <div class="text-center">
                        <img src="admin/<?= $main_store['logo'] ?>" alt="Cart Logo" class="img-fluid" onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';" style="width: 80px; height: 50px; object-fit: cover" id="cart-logo">
                    </div>
                <?php endif; ?>
            </a>
            <div class="d-flex align-items-center">
                <button class="btn btn-secondary position-relative me-2 btn-add-to-cart" id="cart-button" data-bs-toggle="modal" data-bs-target="#cartModal">
                    <i class="bi bi-cart fs-5"></i>
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                            <?= count($_SESSION['cart']) ?>
                        </span>
                    <?php endif; ?>
                </button>
                <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#reservationModal">
                    <i class="bi bi-calendar-plus fs-5"></i>
                </button>
                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                    <i class="bi bi-star fs-5"></i>
                </button>
                <span class="fw-bold fs-6 ms-2" id="cart-total">
                    <?= number_format($cart_total_with_tip, 2) ?>€
                </span>
            </div>
        </div>
    </nav>
    <div class="language-switcher mt-1 d-flex justify-content-end pe-3">
        <a href="#" class="me-2"><span class="flag-icon flag-icon-us"></span></a>
        <a href="#"><span class="flag-icon flag-icon-al"></span></a>
    </div>
</header>