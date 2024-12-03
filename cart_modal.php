<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Your Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($_SESSION['cart']): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                    <?php if ($item['extras']): ?>
                                        <ul>
                                            <?php foreach ($item['extras'] as $extra): ?>
                                                <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if ($item['drink']): ?>
                                        <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                    <?php endif; ?>
                                    <?php if ($item['sauces']): ?>
                                        <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($item['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($item['sauces'], fn($carry, $sauce) => $carry + $sauce['price'], 0.00), 2) ?>€)</p>
                                    <?php endif; ?>
                                    <?php if ($item['special_instructions']): ?>
                                        <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= number_format($item['total_price'], 2) ?>€</strong><br>
                                    <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                    <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($tip_amount > 0): ?>
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>Tip</div>
                                <div><?= number_format($tip_amount, 2) ?>€</div>
                            </li>
                        </ul>
                    <?php endif; ?>
                    <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                    <button class="btn btn-success w-100 mt-3 btn-checkout" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                <?php else: ?>
                    <p>Your cart is empty.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>