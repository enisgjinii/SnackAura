<footer class="bg-light text-muted pt-5 pb-4">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                    <?php if (!empty($main_store['logo'])): ?>
                        <img src="<?= htmlspecialchars($main_store['logo']) ?>" alt="Logo" width="40" height="40" class="me-2">
                    <?php endif; ?>
                    <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>
                </h5>
                <p>Experience the finest dining with us.</p>
            </div>
            <div class="col-md-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">AGB</h5>
                <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#agbModal">Read AGB</button>
            </div>
            <div class="col-md-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Impressum</h5>
                <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#impressumModal">View Impressum</button>
            </div>
            <div class="col-md-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Datenschutz</h5>
                <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#datenschutzModal">Privacy Policy</button>
            </div>
        </div>
        <hr class="mb-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <p>© <?= date('Y') ?> <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>. All rights reserved.</p>
            </div>
            <div class="col-md-5 text-end">
                <div class="social-media">
                    <?php
                    foreach (['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'] as $social) {
                        if (!empty($main_store[$social])): ?>
                            <a href="<?= htmlspecialchars($main_store[$social]) ?>" target="_blank">
                                <i class="bi bi-<?= explode('_', $social)[0] ?>"></i>
                            </a>
                    <?php endif;
                    } ?>
                </div>
            </div>
        </div>
    </div>
</footer>