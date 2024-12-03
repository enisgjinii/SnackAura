<!-- Special Offers Section -->
<?php if (!empty($active_offers)): ?>
    <section class="offers-section py-5">
        <div class="container">
            <h2 class="mb-4 text-center">Special Offers</h2>
            <div class="row g-4">
                <?php foreach ($active_offers as $offer): ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm position-relative">
                            <?php if ($offer['image'] && file_exists($offer['image'])): ?>
                                <a href="offers.php?action=view&id=<?= htmlspecialchars($offer['id']) ?>">
                                    <img src="<?= htmlspecialchars($offer['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($offer['title']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/600x400?text=Offer+Image';">
                                </a>
                            <?php else: ?>
                                <img src="https://via.placeholder.com/600x400?text=No+Image" class="card-img-top" alt="No Image Available" loading="lazy">
                            <?php endif; ?>
                            <?php if ($offer['is_active']): ?>
                                <span class="badge bg-success position-absolute top-0 end-0 m-2">Active</span>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($offer['title']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($offer['description'])) ?></p>
                                <div class="mt-auto">
                                    <a href="offers.php?action=view&id=<?= htmlspecialchars($offer['id']) ?>" class="btn btn-primary w-100">
                                        View Offer
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <?= htmlspecialchars($offer['start_date'] ?? 'N/A') ?> - <?= htmlspecialchars($offer['end_date'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>