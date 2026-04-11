<div class="modal fade"
    id="clearAgentConfirmationModal"
    tabindex="-1" role="dialog" 
    aria-labelledby="clearAgentConfirmationModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearAgentConfirmationModalLabel">{{ __('agent.confirm_clear') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{ __('agent.confirm_clear_agent', ['agent_name' => '']) }}
                <strong id="modalClearAgentName"></strong>?
                <p class="text-danger mt-2">{{ __('messages.delete_warning') }}</p>
            </div>
            <div class="modal-footer">
                <form>
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                </form>
                <form id="clearAgentForm" method="POST" action="">
                    @csrf
                    <button type="submit" class="btn bg-gradient-primary">{{ __('agent.clear') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@push('js')
<script>
    $(document).ready(function() {
        $(document).on('click', '.clear-agent-btn', function () {
            var clearAgentId = $(this).data('agent-id');
            var ClearAgentName = $(this).data('agent-name');
            var clearAgentRoute = $(this).data('clear-agent-route');
            $('#modalClearAgentName').text(ClearAgentName);
            clearAgentRoute = clearAgentRoute.replace('__ITEM_ID__', clearAgentId);
            $('#clearAgentForm').attr('action', clearAgentRoute);
        });
        $('#clearAgentForm').on('submit', async function(e) {
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
