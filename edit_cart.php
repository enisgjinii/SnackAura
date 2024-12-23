<?php
// Ensure that $products is accessible and keyed by product_id.
// If currently $products is a simple numeric array, create an associative array keyed by product ID.
$products_by_id = [];
foreach ($products as $p) {
    $products_by_id[$p['id']] = $p;
}
$products = $products_by_id;

function in_cart($needle_id, $items)
{
    // Checks if needle_id is in items by their 'id' field
    foreach ($items as $it) {
        if (isset($it['id']) && $it['id'] == $needle_id) return true;
    }
    return false;
}
?>

<?php foreach ($_SESSION['cart'] as $index => $item): ?>
    <?php
    // Safely fetch the product by product_id
    if (!isset($products[$item['product_id']])) {
        // Product not found in $products, skip this cart item
        continue;
    }
    $product = $products[$item['product_id']];
    ?>
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

                        <?php if (!empty($product['sizes'])): ?>
                            <div class="mb-3">
                                <label for="size<?= $index ?>" class="form-label">Size</label>
                                <select class="form-select" id="size<?= $index ?>" name="size">
                                    <option value="">Choose a size</option>
                                    <?php foreach ($product['sizes'] as $sz): ?>
                                        <?php
                                        // Assuming each size array: ['size' => 'Medium', 'price' => ..., ...]
                                        // The cart item might store size by 'size' name
                                        // Adjust if necessary to match how size is stored in the cart item
                                        $selected = (isset($item['size']) && $item['size'] === $sz['size']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($sz['size']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($sz['size']) ?> (+<?= number_format((float)$sz['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['extras'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Extras</label>
                                <div>
                                    <?php foreach ($product['extras'] as $extra): ?>
                                        <?php
                                        // Assuming $item['extras'] is array of arrays with 'name' and 'price'
                                        // Check if this extra's name is in cart item extras
                                        $checked = in_cart($extra['name'], $item['extras']) || in_array($extra['name'], array_column($item['extras'], 'name')) ? 'checked' : '';
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_extra<?= $index ?>_<?= htmlspecialchars($extra['name']) ?>" name="extras[]" value="<?= htmlspecialchars($extra['name']) ?>" <?= $checked ?>>
                                            <label class="form-check-label" for="edit_extra<?= $index ?>_<?= htmlspecialchars($extra['name']) ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format((float)$extra['price'], 2) ?>€)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['sauces'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Sauces</label>
                                <div>
                                    <?php foreach ($product['sauces'] as $sauce): ?>
                                        <?php
                                        // Assuming sauces are array of ['name' => ..., 'price' => ...]
                                        $checked = in_array($sauce['name'], array_column($item['sauces'], 'name')) ? 'checked' : '';
                                        ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="edit_sauce<?= $index ?>_<?= htmlspecialchars($sauce['name']) ?>" name="sauces[]" value="<?= htmlspecialchars($sauce['name']) ?>" <?= $checked ?>>
                                            <label class="form-check-label" for="edit_sauce<?= $index ?>_<?= htmlspecialchars($sauce['name']) ?>"><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format((float)$sauce['price'], 2) ?>€)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($drinks)): ?>
                            <div class="mb-3">
                                <label class="form-label">Drinks</label>
                                <select class="form-select" name="drink">
                                    <option value="">Choose a drink</option>
                                    <?php foreach ($drinks as $drink): ?>
                                        <?php
                                        // Check if item has a drink and match by 'id'
                                        $selected = (isset($item['drink']['id']) && $item['drink']['id'] == $drink['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($drink['id']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($drink['name']) ?> (+<?= number_format((float)$drink['price'], 2) ?>€)
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