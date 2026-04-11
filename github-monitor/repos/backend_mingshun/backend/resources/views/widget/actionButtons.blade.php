@if ($edit)
    <a href="{{ route($crudRoutePart . '.edit', $row->id) }}">
        @if($isButton)<button type="button" class="btn btn-sm btn-primary waves-effect waves-light" style='margin-bottom:10px;'>@endif
            编辑
        @if($isButton)</button>@endif
    </a>
@endif
@if ($delete)
    <form action="{{ route($crudRoutePart . '.destroy', $row->id) }}" method="POST" onsubmit="return confirm('确定？');"
        @if($isButton) style="display: inline-block;" @endif>
        @method('DELETE')
        @csrf
        <input type="submit" @if($isButton) class="btn btn-sm btn-danger waves-effect waves-light" @else class="input-submit"@endif value="删除"  style='margin-bottom:10px;'>
    </form>
@endif
