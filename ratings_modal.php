<!-- Bewertungs-Modal -->
<div class="modal fade" id="ratingsModal" tabindex="-1" aria-labelledby="ratingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="submit_rating.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingsModalLabel">Ihre Bewertung abgeben</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <!-- Vollständiger Name -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Vollständiger Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>

                    <!-- E-Mail -->
                    <div class="mb-3">
                        <label for="email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <!-- Telefon (optional) -->
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefon (optional)</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>

                    <!-- Anonym abgeben -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="anonymous" name="anonymous">
                        <label class="form-check-label" for="anonymous">
                            Anonym abgeben
                        </label>
                    </div>

                    <!-- Bewertung -->
                    <div class="mb-3">
                        <label class="form-label">Bewertung <span class="text-danger">*</span></label>
                        <div>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" required>
                                    <label class="form-check-label" for="rating<?= $i ?>">
                                        <?= $i ?> Stern<?= $i > 1 ? 'e' : '' ?>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Kommentare -->
                    <div class="mb-3">
                        <label for="comments" class="form-label">Kommentare</label>
                        <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Ihr Feedback..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <input type="hidden" name="submit_rating" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Bewertung abgeben</button>
                </div>
            </form>
        </div>
    </div>
</div>