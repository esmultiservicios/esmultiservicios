    </main>
</div>

<div
    class="image-modal"
    data-image-modal
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-label="Media preview"
>
    <div class="modal-preview-dialog">
        <button
            type="button"
            class="modal-close"
            data-modal-close
            aria-label="Close preview"
            title="Close preview"
        >
            <span class="modal-close-icon" aria-hidden="true">×</span>
            <span class="modal-close-text">Close</span>
        </button>

        <div class="modal-stage">
            <img src="" alt="Large preview" data-modal-image>
            <p data-modal-caption></p>
        </div>
    </div>
</div>

<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/select2/select2.local.js"></script>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="../assets/vendor/show-notify/showNotify.js"></script>
<script>
(function(){
  if(!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2!=='function') return;
  window.jQuery('select:not([data-native-select])').each(function(){
    const $el=window.jQuery(this); if(!$el.data('select2-local')) $el.select2({width:'100%'});
  });
})();
</script>
<script src="../assets/action-icons.js"></script>
<script src="admin.js"></script>
</body>
</html>
