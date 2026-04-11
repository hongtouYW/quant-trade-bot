@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('shop.edit_shop'))
@section('header-title', __('shop.edit_shop'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('shop.edit_shop') }}: {{ $shop->shop_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.shop.update', $shop->shop_id) }}" method="POST" class="p-4">
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
                        <label for="shop_name" class="form-label">{{ __('shop.shop_name') }}</label>
                        <input type="text" class="form-control @error('shop_name') is-invalid @enderror" id="shop_name" name="shop_name" value="{{ old('shop_name', $shop->shop_name) }}" required readonly>
                        @error('shop_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="shop_login" class="form-label">{{ __('shop.shop_login') }}</label>
                        <input type="text" class="form-control @error('shop_login') is-invalid @enderror" id="shop_login" name="shop_login" value="{{ old('shop_login', $shop->shop_login) }}" required>
                        @error('shop_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="shop_pass" class="form-label">{{ __('shop.shop_password') }}</label>
                        <input type="password" class="form-control @error('shop_pass') is-invalid @enderror" id="shop_pass" name="shop_pass" placeholder="{{ __('shop.leave_blank_for_no_change') }}">
                        @error('shop_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="principal" class="form-label">{{ __('shop.principal') }}</label>
                        <input type="number" class="form-control @error('principal') is-invalid @enderror" id="principal" name="principal" value="{{ old('principal', $shop->principal) }}" min="0" step="1" required>
                        @error('principal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="lowestbalance" class="form-label">{{ __('shop.lowestbalance') }}</label>
                        <input type="number" class="form-control @error('lowestbalance') is-invalid @enderror" id="lowestbalance" name="lowestbalance" value="{{ old('lowestbalance', $shop->lowestbalance) }}" min="0" step="1">
                        @error('lowestbalance')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="balance" class="form-label">{{ __('shop.balance') }}</label>
                        <input type="number" class="form-control @error('balance') is-invalid @enderror" id="balance" name="balance" value="{{ old('balance', $shop->balance) }}" min="0.00" step="0.01" readonly>
                        @error('balance')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="manager_id" class="form-label">{{ __('shop.selectmainmanager') }}</label>
                            <select class="form-control select2 @error('manager_id') is-invalid @enderror" 
                            id="manager_id" 
                            name="manager_id"
                            required>
                            <option value="">{{ __('shop.selectmainmanager') }}</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->manager_id }}" {{ old('manager_id', $shop->manager_id) == $manager->manager_id ? 'selected' : '' }}>
                                    {{ $manager->manager_id }} - {{ $manager->manager_name }} - {{ $manager->area_code }}
                                </option>
                            @endforeach
                            </select>
                        @error('manager_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="permission_user" class="form-label">{{ __('shop.selectmanagerpermission') }}</label>
                        @php
                            $managerpermissions = old('permission_user', $managerpermissions ?? []);
                        @endphp
                        <select class="form-control select2 @error('permission_user') is-invalid @enderror" 
                            id="permission_user" 
                            name="permission_user[]" 
                            multiple
                            required>
                            @foreach ($managers as $permission_user)
                                <option value="{{ $permission_user->manager_id }}" 
                                    {{ in_array($permission_user->manager_id, $managerpermissions) ? 'selected' : '' }}>
                                    {{ $permission_user->manager_id }} - {{ $permission_user->manager_name }} - {{ $permission_user->area_code }}
                                </option>
                            @endforeach
                        </select>
                        @error('permission_user')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $shop->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('shop.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center">
                            <label class="form-label mb-0" style="margin-right:1rem">{{ __('shop.permission_setting') }}</label>
                            <div class="form-check" style="margin-top:0.2rem">
                                <input class="form-check-input" type="checkbox" id="select_all_permissions">
                                <label class="form-check-label fw-bold" for="select_all_permissions">
                                    {{ __('messages.select_all') }}
                                </label>
                            </div>
                        </div>
                        <div class="permission-checkboxes mt-2">
                            @php
                                $permissions = [
                                    'can_deposit' => __('shop.can_deposit'),
                                    'can_withdraw' => __('shop.can_withdraw'),
                                    'can_create' => __('shop.can_create_account'),
                                    'can_block' => __('shop.can_block_account'),
                                    'can_income' => __('shop.show_shop_income'),
                                    'read_clear' => __('shop.show_clear_record'),
                                    'can_view_credential' => __('shop.show_member_credential'),
                                    'no_withdrawal_fee' => __('shop.no_withdrawal_fee'),
                                ];
                            @endphp

                            @foreach ($permissions as $key => $label)
                                <div class="form-check">
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        value="1"
                                        {{ old($key, $shop->$key) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="{{ $key }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('shop.edit_shop') }}</button>
                    <a href="{{ route('admin.shop.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('js')
    <script>
        const selectAll = document.getElementById('select_all_permissions');
        const checkboxes = document.querySelectorAll('.permission-checkboxes input[type="checkbox"]');

        // Toggle all checkboxes when select all is clicked
        selectAll.addEventListener('change', function () {
            const checked = this.checked;
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
        });

        // Optional: auto toggle "Select All" if all boxes are manually checked
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                selectAll.checked = allChecked;
            });
        });

        // Initialize Select All state on page load
        window.addEventListener('load', function () {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            selectAll.checked = allChecked;
        });

        //Manager Permission
        $(document).ready(function () {
            const managerSelect = $('#manager_id');
            const permissionSelect = $('#permission_user');

            const allOptions = permissionSelect.find('option').clone();

            function rebuildPermissionUsers() {
                let selectedManager = managerSelect.val();

                // Save current selected values
                let currentValues = permissionSelect.val() || [];

                // ✅ ALWAYS remove manager from selected values
                if (selectedManager) {
                    currentValues = currentValues.filter(v => v != selectedManager);
                }

                // Clear options
                permissionSelect.empty();

                // Rebuild options except selected manager
                allOptions.each(function () {
                    let value = $(this).val();

                    if (value != selectedManager) {
                        permissionSelect.append($(this).clone());
                    }
                });

                // Remove manager from selected values
                currentValues = currentValues.filter(v => v != selectedManager);

                // Restore selected values
                permissionSelect.val(currentValues).trigger('change');
            }

            // ✅ Run AFTER everything is ready (important for old() + select2)
            setTimeout(function () {
                rebuildPermissionUsers();
            }, 0);

            // On change
            managerSelect.on('change', function () {
                rebuildPermissionUsers();
            });
        });
    </script>
@endpush
