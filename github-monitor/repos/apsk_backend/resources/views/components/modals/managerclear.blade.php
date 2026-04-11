<div class="modal fade"
    id="clearManagerConfirmationModal"
    tabindex="-1" role="dialog" 
    aria-labelledby="clearManagerConfirmationModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearManagerConfirmationModalLabel">{{ __('manager.confirm_clear') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{ __('manager.confirm_clear_manager', ['manager_name' => '']) }}
                <strong id="modalClearManagerName"></strong>?
                <p class="text-danger mt-2">{{ __('messages.delete_warning') }}</p>
            </div>
            <div class="modal-footer">
                <form>
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                </form>
                <form id="clearManagerForm" method="POST" action="">
                    @csrf
                    <button type="submit" class="btn bg-gradient-primary">{{ __('manager.clear') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@push('js')
<script>
    $(document).ready(function() {
        $(document).on('click', '.clear-manager-btn', function () {
            var clearManagerId = $(this).data('manager-id');
            var ClearManagerName = $(this).data('manager-name');
            var clearManagerRoute = $(this).data('clear-manager-route');
            $('#modalClearManagerName').text(ClearManagerName);
            clearManagerRoute = clearManagerRoute.replace('__ITEM_ID__', clearManagerId);
            $('#clearManagerForm').attr('action', clearManagerRoute);
        });
        $('#clearManagerForm').on('submit', async function(e) {
            e.preventDefault();
            try{
                const response = await fetch($(this).attr('action'), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
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
