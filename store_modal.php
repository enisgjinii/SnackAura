<?php if (!isset($_SESSION['selected_store']) && !$is_closed): ?>
    <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="storeSelectionForm">
                    <div class="modal-header">
                        <img src="<?= htmlspecialchars($cart_logo, ENT_QUOTES, 'UTF-8') ?>" alt="Cart Logo" style="width: 100%; height: 80px; object-fit: cover" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="store_id" class="form-label">Choose a store</label>
                            <select name="store_id" id="store_id" class="form-select" required>
                                <option value="" selected>Select Store</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?= htmlspecialchars($store['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Delivery Address</label>
                            <input type="text" class="form-control" id="delivery_address" name="delivery_address" placeholder="Enter your address" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Location on Map</label>
                            <div id="map" style="height: 300px;"></div>
                        </div>
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const storeModal = new bootstrap.Modal(document.getElementById('storeModal'), {
                backdrop: 'static',
                keyboard: false
            });
            storeModal.show();
            let map = L.map('map').setView([51.505, -0.09], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);
            let marker;
            map.on('click', e => {
                if (marker) map.removeLayer(marker);
                marker = L.marker(e.latlng).addTo(map);
                $('#latitude').val(e.latlng.lat);
                $('#longitude').val(e.latlng.lng);
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.display_name) $('#delivery_address').val(data.display_name);
                    })
                    .catch(err => console.error('Error:', err));
            });
            $('#delivery_address').on('change', function() {
                let addr = $(this).val().trim();
                if (addr.length > 5) {
                    fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(addr)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.length > 0) {
                                let first = data[0];
                                if (marker) map.removeLayer(marker);
                                marker = L.marker([first.lat, first.lon]).addTo(map).bindPopup(first.display_name).openPopup();
                                map.setView([first.lat, first.lon], 13);
                                $('#latitude').val(first.lat);
                                $('#longitude').val(first.lon);
                            } else alert('Address not found. Please try again.');
                        })
                        .catch(err => console.error('Error:', err));
                }
            });
            $('#storeSelectionForm').on('submit', function(e) {
                if (!$('#delivery_address').val() || !$('#latitude').val() || !$('#longitude').val()) {
                    e.preventDefault();
                    alert('Please enter a valid address and select your location on the map.');
                }
            });
            map.invalidateSize();
        });
    </script>
<?php elseif ($is_closed): ?>
    <div class="container mt-4">
        <div class="alert alert-warning text-center" role="alert">
            <?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?><?= !empty($notification['end_datetime']) ? " We will reopen on " . date('F j, Y, g:i a', strtotime($notification['end_datetime'])) . "." : "" ?>
        </div>
    </div>
<?php else: ?>
    <div class="container mt-4">
        <p>You have selected store: <?= htmlspecialchars($_SESSION['store_name'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php endif; ?>