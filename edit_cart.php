<!-- Edit Cart Modals -->
<?php foreach ($_SESSION['cart'] as $index => $item): ?>
    <div class="modal fade" id="editCartModal<?= $index ?>" tabindex="-1" aria-labelledby="editCartModalLabel<?= $index ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCartModalLabel<?= $index ?>">Edit <?= htmlspecialchars($item['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="quantity<?= $index ?>" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity<?= $index ?>" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max="99" required>
                        </div>
                        <?php if (!empty($products[$item['product_id']]['sizes'])): ?>
                            <div class="mb-3">
                                <label for="size<?= $index ?>" class="form-label">Size</label>
                                <select class="form-select" id="size<?= $index ?>" name="size" required>
                                    <option value="">Choose a size</option>
                                    <?php foreach ($products[$item['product_id']]['sizes'] as $size): ?>
                                        <option value="<?= htmlspecialchars($size['id']) ?>" <?= ($item['size_id'] === (int)$size['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if ($products[$item['product_id']]['extras']): ?>
                            <div class="mb-3">
                                <label class="form-label">Extras</label>
                                <div>
                                    <?php foreach ($products[$item['product_id']]['extras'] as $extra): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_extra<?= $index ?>_<?= $extra['id'] ?>" name="extras[]" value="<?= htmlspecialchars($extra['id']) ?>" <?= in_array($extra['id'], array_column($item['extras'], 'id')) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_extra<?= $index ?>_<?= $extra['id'] ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($products[$item['product_id']]['sauces']): ?>
                            <div class="mb-3">
                                <label class="form-label">Sauces</label>
                                <div>
                                    <?php foreach ($products[$item['product_id']]['sauces'] as $sauce_id): ?>
                                        <?php if (isset($sauce_details[$sauce_id])): ?>
                                            <?php $sauce = $sauce_details[$sauce_id]; ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="edit_sauce<?= $index ?>_<?= $sauce['id'] ?>" name="sauces[]" value="<?= htmlspecialchars($sauce['id']) ?>" <?= in_array($sauce['id'], array_column($item['sauces'], 'id')) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="edit_sauce<?= $index ?>_<?= $sauce['id'] ?>"><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format($sauce['price'], 2) ?>€)</label>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($drinks): ?>
                            <div class="mb-3">
                                <label class="form-label">Drinks</label>
                                <select class="form-select" name="drink">
                                    <option value="">Choose a drink</option>
                                    <?php foreach ($drinks as $drink): ?>
                                        <option value="<?= htmlspecialchars($drink['id']) ?>" <?= ($item['drink']['id'] ?? null) === $drink['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="special_instructions<?= $index ?>" class="form-label">Special Instructions</label>
                            <textarea class="form-control" id="special_instructions<?= $index ?>" name="special_instructions" rows="2" placeholder="Any special requests?"><?= htmlspecialchars($item['special_instructions']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="item_index" value="<?= $index ?>">
                        <input type="hidden" name="update_cart" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Cart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>