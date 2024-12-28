<?php
// Stelle sicher, dass $products zugänglich ist und nach product_id indiziert ist.
// Wenn $products derzeit ein einfaches numerisches Array ist, erstelle ein assoziatives Array, das nach Produkt-ID indiziert ist.
$products_by_id = [];
foreach ($products as $p) {
    $products_by_id[$p['id']] = $p;
}
$products = $products_by_id;

/**
 * Überprüft, ob ein bestimmtes Element im Warenkorb vorhanden ist.
 *
 * @param mixed $needle_id Die ID des zu suchenden Elements.
 * @param array $items Das Array der aktuellen Warenkorbartikel.
 * @return bool Gibt true zurück, wenn das Element gefunden wird, sonst false.
 */
function in_cart($needle_id, $items)
{
    // Überprüft, ob needle_id in den Artikeln anhand ihres 'id'-Feldes vorhanden ist
    foreach ($items as $it) {
        if (isset($it['id']) && $it['id'] == $needle_id) return true;
    }
    return false;
}
?>

<?php foreach ($_SESSION['cart'] as $index => $item): ?>
    <?php
    // Sicheres Abrufen des Produkts anhand der product_id
    if (!isset($products[$item['product_id']])) {
        // Produkt nicht in $products gefunden, überspringe diesen Warenkorbartikel
        continue;
    }
    $product = $products[$item['product_id']];
    ?>
    <div class="modal fade" id="editCartModal<?= $index ?>" tabindex="-1" aria-labelledby="editCartModalLabel<?= $index ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCartModalLabel<?= $index ?>">Bearbeite <?= htmlspecialchars($item['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Menge -->
                        <div class="mb-3">
                            <label for="quantity<?= $index ?>" class="form-label">Menge</label>
                            <input type="number" class="form-control" id="quantity<?= $index ?>" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max="99" required>
                        </div>

                        <!-- Größe (falls zutreffend) -->
                        <?php if (!empty($product['sizes'])): ?>
                            <div class="mb-3">
                                <label for="size<?= $index ?>" class="form-label">Größe</label>
                                <select class="form-select" id="size<?= $index ?>" name="size">
                                    <option value="">Wähle eine Größe</option>
                                    <?php foreach ($product['sizes'] as $sz): ?>
                                        <?php
                                        // Angenommen, jedes Größenarray: ['size' => 'Medium', 'price' => ..., ...]
                                        // Der Warenkorbartikel könnte die Größe nach dem 'size'-Namen speichern
                                        // Passe dies ggf. an, um mit der Speicherung der Größe im Warenkorbartikel übereinzustimmen
                                        $selected = (isset($item['size']) && $item['size'] === $sz['size']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($sz['size']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($sz['size']) ?> (+<?= number_format((float)$sz['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Extras (Menge) -->
                        <?php if (!empty($product['extras'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Extras</label>
                                <div>
                                    <?php foreach ($product['extras'] as $extra): ?>
                                        <?php
                                        // Angenommen, $item['extras'] ist ein Array von Arrays mit 'name' und 'price'
                                        // Überprüfe, ob der Name dieses Extras im Warenkorbartikel-Extras vorhanden ist
                                        $existingQty = 0;
                                        if (!empty($item['extras'])) {
                                            foreach ($item['extras'] as $exInCart) {
                                                if (!empty($exInCart['name']) && $exInCart['name'] === $extra['name']) {
                                                    $existingQty = (int)$exInCart['quantity'];
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <input type="number"
                                                class="form-control me-2"
                                                style="width:80px"
                                                name="extras[<?= htmlspecialchars($extra['name']) ?>]"
                                                value="<?= $existingQty ?>"
                                                min="0"
                                                step="1">
                                            <label class="form-check-label">
                                                <?= htmlspecialchars($extra['name']) ?>
                                                ( +<?= number_format((float)$extra['price'], 2) ?>€ pro Stück )
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Saucen (Menge) -->
                        <?php if (!empty($product['sauces'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Saucen</label>
                                <div>
                                    <?php foreach ($product['sauces'] as $sauce): ?>
                                        <?php
                                        $existingQty = 0;
                                        if (!empty($item['sauces'])) {
                                            foreach ($item['sauces'] as $sInCart) {
                                                if (!empty($sInCart['name']) && $sInCart['name'] === $sauce['name']) {
                                                    $existingQty = (int)$sInCart['quantity'];
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <input type="number"
                                                class="form-control me-2"
                                                style="width:80px"
                                                name="sauces[<?= htmlspecialchars($sauce['name']) ?>]"
                                                value="<?= $existingQty ?>"
                                                min="0"
                                                step="1">
                                            <label class="form-check-label">
                                                <?= htmlspecialchars($sauce['name']) ?>
                                                ( +<?= number_format((float)$sauce['price'], 2) ?>€ pro Stück )
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Getränke -->
                        <?php if (!empty($drinks)): ?>
                            <div class="mb-3">
                                <label class="form-label">Getränke</label>
                                <select class="form-select" name="drink">
                                    <option value="">Wähle ein Getränk</option>
                                    <?php foreach ($drinks as $drink): ?>
                                        <?php
                                        // Überprüfe, ob der Artikel ein Getränk hat und vergleiche anhand der 'id'
                                        $selected = (isset($item['drink']['id']) && $item['drink']['id'] == $drink['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($drink['id']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($drink['name']) ?> (+<?= number_format((float)$drink['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Besondere Anweisungen -->
                        <div class="mb-3">
                            <label for="special_instructions<?= $index ?>" class="form-label">Besondere Anweisungen</label>
                            <textarea class="form-control" id="special_instructions<?= $index ?>" name="special_instructions" rows="2" placeholder="Gibt es besondere Wünsche?"><?= htmlspecialchars($item['special_instructions']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="item_index" value="<?= $index ?>">
                        <input type="hidden" name="update_cart" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Warenkorb aktualisieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>