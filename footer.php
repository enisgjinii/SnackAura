<footer class="bg-light text-muted py-4">
    <div class="container">
        <div class="row mb-4">
            <!-- Store Information -->
            <div class="col-12 col-md-3 mb-4 mb-md-0">
                <h5 class="text-uppercase fw-bold text-dark d-flex align-items-center">
                    <?php if (!empty($main_store['logo'])): ?>
                        <img src="<?= htmlspecialchars($main_store['logo']) ?>"
                            alt="Logo"
                            width="40"
                            height="40"
                            class="me-2">
                    <?php endif; ?>
                    <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>
                </h5>
                <p class="mb-0">Experience the finest dining with us.</p>
            </div>

            <!-- AGB Section -->
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h5 class="text-uppercase fw-bold text-dark">AGB</h5>
                <button type="button"
                    class="btn btn-link p-0 text-reset"
                    data-bs-toggle="modal"
                    data-bs-target="#agbModal">
                    Read AGB
                </button>
            </div>

            <!-- Impressum Section -->
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h5 class="text-uppercase fw-bold text-dark">Impressum</h5>
                <button type="button"
                    class="btn btn-link p-0 text-reset"
                    data-bs-toggle="modal"
                    data-bs-target="#impressumModal">
                    View Impressum
                </button>
            </div>

            <!-- Datenschutz Section -->
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h5 class="text-uppercase fw-bold text-dark">Datenschutz</h5>
                <button type="button"
                    class="btn btn-link p-0 text-reset"
                    data-bs-toggle="modal"
                    data-bs-target="#datenschutzModal">
                    Privacy Policy
                </button>
            </div>
        </div>

        <hr class="mb-4">

        <div class="row align-items-center">
            <!-- Copyright -->
            <div class="col-12 col-md-7 mb-3 mb-md-0">
                <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>. All rights reserved.</p>
            </div>

            <!-- Social Media Links -->
            <div class="col-12 col-md-5 text-center text-md-end">
                <div class="d-flex justify-content-center justify-content-md-end gap-3">
                    <?php
                    $socialPlatforms = [
                        'facebook_link' => 'facebook',
                        'twitter_link' => 'twitter',
                        'instagram_link' => 'instagram',
                        'linkedin_link' => 'linkedin',
                        'youtube_link' => 'youtube'
                    ];
                    foreach ($socialPlatforms as $key => $platform) {
                        if (!empty($main_store[$key])): ?>
                            <a href="<?= htmlspecialchars($main_store[$key]) ?>" target="_blank" class="text-reset">
                                <i class="bi bi-<?= $platform ?> fs-5"></i>
                            </a>
                    <?php endif;
                    } ?>
                </div>
            </div>
        </div>
    </div>
</footer>