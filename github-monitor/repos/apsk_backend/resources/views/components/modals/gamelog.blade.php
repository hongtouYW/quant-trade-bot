<div class="modal fade"
    id="gamelogModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="gamelogModalLabel"
    data-backdrop="static"
    data-keyboard="false">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gamelogModalLabel">{{ __('gamelog.sync') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre class="form-control bg-black" id="gamelog_desc" style="height: 400px;"></pre>
            </div>
            <div class="modal-footer">
                <form>
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                </form>
                <button type="button" id="syncgamelog" class="btn bg-primary">{{ __('gamelog.sync') }}</button>
            </div>
        </div>
    </div>
</div>
@push('js')
<script>
    $('#gamelogModal').on('shown.bs.modal', function () {
        $('#gamelog_desc').scrollTop(0);
    });
    function gamelogdesc(message) {
        let el = $('#gamelog_desc');
        let time = new Date().toLocaleTimeString();
        el.append(`[${time}] ${message}\n`);
        el.scrollTop(el[0].scrollHeight);
    }
    function cleargamelogdesc() {
        $('#gamelog_desc').html('');
    }
</script>
@endpush