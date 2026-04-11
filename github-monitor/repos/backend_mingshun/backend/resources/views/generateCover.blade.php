<form id='coverForm' action="{{ route($crudRoutePart . '.generate', $id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if(($edit ?? 0) == 1) @method('PUT') @endif
    @foreach ($columns as $key => $column)
        @include('widget.inputContainer', [
            'key' => $key,
            'name' => $column['name'] ?? '',
            'type' => $column['type'] ?? '',
            'setting' => [
                'containerKey' => $key,
                'required' => $column['required'] ?? 0,
                'multiple' => $column['multiple'] ?? 0,
                'readonly' => $column['readonly'] ?? 0,
                'value' => ($column['value'] ?? (($column['multiple'] ?? 0)?[]:'')),
                'spacing' => 1,
                'mimeType' => $column['mimeType'] ?? '',
                'placeholder' => $column['placeholder'] ?? '',
                'create' => $column['create'] ?? 0,
                'condition' => $column['condition'] ?? [],
                'fields' => $column['field'] ?? [],
                'setting' => $column['setting'] ?? [],
                'modal' => $column['modal'] ?? '',
                'route' => $column['route'] ?? '',
                'label' => $column['label'] ?? '',
                'pre' => $column['pre'] ?? [],
            ],
        ])
    @endforeach
    <input type="hidden" id="temporary-image" name="temporary-image">
    <div class="row">
        <div class="col-3">

        </div>
        <div class="col-9">
            <img id="generate-img" class='table-img clickable-img' src="">
        </div>
    </div>
    @include('widget.video',['script' => 1])
    <div class='row'>
        <div class='col-12 submit-button'>
            <button type="button" id="changeCover" class="btn btn-sm btn-change" style="background-color: orange; color: white; display:none;">
                替换
            </button>
            <button type="button" id="cancelCover" class="btn btn-sm btn-cancel" style="background-color: red; color: white; display:none;">
                取消
            </button>
            <button id="generateCover" class="btn btn-sm btn-generate" style="background-color: blue; color: white; display:none;">
                生成
            </button>
            <button type="button" id="submitCover" class="btn btn-sm btn-submit">
                提交
            </button>
        </div>
    </div>
</form>
<script>
    $(document).ready(function() {
        $('#cover_font').change(function() { 
            if($(this).is(":checked")){
                $('#generateCover').show();
                $('#submitCover').hide();
            }else{
                $('#submitCover').show();
                $('#generateCover').hide();
                $('#changeCover').hide();
                $('#generate-img').attr('src','');
            }
        });

        $('#coverForm').submit(function (e) {
            var imageValue = $('input[name="image"]').val();
            $('#temporary-image').val(imageValue);
            $.ajax({
                type: 'post',
                url: $('#coverForm').attr('action'),
                data: $('#coverForm').serialize(),
                success: function (response) {
                    if (response.includes("http")) {
                        $('#changeCover').show();
                        $('#cancelCover').show();
                        $('#generate-img').attr('src',response);
                    }else{
                        if(response){
                            label = response;
                        }else{
                            label = '生成失败';
                        }
                        const danger = addNotification(
                            NOTIFICATION_TYPES.DANGER,
                            label);
                        setTimeout(() => {
                            removeNotification(danger);
                        }, 5000);
                    }
                }
            });

            e.preventDefault();
        });

        $('#cancelCover').click(function (e) {
            $('#generate-img').attr('src','');
            $('#changeCover').hide();
            $('#cancelCover').hide();
        });

        $('#changeCover').click(function (e) {
            var src =  $('#generate-img').attr('src');
            $('.cover_font-container').hide();
            $('#generate-img').attr('src','');
            $('#changeCover').hide();
            $('#generateCover').hide();
            $('#cancelCover').hide();
            $('#submitCover').show();
            $('.upload-img').attr('src',src);
            $('input[name="image"]').val(src);
        });

        $('#submitCover').click(function (e) {
            $('#baseForm').submit();
        });
    });
</script>
