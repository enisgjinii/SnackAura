<?php
// store_close.php
?>
<!-- Store Closed Modal -->
<div class="modal fade" id="storeCloseModal" tabindex="-1" aria-labelledby="storeCloseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Geschäft geschlossen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Unser Restaurant ist derzeit geschlossen. Bitte versuchen Sie es später erneut.</p>
                <?php if(!empty($notification['message'])): ?>
                    <p><?=htmlspecialchars($notification['message'])?></p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    <?php if($is_closed): ?>
        var storeCloseModal = new bootstrap.Modal(document.getElementById('storeCloseModal'), {
            backdrop: 'static',
            keyboard: false
        });
        storeCloseModal.show();
    <?php endif; ?>
});
</script>
