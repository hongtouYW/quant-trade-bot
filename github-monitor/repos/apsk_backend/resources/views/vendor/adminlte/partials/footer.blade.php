@push('js')
<form id="adminlte-logout-form"
      action="{{ route('logout') }}"
      method="POST"
      style="display:none;">
    @csrf
</form>
@endpush