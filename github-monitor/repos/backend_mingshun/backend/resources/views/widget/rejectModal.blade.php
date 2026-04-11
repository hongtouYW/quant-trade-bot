<div class="modal status-modal" id="status-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-status-modal">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="chg-status-modal-form" action="{{ route($crudRoutePart) }}" class='changeStatusFormOnce' confirmWord = '确定不通过？' method="POST">
                    @csrf
                    @include('widget.inputContainer', [
                        'key' => 'reason',
                        'name' => '不通过理由',
                        'type' => 'textarea',
                        'setting' => [
                            'containerKey' => 'reason',
                            'required' => 1,
                        ],
                    ])
                    <input id="chg-status-modal-status" type="hidden" name="status" value={{ $value }}>
                    <input id="chg-status-modal-id" type="hidden" name="id" value="">
                    <div class='row'>
                        <div class='col-12 submit-button'>
                            <button class="btn btn-sm btn-submit">
                                提交
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@if($script)
<script>
    $(document).ready(function() {
        var rejectStatusBtnClass = "{{ $modalBtnClass }}".split(",");
        var temp = '';
        rejectStatusBtnClass.forEach(element => {
            $("." + element).click(function() {
                $('#reason-label').text($(this).attr('data-label'));
                $('#chg-status-modal-status').val($(this).attr('data-value'));
                $('#chg-status-modal-id').val($(this).attr('data'));
                $('#chg-status-modal-form').attr('data',$(this).attr('data'));
                $('#status-modal').show();
                $("body").addClass("modal-open");
            });
        });
        
        $('#close-status-modal').click(function() {
            closeStatusModal();
        });

        var modal = document.getElementById('status-modal');
        window.onclick = function(event) {
            if (event.target == modal) {
                closeStatusModal();
            }
        }

        function closeStatusModal() {
            $('#status-modal').hide();
            $("body").removeClass("modal-open");
        }
    });
</script>
@endif
