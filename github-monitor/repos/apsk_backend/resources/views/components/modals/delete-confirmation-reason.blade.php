<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="deleteForm" method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">{{ __('messages.confirm_delete') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            {{ __('messages.confirm_delete_item', ['item_name' => '']) }} 
                            <strong id="modalItemName"></strong>?
                            <p class="text-danger mt-2">{{ __('messages.delete_warning') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label for="reason" class="form-label">{{ __('messages.deletereason') }}</label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" 
                                id="reason" name="reason" 
                                maxlength="10000" style="height: 100px;"
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn bg-gradient-danger">{{ __('messages.delete') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content')
            }
        });
        $(document).on('click', '.delete-btn', function () {
            var itemId = $(this).data('item-id');
            var itemName = $(this).data('item-name');
            var deleteRoute = $(this).data('delete-route');

            $('#modalItemName').text(itemName);

            deleteRoute = deleteRoute.replace('__ITEM_ID__', itemId);
            $('#deleteForm').attr('action', deleteRoute);
        });
        $('#deleteForm').on('submit', async function(e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            try{
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: form.serialize(), // includes _token + reason
                    success: function (response) {
                        console.log(response);
                        $('#deleteConfirmationModal').modal('hide');
                        location.reload();
                    },
                    error: function (xhr) {
                        console.log(xhr);
                        console.error(xhr.responseJSON?.message || 'Delete failed');
                        document.getElementById('modalItemName').innerHTML = `{{ __('messages.unexpected_error') }}`;
                    }
                });
            } catch (error) {
                console.error(error);
                // location.reload();
                document.getElementById('modalItemName').innerHTML = `{{ __('messages.unexpected_error') }}`;
            }
        });
    });
</script>
@endpush
