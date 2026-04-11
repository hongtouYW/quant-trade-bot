<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmationModalLabel">{{ __('messages.confirm_delete') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{ __('messages.confirm_delete_item', ['item_name' => '']) }} <strong id="modalItemName"></strong>?
                <p class="text-danger mt-2">{{ __('messages.delete_warning') }}</p>
            </div>
            <div class="modal-footer">
                <form>
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                </form>
                <form id="deleteForm" method="POST" action="">
                    <button type="submit" class="btn bg-gradient-danger">{{ __('messages.delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@push('js')
<script>
    $(document).ready(function() {
        $(document).on('click', '.delete-btn', function () {
            var itemId = $(this).data('item-id');
            var itemName = $(this).data('item-name');
            var deleteRoute = $(this).data('delete-route');

            $('#modalItemName').text(itemName);

            deleteRoute = deleteRoute.replace('__ITEM_ID__', itemId);
            // deleteRoute = deleteRoute.replace("http", "https");
            $('#deleteForm').attr('action', deleteRoute);
        });
        $('#deleteForm').on('submit', async function(e) {
            e.preventDefault();
            // var action = $(this).attr('action');
            // console.log(action);
            // if (action.startsWith('http://')) {
            //     action = action.replace(/^http:/, 'https:');
            //     $(this).attr('action', action);
            // }
            // this.submit();
            // console.log(deleteRoute);
            try{
                const response = await fetch( $(this).attr('action'), {
                    method: 'POST', // POST instead of DELETE
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        _method: 'DELETE' // Laravel sees this as DELETE
                    })
                })
                .then(response => {
                    if (!response.ok) throw response;
                    location.reload(); // reload iframe/page after success
                })
                .catch( err => location.reload() );
                // .catch(err => console.error('Delete failed:', err));
            } catch (error) {
                // console.error(error);
                location.reload();
            }
        });
    });
</script>
@endpush
