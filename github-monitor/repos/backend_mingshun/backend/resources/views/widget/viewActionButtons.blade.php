<a href="{{ route($crudRoutePart . '.show', $id) }}">
    @if($isButton)<button type="button" class="btn btn-sm btn-secondary waves-effect waves-light" style='margin-bottom:10px;'>@endif
        详情
    @if($isButton)</button>@endif
</a>
