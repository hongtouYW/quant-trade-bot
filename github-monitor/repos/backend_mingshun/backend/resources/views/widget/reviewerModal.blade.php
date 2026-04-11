<div class="modal reviewer-modal" id="reviewer-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-reviewer-modal">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                @include('widget.inputContainer', [
                    'key' => 'label',
                    'name' => '标签',
                    'type' => 'html',
                    'setting' => [
                        'containerKey' => 'label',
                        'required' => 0,
                        'spacing' => 1,
                        'value' => '<div class="event">
                            <span class="badge bg-success">完成每日任务</span>
                            <span class="badge bg-danger">完成额外任务</span>
                            <span class="badge bg-secondary">未完成任务</span>
                            <span class="badge bg-light">未领取任务</span>
                        </div>',
                    ],
                ])
                @include('widget.inputContainer', [
                    'key' => 'date',
                    'name' => '日期',
                    'type' => 'html',
                    'setting' => [
                        'containerKey' => 'date',
                        'required' => 0,
                        'spacing' => 1
                    ],
                ])
                @include('widget.inputContainer', [
                    'key' => 'reviewer',
                    'name' => '审核者',
                    'type' => 'html',
                    'setting' => [
                        'containerKey' => 'reviewer',
                        'required' => 0,
                        'spacing' => 1
                    ],
                ])
                 @include('widget.inputContainer', [
                    'key' => 'uploader',
                    'name' => '上传者',
                    'type' => 'html',
                    'setting' => [
                        'containerKey' => 'reviewer',
                        'required' => 0,
                        'spacing' => 1
                    ],
                ])
                @include('widget.inputContainer', [
                    'key' => 'coverer',
                    'name' => '图片手',
                    'type' => 'html',
                    'setting' => [
                        'containerKey' => 'coverer',
                        'required' => 0,
                        'spacing' => 1
                    ],
                ])
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('#close-reviewer-modal').click(function() {
            closeStatusModal();
        });

        var modal = document.getElementById('reviewer-modal');
        window.onclick = function(event) {
            if (event.target == modal) {
                closeStatusModal();
            }
        }

        function closeStatusModal() {
            $('#reviewer-modal').hide();
            $("body").removeClass("modal-open");
        }
    });
</script>
