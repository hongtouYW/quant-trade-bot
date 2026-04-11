<div class="modal image-modal" id="image-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-image-modal">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                <img src="" id="modal-image-inside">
            </div>
        </div>
    </div>
</div>
@if($script)
<script>
    $(document).ready(function() {
        $('.clickable-img').click(function() {
            $('#image-modal').show();
            $("#modal-image-inside").attr('src',$(this).attr('src'));
            $("body").addClass("modal-open");
        });

        $('#close-image-modal').click(function() {
            closeImageModal();
        });

        var modal = document.getElementById('image-modal');
        window.onclick = function(event) {
            if (event.target == modal) {
                closeImageModal();
            }
        }

        function closeImageModal() {
            $('#image-modal').hide();
            $("body").removeClass("modal-open");
        }
    });
</script>
@endif
