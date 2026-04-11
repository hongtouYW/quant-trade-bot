<div class="modal import-modal" id="import-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-import-modal">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class='row'>
                    <p class='description'>使用.csv文件导入。文件需要有{{$import}}参数。<a href="{{ route($crudRoutePart . '.importExample') }}">下载例子</a></p>
                </div>
                <form action="{{ route($crudRoutePart . '.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('widget.inputContainer', [
                        'key' => 'file',
                        'name' => 'Csv 文件',
                        'type' => 'file',
                        'setting' => [
                            'containerKey' => 'file',
                            'required' => 1,
                            'mimeType' => 'text/csv',
                        ],
                    ])
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
<script>
    $(document).ready(function() {

        $(".import-btn").click(function() {
            $('#import-modal').show();
            $("body").addClass("modal-open");
        });

        $('#close-import-modal').click(function() {
            closeModal();
        });

        var modal = document.getElementById('import-modal');
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        function closeModal() {
            $('#import-modal').hide();
            $("body").removeClass("modal-open");
        }
    });
</script>
