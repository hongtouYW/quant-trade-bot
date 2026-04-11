<div class="modal {{$key}}-modal" id="{{$key}}-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-{{$key}}">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-3" style="">
                        <label id="{{$key}}-search-label" for="{{$key}}-search">搜索 : </label>
                    </div>
                    <div class="col-9" style="">
                        <input id="{{$key}}-search" type="text" name="{{$key}}-search">
                    </div>
                </div>
                @foreach ($value as $value_key=>$name)
                    <div id='{{$key}}-{{$value_key}}-checkbox-container' class='{{$key}}-checkbox-container' style="display:inline-block;">
                        <input type="checkbox" name="{{$key}}[]" value="{{$value_key}}"
                            @foreach ($init as $optionValue)
                                @if(old($key))
                                    @if(gettype(old($key))=='array')
                                        @if (in_array($value_key, old($key))) checked @endif
                                    @else
                                        @if (old($key) == $value_key) checked @endif
                                    @endif
                                @else
                                    @if(gettype($optionValue)=='array')
                                        @if (in_array($value_key, $optionValue)) checked @endif
                                    @else
                                        @if ($optionValue == $value_key) checked @endif
                                    @endif
                                @endif
                            @endforeach>
                        <span id="{{$key}}-{{$value_key}}-span">{{trim($name)}}</span>
                    </div>
                @endforeach
    
                <div class='row submit-button-container'>
                    <div class='col-12 submit-button'>
                        <button type="button" id="{{$key}}-modal-submit" class="btn btn-sm btn-submit">
                            提交
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        function close{{$key}}(){
            $('#{{$key}}-modal').hide();
            $("body").removeClass("modal-open");
            $('#{{$key}}-search').val("");
            $('.{{$key}}-checkbox-container').show();
        }

        $('#{{$key}}-search').on("input", function() {
            var search = $(this).val();
            $('input[name="{{$key}}[]"]').each(function() {
                var name = $(this).attr("name");
                var newName = name.replace("[]", "");
                var spanName = newName + "-" + $(this).val() + "-span";
                var spanValue = $("#" + spanName).text();
                if (spanValue.indexOf(search) !== -1) {
                    $('#' + newName + "-" + $(this).val() + '-checkbox-container').show();
                } else {
                    $('#' + newName + "-" + $(this).val() + '-checkbox-container').hide();
                }
            });
        });

        $('.{{$key}}-checkbox-container').click(function(e) {
            $(this).find('input[type="checkbox"]').prop('checked', !$(this).find('input[type="checkbox"]').prop('checked'));
        });

        $('.{{$key}}-checkbox-container input[type="checkbox"]').click(function(e) {
            e.stopPropagation();
        });

        $('#{{$changeKey}}').on('select2:opening', function (ev) {
            ev.preventDefault();
            $('#{{$key}}-modal').show();
            $("body").addClass("modal-open");
        });

        $('#{{$changeKey}}').on('select2:unselecting', function (e) { 
            id = e.params.args.data.id;
            $('input[name="{{$key}}[]"][value="'+id+'"]').prop('checked', false);
            $('#{{$changeKey}}').on('select2:opening', function (ev) {
                ev.preventDefault();
                $('#{{$key}}-modal').hide();
                $("body").removeClass("modal-open");
                $('#{{$changeKey}}').off('select2:opening');
                $('#{{$changeKey}}').on('select2:opening', function (ev) {
                    ev.preventDefault();
                    $('#{{$key}}-modal').show();
                    $("body").addClass("modal-open");
                });
            });
        });

        $('#close-{{$key}}').click(function() {
            close{{$key}}();
        });

        $('#{{$key}}-modal-submit').click(function(e) {
            var selectedValues = []; 
            $('input[name="{{$key}}[]"]:checked').each(function() {
                selectedValues.push($(this).val());
            });
            $('#{{$changeKey}}').val(selectedValues).trigger('change');
            $('#{{$key}}-modal').hide();
            $("body").removeClass("modal-open");
            $('#{{$key}}-search').val("");
            $('.{{$key}}-checkbox-container').show();
        });
    });
</script>  
