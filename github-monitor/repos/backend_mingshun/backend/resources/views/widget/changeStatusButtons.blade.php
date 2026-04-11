@if($modalBtnClass)
    @if ($isButton)
        <button extra='{{$extra}}' data-label="{{strip_tags($title) . "理由 : "}}" data='{{ $id }}' data-value='{{ $value }}' @if ($isButton) class="{{$modalBtnClass}} btn btn-sm {{ $class }} waves-effect waves-light" @endif>{!! $title !!}</button>
    @else
        <input extra='{{$extra}}' data-label="{{strip_tags($title) . "理由 : "}}" data-value='{{ $value }}' class='{{$modalBtnClass}} change-status-model-grid-button' data='{{ $id }}' value={{strip_tags($title)}}>
    @endif
@else
    <form action="{{ route($crudRoutePart, $id) }}" method="POST" class='changeStatusForm' confirmWord = '{{$confirmWord}}' data='{{ $id }}'
    @if ($isButton) style="display: inline-block;" @endif>
        @method('PUT')
        @csrf
        <input type="hidden" name="status" value={{ $value }}>
        @if ($isButton)
            <button extra='{{$extra}}' type="submit" class="btn btn-sm {{ $class }} waves-effect waves-light">{!! $title !!}</button>
        @else
            <input extra='{{$extra}}' type="submit" class='input-submit' value={{strip_tags($title)}}>
        @endif
    </form>
@endif
