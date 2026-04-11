@php
    $inputName = $key;
    if($setting['inputname'] ?? ""){
        $inputName = $setting['inputname'];
    }
    if($type == 'file' && ((($setting['setting']['type'] ?? '') == 'image') || (($setting['setting']['type'] ?? '') == 'video'))){
        $inputName .= '-temp';
    }
    if($setting['multiple'] ?? 0){
        $inputName .= "[]";
    }
@endphp
@if (gettype($type) == 'array')
    <select class="select2-init" id="{{ $key }}"
        name="{{$inputName}}"
        @if ($setting['multiple'] ?? 0) multiple @endif
        @if ($setting['readonly'] ?? 0) readonly @endif
        @if ($setting['required'] ?? 0) class="required" @endif>
            @if (!($setting['required'] ?? 0) && !($setting['multiple'] ?? 0))
                <option value="" extra=0> 选择{{ $name }}</option>
            @endif
        @if ($setting['create'] ?? 0)
            <option value="-1"
                @if(old($key))
                    @if (old($key) == -1) selected @endif
                @endif
            extra=0> 创建{{ $name }}</option>
        @endif
        @foreach ($type as $optionKey => $optionValue)
            <option value="{{ $optionKey }}" 
                @if(gettype($optionValue) == 'array')
                    extra="{{ $optionValue['extra'] ?? $optionKey }}"
                @else
                    extra="{{ $optionKey }}"
                @endif
                @if(old($key))
                    @if(gettype(old($key))=='array')
                        @if (in_array($optionKey, old($key))) selected @endif
                    @else
                        @if (old($key) == $optionKey) selected @endif
                    @endif
                @else
                    @if(gettype(($setting['value'] ?? ""))=='array')
                        @if (in_array($optionKey, ($setting['value'] ?? ""))) selected @endif
                    @else
                        @if (($setting['value'] ?? "") == $optionKey) selected @endif
                    @endif
                @endif>
                @if(gettype($optionValue) == 'array')
                    {{ $optionValue['value'] }}
                @else
                    {{ $optionValue }}
                @endif
            </option>
        @endforeach
    </select>
    @if($setting['modal'] ?? "")
        @include('widget.select2Modal', [
            'value' => $type,
            'init' => $setting['value'] ?? [],
            'key' => $key . "_modal_temp",
            'changeKey' => $key
        ])
    @endif
@else
    @if($type == 'color')
        <div class="row">
            <div class="col-6" style='padding-right:0px !important;'>
                <input type="color" id='color-{{ $key }}' value='{{$setting['value'] ?? ""}}' 
                    @if ($setting['readonly'] ?? 0)  disabled @endif>
            </div>
            <div class="col-6" style='padding-left:0px !important;'>
                <input type="text" id='{{ $key }}' name='{{ $key }}' value='{{$setting['value'] ?? ""}}' 
                    @if ($setting['required'] ?? 0) class="required" required @endif>        
            </div>
        </div>                                       
    @elseif ($type == 'switch')
        <label class="switch">
            <input id="{{ $key }}" type="checkbox" name="{{$inputName}}" @if($setting['readonly'] ?? 0) disabled @endif
            @if(old($inputName)) checked @else @if($setting['value'] ?? "") checked @endif @endif>
            <span class="slider round"></span>
        </label>
        <div id="{{ $key }}-container" switchId='{{ $key }}'>
            @foreach($setting['setting'] ?? [] as $switchInputKey=>$switchInput)
                @include('widget.inputContainer', [
                    'key' => $switchInputKey,
                    'name' => $switchInput['name'] ?? '',
                    'type' => $switchInput['type'] ?? '',
                    'setting' => [
                        'containerKey' => $switchInputKey,
                        'required' => $switchInput['required'] ?? 0,
                        'multiple' => $switchInput['multiple'] ?? 0,
                        'readonly' => $switchInput['readonly'] ?? 0,
                        'value' => $switchInput['value'] ?? '',
                        'spacing' => 1,
                        'mimeType' => $switchInput['mimeType'] ?? '',
                        'placeholder' => $switchInput['placeholder'] ?? '',
                        'create' => $switchInput['create'] ?? 0,
                        'condition' => $switchInput['condition'] ?? [],
                        'fields' => $switchInput['field'] ?? [],
                        'setting' => $switchInput['setting'] ?? [],
                        'route' => $column['route'] ?? '',
                    ],
                ])
            @endforeach
        </div>
        
    @elseif ($type == 'html')
        {!! $setting['value'] ?? '' !!}
    @elseif ($type == 'multiple')
        <button id="multiple_{{$key}}_add" type="button" class="multiple_add_btn btn btn-success waves-effect waves-light">添加</button>
        <table class="multiple-table" id="multiple_{{$key}}" style="width:100%">
            <tr>
                <th style="width: 5%;">#</th>
                <th>资料</th>
                <th style="width: 5%; text-align:right;">删除</th>
            </tr>
        </table>
    @elseif ($type == 'textarea')
        <textarea rows="10" @if ($setting['required'] ?? 0) class="required" @endif id="{{ $key }}" name="{{ $key }}" @if ($setting['required'] ?? 0) required @endif @if ($setting['readonly'] ?? 0) readonly @endif>{!! old($key) ? old($key) : ($setting['value'] ?? '') !!}</textarea>
    @elseif ($type == 'json')
        <table class="view-table" style="width:100%">
            @foreach(($setting['value'] ?? []) as $key=>$value)
                <tr>
                    <th width='100px'>{{$key}} :</th>
                    <td>{{$value}}</td>
                </tr> 
            @endforeach
        </table>
    @elseif ($type == 'editor')
        <textarea id="{{ $key }}" name="{{ $key }}" class='textarea-editor'>{!! $setting['value'] ?? '' !!}</textarea>
    @elseif ($type == 'select')
        <select id="{{ $key }}" class='select2-ajax'
            name="{{$inputName}}"
            @if ($setting['multiple'] ?? 0) multiple @endif
            @if ($setting['readonly'] ?? 0) readonly @endif
            @if ($setting['required'] ?? 0) class="required" @endif>
            @if (($setting['label'] ?? 0) && ($setting['value'] ?? 0))
                @if(gettype(($setting['label'] ?? ""))=='array')
                    @foreach($setting['label'] as $select2ajaxkey=>$select2ajaxvalue)
                        <option value="{{ $setting['value'][$select2ajaxkey] }}" selected>
                            {{ $select2ajaxvalue }}
                        </option>
                    @endforeach
                @else
                    <option value="{{ $setting['value'] ?? 0 }}" selected>
                        {{ $setting['label'] ?? 0 }}
                    </option>
                @endif
            @endif
        </select>
    @else
        <input id="@if($type == 'file' && ((($setting['setting']['type'] ?? '') == 'image') || (($setting['setting']['type'] ?? '') == 'video'))){{$key.'-temp'}}@else{{ $key }}@endif" type="{{ $type }}"
            @if ($type != 'file' && $setting['required'] ?? 0) class="required" @endif
            name="{{$inputName}}"
            @if(old($inputName))
                @if(gettype(old($inputName))=='array')
                    value='{{json_encode(old($inputName))}}'
                @else
                    value='{{old($inputName)}}'
                @endif
            @else
                @if(gettype(($setting['value'] ?? ""))=='array')
                    value='{{json_encode($setting['value'])}}'
                @else
                    value='{{$setting['value'] ?? ""}}'
                @endif
            @endif
            @if ($setting['step'] ?? 0) step="{{$setting['step']}}" @endif
            @if (($setting['required'] ?? 0) && $type != 'file') required @endif 
            @if ($setting['multiple'] ?? 0) multiple @endif
            @if ($setting['readonly'] ?? 0) readonly @endif
            @if (($type == 'file' || $type == 'video') && ($setting['readonly'] ?? 0)) style='display:none;' @endif
            @if ($setting['placeholder'] ?? "") placeholder={{$setting['placeholder'] ?? ""}} @endif
            @if ($type == 'file') accept='@if(($setting['setting']['type'] ?? '') == 'image'){{'image/*'}}@elseif(($setting['setting']['type'] ?? '') == 'video'){{'video/*'}}@else{{$setting['setting']['accept'] ?? ''}}@endif' @endif>
        @if ($type == 'file')
            @if(($setting['setting']['type'] ?? '') == 'image')
                <div  id="upload-img-container-{{ $key }}" class='upload-img-container'>
                    @if($setting['value'] ?? '')
                        @if ($setting['multiple'] ?? 0)
                            @foreach ($setting['value'] as $img_key => $img)
                                <div id='img-container-{{$img_key}}' class='img-container-wrapper'>
                                    <img class="upload-img table-img clickable-img" src="{{ $img['src'] ?? "" }}">
                                    <input type="hidden" name="{{$key}}[]" value='{{$img['src'] ?? ""}}'>
                                    <input type="hidden" name="id_{{$key}}[]" value={{$img['id'] ?? ""}}>
                                    <span id="img-container-close-{{$img_key}}" class="img-container-close" data="{{$img_key}}"></span>
                                </div>
                            @endforeach
                        @else
                            @if($setting['value']['src'] ?? '')
                                <img class="upload-img table-img clickable-img" src="{{ $setting['value']['src'] ?? ""}}">
                                <input type="hidden" name="{{$key}}" value='{{$setting['value']['src'] ?? ""}}'>
                                <input type="hidden" name="id_{{$key}}" value='{{$setting['value']['id'] ?? ""}}'>
                            @endif
                        @endif
                    @endif
                </div>
            @elseif(($setting['setting']['type'] ?? '') == 'video')
                <div  id="upload-video-container-{{ $key }}" class='upload-video-container'>
                    @if($setting['multiple'] ?? 0)
                        <table id="upload-video-table-{{ $key }}" class="upload-video-table" id="multiple_{{$key}}" style="width:100%; display:none;">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th>视频</th>
                                <th style="width: 5%; text-align:right;">删除</th>
                            </tr>
                    @endif
                    @if($setting['value'] ?? '')
                        @if ($setting['multiple'] ?? 0)
                            @foreach ($setting['value'] as $video_key => $img)
                                <tr id='upload-video-container-{{$video_key}}' >
                                    <td>{{$video_key + 1}}</td>
                                    <td>
                                        <a class="open-hls-video" data='{{ ($img['src'] ?? '') }}'>
                                            观看视频
                                        </a>
                                        <input type="hidden" name="{{$key}}[]" value='{{$img['src'] ?? ""}}'>
                                        <input type="hidden" name="id_{{$key}}[]" value={{$img['id'] ?? ""}}>
                                    </td>
                                    <td style='text-align:right;'>
                                        <i data='{{$video_key}}' id='upload-video-trash-table-{{$video_key}}' class='bx bx-trash upload-video-trash-table'></i>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            @if($setting['value']['src'] ?? '')
                                <a id="open-hls-video-0" class="open-hls-video" data='{{ ($setting['value']['src'] ?? '') }}'>
                                    观看视频
                                </a>
                                <input type="hidden" name="{{$key}}" value='{{$setting['value']['src'] ?? ""}}'>
                                <input type="hidden" name="id_{{$key}}" value={{$setting['value']['id'] ?? ""}}>
                            @endif
                        @endif
                    @endif
                    @if($setting['multiple'] ?? 0)
                        </table>
                    @endif
                </div>
            @endif
        @elseif($type == 'video' && ($setting['value'] ?? ''))
            <a class="open-hls-video" data='{{ ($setting['value'] ?? '') }}'>
                观看视频
            </a>
        @endif
    @endif
@endif
@if ($setting['create'] ?? 0)
    <input class="create_input" type="{{old($key)?"text":"hidden"}}" name="create_{{ $key }}" id="create_{{ $key }}" placeholder="新{{$name}}" value={{old("create_".$key)}}>
@endif
@if($type == 'color')
    <script>
        $(document).ready(function() {
            $('#color-{{ $key }}').on('input', function() {
                @if ($setting['multiple'] ?? 0)
                    var temp =  $('#{{ $key }}').val();
                    if(temp){
                        $('#{{ $key }}').val($('#{{ $key }}').val() + ',' + temp); 
                    }else{
                        $('#{{ $key }}').val(this.value); 
                    }
                      
                @else
                    $('#{{ $key }}').val(this.value);
                @endif
                
            });

            $('#{{ $key }}').on('keydown', function (event) {
                event.preventDefault();
            });

            $('#{{ $key }}').on('input', function () {
                $(this).val('#ff0000'); 
                $('#color-{{ $key }}').val('#00ff00');
            });
    });
    </script>
@elseif($type == 'switch')
    <script>
        function {{$key}}Switch(){
            var checked = $('#{{$key}}').is(":checked");
            if(checked){
                $('#{{$key}}-container').show();
                $('#{{$key}}-container').find('input', 'select2').each(function(index, element) {
                    if($(element).hasClass("required")){
                        $(element).prop("required", true);
                    }
                });
            }else{
                $('#{{$key}}-container').hide();
                $('#{{$key}}-container').find('input', 'select2').each(function(index, element) {
                    $(element).prop("required", false);
                });
            }
        }
        $(document).ready(function() {
            {{$key}}Switch();
            $('#{{$key}}').change(function() { 
                {{$key}}Switch();
            });
        });
    </script>
@elseif ($type == 'editor')
    <script>
        new FroalaEditor('#{{$key}}',{
            @if($setting['setting']['upload_image_url'] ?? '')
                imageUploadURL: '{{$setting['setting']['upload_image_url']}}',
                imageUploadParams: {
                    _token: '{{csrf_token()}}',
                    @foreach(($setting['setting']['additional'] ?? []) as $key=> $value)
                        {{$key}}: '{{$value}}',
                    @endforeach
                },
                imageUploadMethod: 'POST',
            @endif
            @if($setting['setting']['upload_file_url'] ?? '')
                fileUploadURL:'{{$setting['setting']['upload_file_url']}}',
                fileUploadParams: {
                    _token: '{{csrf_token()}}',
                    @foreach(($setting['setting']['additional'] ?? []) as $key=> $value)
                        {{$key}}: '{{$value}}',
                    @endforeach
                },
                fileUploadMethod: 'POST',
            @endif
            @if($setting['setting']['upload_video_url'] ?? '')
                videoUploadURL: '{{$setting['setting']['upload_video_url']}}',
                videoUploadParams: {
                    _token: '{{csrf_token()}}',
                    @foreach(($setting['setting']['additional'] ?? []) as $key=> $value)
                        {{$key}}: '{{$value}}',
                    @endforeach
                },
                videoUploadMethod: 'POST',
            @endif
            events: {
                @if($setting['setting']['delete_image_url'] ?? '')
                    'image.removed': function (img) {
                        var data = {
                            _token: '{{csrf_token()}}',
                            src: img.attr('src'),
                            @foreach(($setting['setting']['additional_delete'] ?? []) as $key=> $value)
                                {{$key}}: '{{$value}}',
                            @endforeach
                        };
                        $.ajax({
                            type: "POST",
                            url: "{{$setting['setting']['delete_image_url']}}",
                            data: data,
                            success: function(response) {
                                console.log (response);
                            },
                            error: function(xhr, status, error) {
                                console.error("Error: " + error);
                            }
                        });
                    },
                @endif
                @if($setting['setting']['delete_video_url'] ?? '')
                    'video.removed': function (video) {
                        var data = {
                            _token: '{{csrf_token()}}',
                            src: video.attr('src'),
                            @foreach(($setting['setting']['additional_delete'] ?? []) as $key=> $value)
                                {{$key}}: '{{$value}}',
                            @endforeach
                        };
                        $.ajax({
                            type: "POST",
                            url: "{{$setting['setting']['delete_video_url']}}",
                            data: data,
                            success: function(response) {
                                console.log (response);
                            },
                            error: function(xhr, status, error) {
                                console.error("Error: " + error);
                            }
                        });
                    },
                @endif
                @if($setting['setting']['delete_file_url'] ?? '')
                    'file.unlink': function (file) {
                        var data = {
                            _token: '{{csrf_token()}}',
                            src: $(file).attr('href'),
                            @foreach(($setting['setting']['additional_delete'] ?? []) as $key=> $value)
                                {{$key}}: '{{$value}}',
                            @endforeach
                        };
                        $.ajax({
                            type: "POST",
                            url: "{{$setting['setting']['delete_file_url']}}",
                            data: data,
                            success: function(response) {
                                console.log (response);
                            },
                            error: function(xhr, status, error) {
                                console.error("Error: " + error);
                            }
                        });
                    },
                @endif
            }
        });
    </script>
@elseif ($type == 'multiple')
    <script>
        function buildMultiple{{$key}}(){
           return `@foreach(($setting['fields'] ?? []) as $field_key => $field)
                            <div class='col-6'>
                                <div class='row multiple-container'>
                                    <div class='col-6'>
                                        {{$field['name']}}
                                        @if ($field['required'] ?? 0)
                                            <span class='important-label'>*</span>
                                        @endif
                                    </div>
                                    <div class='col-6'>
                                        @include('widget.input', [
                                            'key' => $field_key . "_" . $key . "_multiple_key_input",
                                            'name' => $field['name'],
                                            'type' => $field['type'] ?? '',
                                            'setting' => [
                                                'containerKey' => $key,
                                                'inputname' => $field_key . "_" . $key .  "[]",
                                                'required' => $field['required'] ?? 0,
                                                'value' => $field['value'] ?? '',
                                            ],
                                        ])
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <input type="hidden" name="id_{{$key}}[]]" value="">`;
        }
        function constructMultiple{{$key}}(total, temp){
            var searchString = /multiple_key_input/g;
            var newString = temp.replace(searchString, total);
            return `<tr id='multiple-container-`+total+`' >
                <td>`+total+`</td>
                <td>
                    <div class='row multiple-info-container'>
                       `+newString+`
                    </div>
                </td>
                <td style='text-align:right;'>
                    <i data='`+total+`' id='multiple-trash-table-`+total+`' class='bx bx-trash multiple-trash-table'></i>
                </td>
            </tr>`;
        }
        var multiple{{$key}}Total = 0;
        function addRow(temp){
            multiple{{$key}}Total = multiple{{$key}}Total + 1;
            var row = constructMultiple{{$key}}(multiple{{$key}}Total, temp);
            var temp = multiple{{$key}}Total;
            $('#multiple_{{$key}}').append(row).ready(function () {
                $('#multiple-trash-table-' + temp).click(function() {
                    $('#multiple-container-' + $(this).attr('data')).remove();
                });
                $('.select2-init').select2();
            });;
        }
        $(document).ready(function() {
            @if($edit ?? 0)
                @foreach(($setting['value'] ?? []) as $value)
                    var temp = `@foreach(($setting['fields'] ?? []) as $field_key => $field)
                            <div class='col-6'>
                                <div class='row multiple-container'>
                                    <div class='col-6'>
                                        {{$field['name']}}
                                        @if ($field['required'] ?? 0)
                                            <span class='important-label'>*</span>
                                        @endif
                                    </div>
                                    <div class='col-6'>
                                        @include('widget.input', [
                                            'key' => $field_key . "_" . $key . "_multiple_key_input",
                                            'name' => $field['name'],
                                            'type' => $field['type'] ?? '',
                                            'setting' => [
                                                'containerKey' => $key,
                                                'inputname' => $field_key . "_" . $key .  "[]",
                                                'value' => $value[$field_key] ?? '',
                                                'required' => $field['required'] ?? 0,
                                            ],
                                        ])
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <input type="hidden" name="id_{{$key}}[]]" value="{{$value['id']}}">`;
                        addRow(temp);
                @endforeach
               
            @else
                var temp = buildMultiple{{$key}}();
                addRow(temp);
            @endif
            
            $('#multiple_{{$key}}_add').click(function() {
                var temp = buildMultiple{{$key}}();
                addRow(temp);
            });
        });
    </script>
@elseif ($type == 'select')
    <script>
        $(document).ready(function() {
            $('#{{$key}}').select2({
                ajax: {
                    url: '{{($setting['route'] ?? "")}}',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            init: '@if(gettype(($setting['value'] ?? ""))=='array'){{json_encode(($setting['value'] ?? ""))}}@else {{($setting['value'] ?? "")}} @endif' || '',
                            q: params.term || '',
                            page: params.page || 1,
                            pre: '{{json_encode($setting['pre'] ?? [])}}'
                        }
                    },
                    cache: true
                }
            });
       });
    </script>
@elseif($type == 'file')
    <script>
        @if((($setting['setting']['type'] ?? '') == 'image'))
            @if($setting['multiple'] ?? 0)
                function successUploadImage{{$key}}(image_key,src,temp,doneTemp){
                    temp.push(image_key);
                    $('#upload-img-container-images').append(`<div id="img-container-`+image_key+`"  class="img-container-wrapper">
                            <img class="upload-img table-img clickable-img" src="`+src+`">
                            <input type="hidden" name="{{$key}}[]" value='`+src+`'>
                            <span id="img-container-close-`+image_key+`" class="img-container-close" data="`+image_key+`"></span>
                            <input type="hidden" name="id_{{$key}}[]" value=0>
                        </div>`).ready(function () {
                            var difference= temp
                                .filter(x => !doneTemp.includes(x))
                                .concat(doneTemp.filter(x => !temp.includes(x)));
                            doneTemp = JSON.parse(JSON.stringify(temp));
                            difference.forEach(element => {
                                $('#img-container-close-' + element).click(function(e) {
                                    e.preventDefault();
                                    $('#img-container-'+ element).remove();
                                });
                            });
                            $('.clickable-img').click(function() {
                                $('#image-modal').show();
                                $("#modal-image-inside").attr('src',$(this).attr('src'));
                                $("body").addClass("modal-open");
                            });
                    });
                }
            @else
                function successUploadSingleImage{{$key}}(src){
                    $('#upload-img-container-{{ $key }}').empty();
                    $('#upload-img-container-{{ $key }}').append(
                        '<img class="upload-img table-img clickable-img" src="' + src + '">' +
                        '<input type="hidden" name="{{$key}}" value="'+src+'"><input type="hidden" name="id_{{$key}}" value=0>'
                    ).ready(function () {
                        $('.clickable-img').click(function() {
                            $('#image-modal').show();
                            $("#modal-image-inside").attr('src',$(this).attr('src'));
                            $("body").addClass("modal-open");
                        });
                    });;
                }
            @endif
        @elseif((($setting['setting']['type'] ?? '') == 'video'))
            @if($setting['multiple'] ?? 0)
                function successUploadVideo{{$key}}(image_key,src,temp,doneTemp){
                    $('#upload-video-table-videos').show();
                    temp.push(image_key);
                    $('#upload-video-table-{{ $key }}').append(`
                        <tr id='upload-video-container-`+image_key+`' >
                            <td>`+image_key+`</td>
                            <td>
                                <a id="open-hls-video-`+image_key+`" class="open-hls-video" data='`+src+`'>
                                    观看视频
                                </a>
                                <input type="hidden" name="{{$key}}[]" value='`+src+`'>
                                <input type="hidden" name="id_{{$key}}[]" value=0>
                            </td>
                            <td style='text-align:right;'>
                                <i data='`+image_key+`' id='upload-video-trash-table-`+image_key+`' class='bx bx-trash upload-video-trash-table'></i>
                            </td>
                        </tr>
                    `).ready(function () {
                        var difference= temp
                            .filter(x => !doneTemp.includes(x))
                            .concat(doneTemp.filter(x => !temp.includes(x)));
                        doneTemp = JSON.parse(JSON.stringify(temp));
                        difference.forEach(element => {
                            $('#upload-video-trash-table-' + element).click(function(e) {
                                e.preventDefault();
                                $('#upload-video-container-' + $(this).attr('data')).remove();
                            });

                            $("#open-hls-video-" + element).click(function() {
                                $('#video-modal').show();
                                $("body").addClass("modal-open");
                                $('#hls-video-source').attr('src', $(this).attr('data'));
                                $('#hls').attr('src', $(this).attr('data'));
                                player = videojs("hls");
                                player.play();
                            });
                        });
                    });
                }
            @else
                function successUploadSingleVideo{{$key}}(src){
                    $('#upload-video-container-{{ $key }}').empty();
                    $('#upload-video-container-{{ $key }}').append(
                        `<a id="open-hls-video-0" class="open-hls-video" data='`+src+`'>观看视频</a>
                        <input type="hidden" name="{{$key}}" value='`+src+`'>
                        <input type="hidden" name="id_{{$key}}" value=0>`
                    ).ready(function () {
                        $("#open-hls-video-0").click(function() {
                            $('#video-modal').show();
                            $("body").addClass("modal-open");
                            $('#hls-video-source').attr('src', $(this).attr('data'));
                            $('#hls').attr('src', $(this).attr('data'));
                            player = videojs("hls");
                            player.play();
                        });
                    });;
                }
            @endif
        @endif
        $(document).ready(function() {
            @if ((($setting['setting']['type'] ?? '') == 'image') && $setting['multiple'] ?? 0)
                @foreach ($setting['value'] as $upload_key => $img)
                    $('#img-container-close-{{$upload_key}}').click(function(e) {
                        e.preventDefault();
                        $('#img-container-{{$upload_key}}').remove();
                    });
                @endforeach
            @elseif((($setting['setting']['type'] ?? '') == 'video') && $setting['multiple'] ?? 0)
                @if(!empty($setting['value'] ?? []))
                    $('#upload-video-table-videos').show();
                @endif
                @foreach ($setting['value'] as $upload_key => $img)
                    $('#upload-video-trash-table-{{$upload_key}}').click(function(e) {
                        e.preventDefault();
                        $('#upload-video-container-' + $(this).attr('data')).remove();
                    });

                    $("#open-hls-video-{{$upload_key}}").click(function() {
                        $('#video-modal').show();
                        $("body").addClass("modal-open");
                        $('#hls-video-source').attr('src', $(this).attr('data'));
                        $('#hls').attr('src', $(this).attr('data'));
                        player = videojs("hls");
                        player.play();
                    });
                @endforeach
            @elseif(($setting['setting']['type'] ?? '') == 'video')
                @if($setting['value'] ?? '')
                    $('#upload-video-table-videos').show();
                @endif
                $("#open-hls-video-0").click(function() {
                    $('#video-modal').show();
                    $("body").addClass("modal-open");
                    $('#hls-video-source').attr('src', $(this).attr('data'));
                    $('#hls').attr('src', $(this).attr('data'));
                    player = videojs("hls");
                    player.play();
                });
            @endif
            @if((($setting['setting']['type'] ?? '') == 'image') || (($setting['setting']['type'] ?? '') == 'video'))
                var upload_key= {{$upload_key??0}};
                $('#{{$key.'-temp'}}').on('change', function() {
                    var files = $(this).get(0).files;

                    @if($setting['setting']['tempUploadUrl'] ?? '')
                        for (var i = 0; i < files.length; i++) {
                            var formData = new FormData();
                            var temp = [];
                            var doneTemp = [];
                            formData.append('_token', '{{csrf_token()}}');
                            formData.append('file', files[i]);
                            $.ajax({
                                url: '{{$setting['setting']['tempUploadUrl']}}', 
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    upload_key = upload_key + 1;
                                    @if((($setting['setting']['type'] ?? '') == 'image'))
                                        @if($setting['multiple'] ?? 0)
                                            successUploadImage{{$key}}(upload_key,response.src,temp,doneTemp);
                                        @else
                                            successUploadSingleImage{{$key}}(response.src);
                                        @endif
                                    @elseif((($setting['setting']['type'] ?? '') == 'video'))
                                        @if($setting['multiple'] ?? 0)
                                            successUploadVideo{{$key}}(upload_key,response.src,temp,doneTemp);
                                        @else
                                            successUploadSingleVideo{{$key}}(response.src);
                                        @endif
                                    @endif
                                },
                                error: function(xhr, status, error) {
                                    $('#{{$key.'-temp'}}').val('');
                                    setTimeout(() => {
                                        const danger = addNotification(NOTIFICATION_TYPES.DANGER, '上传'+'{{$name}}'+'失败('+error+')');
                                        setTimeout(() => {
                                            removeNotification(danger);
                                        }, 5000);
                                    }, 300);
                                }
                            });
                        }
                    @else
                        for (var i = 0; i < files.length; i++) {
                            var file = files[i];
                            var reader = new FileReader();
                            var temp = [];
                            var doneTemp = [];
                            reader.onload = function() {
                                upload_key = upload_key + 1;
                                @if((($setting['setting']['type'] ?? '') == 'image'))
                                    @if($setting['multiple'] ?? 0)
                                        successUploadImage{{$key}}(upload_key,this.result,temp,doneTemp);
                                    @else
                                        successUploadSingleImage{{$key}}(this.result);
                                    @endif
                                @elseif((($setting['setting']['type'] ?? '') == 'video'))
                                    @if($setting['multiple'] ?? 0)
                                        successUploadVideo{{$key}}(upload_key,this.result,temp,doneTemp);
                                    @else
                                        successUploadSingleVideo{{$key}}(this.result);
                                    @endif
                                @endif
                            };
                            reader.readAsDataURL(file);
                        }
                    @endIf
                    $('#{{$key.'-temp'}}').val('');
                });
            @endIf
        });
    </script>
@endif
@if ($setting['create'] ?? 0)
    <script>
        $(document).ready(function() {
            $('#{{$key}}').on('change', function() {
                if($(this).val() == -1){
                    $('#create_{{$key}}').attr('type', 'text');
                }else{
                    $('#create_{{$key}}').attr('type', 'hidden');
                }
            });
        });
    </script>
@endif
@if(!empty($setting['condition'] ?? [])) 
    @foreach($setting['condition'] ?? [] as $key2=>$value2)
        <script>
            function set{{$key2.$key}}Condition(val){
                var flag = false;
                var conditionValue = '{{$value2}}';
                conditionValueArray = conditionValue.split(',');
                $.each(conditionValueArray, function(index, value) {
                    if (Array.isArray(val)) {
                        var index = $.inArray(value, val);
                        if (index !== -1) {
                            flag = true;
                        }
                    } else if (typeof val === "string") {
                        if(val == value){
                            flag = true;
                        }
                    } 
                });
                if (flag){
                    $('.{{$key}}-container').show();
                    $('.{{$key}}-container').find('input', 'select2').each(function(index, element) {
                        var switchid = $(element).parent().parent().parent().attr('switchid');
                        if(switchid !== undefined && switchid !== ''){
                            if($('#' + switchid).is(":checked")){
                                if($(element).hasClass("required")){
                                    $(element).prop("required", true);
                                }
                            };
                        }else{
                            if($(element).hasClass("required")){
                                $(element).prop("required", true);
                            }
                        }
                    });
                }else{
                    $('.{{$key}}-container').hide();
                    $('.{{$key}}-container').find('input', 'select2').each(function(index, element) {
                        $(element).prop("required", false);
                    });
                }
            }
            $(document).ready(function() {
                set{{$key2.$key}}Condition($('#{{$key2}}').val());
                $('#{{$key2}}').on('change', function() {
                    set{{$key2.$key}}Condition($(this).val());
                });
            });
        </script>
    @endforeach
@endif