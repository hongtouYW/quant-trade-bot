<form id="form{{$id}}" action="{{ route($crudRoutePart . '.changeStatus', $id) }}" class='changeStatusForm' method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <select class="select2-init status-form" name="status" data-form-id="form{{$id}}">
        @foreach ($selection as $optionKey => $optionValue)
            <option value="{{ $optionKey }}" @if($selectionValue == $optionKey) selected @endif> {{ $optionValue }}</option>
        @endforeach
    </select>
</form>
