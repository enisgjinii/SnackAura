<div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="index.php" id="checkoutForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="checkoutModalLabel">Checkout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Customer Details Fields -->
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>
                            <input class="form-check-input" type="radio" id="event_checkbox"> Event
                        </label>
                    </div>
                    <div id="event_details" style="display: none;">
                        <div class="mb-3">
                            <label for="scheduled_date" class="form-label">Preferred Delivery Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="scheduled_time" class="form-label">Preferred Delivery Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="scheduled_time" name="scheduled_time">
                        </div>
                    </div>
                    <script>
                        document.getElementById('event_checkbox').addEventListener('change', function() {
                            const eventDetails = document.getElementById('event_details');
                            const scheduledDate = document.getElementById('scheduled_date');
                            const scheduledTime = document.getElementById('scheduled_time');
                            if (this.checked) {
                                eventDetails.style.display = 'block';
                                // Set required attribute for inputs when checkbox is checked
                                scheduledDate.setAttribute('required', 'required');
                                scheduledTime.setAttribute('required', 'required');
                            } else {
                                eventDetails.style.display = 'none';
                                // Remove required attribute for inputs when checkbox is unchecked
                                scheduledDate.removeAttribute('required');
                                scheduledTime.removeAttribute('required');
                            }
                        });
                    </script>
                    <!-- Payment Method Selection -->
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentStripe" value="stripe" required>
                                <label class="form-check-label" for="paymentStripe">
                                    Stripe (Online Payment)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentPickup" value="pickup" required>
                                <label class="form-check-label" for="paymentPickup">
                                    Pick-Up (Pay on Collection)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentCash" value="cash" required>
                                <label class="form-check-label" for="paymentCash">
                                    Cash on Delivery
                                </label>
                            </div>
                        </div>
                    </div>
                    <!-- Stripe Payment Elements (Hidden by Default) -->
                    <div id="stripe-payment-section" class="mb-3" style="display: none;">
                        <label class="form-label">Credit or Debit Card</label>
                        <div id="card-element"><!-- A Stripe Element will be inserted here. --></div>
                        <div id="card-errors" role="alert" class="text-danger mt-2"></div>
                    </div>
                    <!-- Tip Selection -->
                    <div class="mb-3">
                        <label for="tip_selection" class="form-label">Select Tip</label>
                        <select class="form-select" id="tip_selection" name="selected_tip">
                            <?php foreach ($tip_options as $tip): ?>
                                <option value="<?= htmlspecialchars($tip['id']) ?>" <?= ($selected_tip == $tip['id']) ? 'selected' : '' ?>>
                                    <?php
                                    if ($tip['percentage']) {
                                        echo htmlspecialchars($tip['name']) . " (" . htmlspecialchars($tip['percentage']) . "%)";
                                    } elseif ($tip['amount']) {
                                        echo htmlspecialchars($tip['name']) . " (+" . number_format($tip['amount'], 2) . "€)";
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Order Summary -->
                    <h5>Order Summary</h5>
                    <ul class="list-group mb-3">
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div><?= htmlspecialchars($item['name']) ?> x<?= htmlspecialchars($item['quantity']) ?><?= $item['size'] ? " ({$item['size']})" : '' ?></div>
                                <div><?= number_format($item['total_price'], 2) ?>€</div>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($tip_amount > 0): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>Tip</div>
                                <div><?= number_format($tip_amount, 2) ?>€</div>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <h5>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h5>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="checkout" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>