
<div class="modal fade" id="imagePreviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body text-center">
                <img src="" id="previewImage" class="img-fluid" alt="Preview">
            </div>
        </div>
    </div>
</div>
@push('js')
    <script>
        $(document).ready(function() {
            console.log('JS loaded');
            // Click on image triggers modal
            $('.img-fluid[data-toggle="modal"]').on('click', function() {
                let imageUrl = $(this).data('image');
                console.log('Clicked image', imageUrl);
                // Set modal image src
                $('#previewImage').attr('src', imageUrl);
                // Show modal manually
                $('#imagePreviewModal').modal('show');
            });
            // Clear modal when closed
            $('#imagePreviewModal').on('hidden.bs.modal', function() {
                $('#previewImage').attr('src', '');
            });
        });
    </script>
@endpush