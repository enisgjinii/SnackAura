<!-- agb_modal.php -->
<div class="modal fade" id="agbModal" tabindex="-1" aria-labelledby="agbModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($agb)): ?>
                    <div class="agb-content">
                        <?= nl2br(htmlspecialchars($agb)) ?>
                    </div>
                <?php else: ?>
                    <p>Terms and Conditions content is not available at the moment.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>