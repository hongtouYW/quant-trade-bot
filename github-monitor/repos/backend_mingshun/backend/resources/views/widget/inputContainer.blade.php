<div class='row create-container {{ $containerKey ?? $key }}-container' style='@if(!($setting['spacing'] ?? 0)) margin-top:0; @endif'>
    @if($type == 'hidden')
        <input type="hidden" name="{{$key}}" id="{{$key}}" value="{{$setting['value']??''}}">
    @else
        <div class='col-3' style='@if(!($setting['spacing'] ?? 0)) padding:0; @endif'>
            @if($name)
                <label id="{{ $key }}-label" for="{{ $key }}">{{ $name }}@if ($setting['required'] ?? 0)
                        <span class='important-label'>*</span>
                    @endif : </label>
            @endif
        </div>
        <div class='col-9' style='@if(!($setting['spacing'] ?? 0) ) padding:0; @endif'>
            @include('widget.input')
        </div>
    @endif
</div>
