<!-- Order Success Modal -->
<?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
    <?php
    // Fetch the last order to get payment method
    $stmt = $pdo->prepare("SELECT payment_method FROM orders WHERE id = (SELECT MAX(id) FROM orders)");
    $stmt->execute();
    $last_order = $stmt->fetch(PDO::FETCH_ASSOC);
    $payment_method = $last_order['payment_method'] ?? 'cash';
    ?>
    <div class="modal fade show" tabindex="-1" style="display: block;" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thank You for Your Order!</h5>
                    <a href="index.php" class="btn-close" aria-label="Close"></a>
                </div>
                <div class="modal-body">
                    <p>Your order has been placed successfully.</p>
                    <p>
                        <?php if ($payment_method === 'stripe'): ?>
                            <strong>Payment Method:</strong> Online Payment via Stripe.
                        <?php elseif ($payment_method === 'pickup'): ?>
                            <strong>Payment Method:</strong> Pay on Collection.
                        <?php elseif ($payment_method === 'cash'): ?>
                            <strong>Payment Method:</strong> Cash on Delivery.
                        <?php endif; ?>
                    </p>
                    <p>It will be delivered on <strong><?= htmlspecialchars($_GET['scheduled_date']) ?></strong> at <strong><?= htmlspecialchars($_GET['scheduled_time']) ?></strong>.</p>
                </div>
                <div class="modal-footer">
                    <a href="index.php" class="btn btn-primary">Close</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>