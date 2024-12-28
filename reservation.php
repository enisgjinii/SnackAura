<!-- Reservierungs-Modal -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="reservationForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationModalLabel">Reservierung vornehmen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reservation_name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reservation_name" name="client_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="reservation_email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="reservation_email" name="client_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="reservation_date" class="form-label">Reservierungsdatum <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reservation_date" name="reservation_date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="reservation_time" class="form-label">Reservierungszeit <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="reservation_time" name="reservation_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="number_of_people" class="form-label">Anzahl der Personen <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="number_of_people" name="number_of_people" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="reservation_message" class="form-label">Nachricht (optional)</label>
                        <textarea class="form-control" id="reservation_message" name="reservation_message" rows="3" placeholder="Schreiben Sie spezielle Anfragen..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Reservieren</button>
                </div>
            </form>
        </div>
    </div>
</div>