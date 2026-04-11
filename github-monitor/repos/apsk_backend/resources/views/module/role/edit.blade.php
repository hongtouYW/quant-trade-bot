@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('role.edit_role'))
@section('header-title', __('role.edit_role'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('role.edit_role') }}: {{ $role->role_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.role.update', $role->role_name) }}" method="POST" class="p-4">
                    @csrf
                    @method('PUT') {{-- Use PUT method for update --}}

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="role_name" class="form-label">{{ __('role.role_name') }}</label>
                        <input type="text" class="form-control @error('role_name') is-invalid @enderror" id="role_name" name="role_name" value="{{ old('role_name', $role->role_name) }}" readonly>
                        @error('role_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="role_desc" class="form-label">{{ __('role.role_desc') }}</label>
                        <textarea class="form-control @error('role_desc') is-invalid @enderror" id="role_desc" name="role_desc" maxlength="1000" style="height: 100px;">{{ old('role_desc', $role->role_desc) }}</textarea>
                        @error('role_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <!-- Access Module -->
                    <div class="mb-3">
                        <div class="card mb-4">
                            <div class="card-header pb-0 d-flex">
                                <h6>{{ __('role.edit_access') }}</h6>
                                {{-- Global select all --}}
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                        class="custom-control-input"
                                        id="select_all_global">
                                    <label class="custom-control-label" for="select_all_global">
                                        {{ __('messages.select_all') }}
                                    </label>
                                </div>
                            </div>
                            <div class="card-body px-0 pt-0 pb-2 container">
                                @forelse ($accesslist as $key => $access)
                                    <div class="row mb-3 align-items-center module-row">
                                        {{-- Per-module select --}}
                                        <div class="col-3 d-flex align-items-center">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" 
                                                    class="custom-control-input module-toggle"
                                                    id="module_toggle_{{ $key }}"
                                                    data-key="{{ $key }}">
                                                <label class="custom-control-label" for="module_id_{{ $key }}"></label>
                                            </div>
                                            {{ __( 'module.'.$access['module_name'] ) }}
                                            <input name="access[{{ $key }}][module_id]" value="{{ $access['module_id'] }}" style="display:none">
                                        </div>

                                        <div class="col-2">
                                            <div class="custom-control custom-switch">
                                                <input class="custom-control-input permission-checkbox"
                                                    type="checkbox"
                                                    id="can_view_{{ $key }}"
                                                    data-key="{{ $key }}"
                                                    name="access[{{ $key }}][can_view]"
                                                    value="1"
                                                    {{ $access['can_view'] == 1 ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="can_view_{{ $key }}">
                                                    {{ __('role.can_view') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="custom-control custom-switch">
                                                <input class="custom-control-input permission-checkbox"
                                                    type="checkbox"
                                                    id="can_edit_{{ $key }}"
                                                    data-key="{{ $key }}"
                                                    name="access[{{ $key }}][can_edit]"
                                                    value="1"
                                                    {{ $access['can_edit'] == 1 ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="can_edit_{{ $key }}">
                                                    {{ __('role.can_edit') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-2">
                                            <div class="custom-control custom-switch">
                                                <input class="custom-control-input permission-checkbox"
                                                    type="checkbox"
                                                        id="can_delete_{{ $key }}"
                                                    data-key="{{ $key }}"
                                                    name="access[{{ $key }}][can_delete]"
                                                    value="1"
                                                    {{ $access['can_delete'] == 1 ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="can_delete_{{ $key }}">
                                                    {{ __('role.can_delete') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p>{{ __('messages.no_access_found') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $role->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('role.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('role.edit_role') }}</button>
                    <a href="{{ route('admin.role.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllGlobal = document.getElementById('select_all_global');
        const moduleToggles = document.querySelectorAll('.module-toggle');
        const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');

        // Global select all
        selectAllGlobal.addEventListener('change', function() {
            const checked = this.checked;
            moduleToggles.forEach(m => m.checked = checked);
            permissionCheckboxes.forEach(p => p.checked = checked);
        });

        // Per-module toggle
        moduleToggles.forEach(module => {
            module.addEventListener('change', function() {
                const key = this.dataset.key;
                const checked = this.checked;
                document.querySelectorAll(`.permission-checkbox[data-key="${key}"]`).forEach(p => {
                    p.checked = checked;
                });
                updateGlobalCheckbox();
            });
        });

        // If any permission is manually changed, update the module toggle
        permissionCheckboxes.forEach(p => {
            p.addEventListener('change', function() {
                const key = this.dataset.key;
                const allChecked = Array.from(document.querySelectorAll(`.permission-checkbox[data-key="${key}"]`)).every(c => c.checked);
                document.querySelector(`.module-toggle[data-key="${key}"]`).checked = allChecked;
                updateGlobalCheckbox();
            });
        });

        function updateGlobalCheckbox() {
            const allChecked = Array.from(permissionCheckboxes).every(c => c.checked);
            selectAllGlobal.checked = allChecked;
        }

        // Initialize module toggles and global checkbox on page load
        moduleToggles.forEach(module => {
            const key = module.dataset.key;
            const allChecked = Array.from(document.querySelectorAll(`.permission-checkbox[data-key="${key}"]`)).every(c => c.checked);
            module.checked = allChecked;
        });
        updateGlobalCheckbox();
    });
</script>
@endpush
