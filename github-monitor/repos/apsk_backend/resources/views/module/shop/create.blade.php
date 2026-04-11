@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('shop.add_new_shop'))
@section('header-title', __('shop.add_new_shop'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('shop.add_new_shop') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.shop.store') }}" method="POST" class="p-4">
                    @csrf
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
                        <label for="shop_login" class="form-label">{{ __('shop.shop_login') }}</label>
                        <input type="text" class="form-control @error('shop_login') is-invalid @enderror" id="shop_login" name="shop_login" value="{{ old('shop_login') }}" required>
                        @error('shop_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="shop_pass" class="form-label">{{ __('shop.shop_password') }}</label>
                        <input type="password" class="form-control @error('shop_pass') is-invalid @enderror" id="shop_pass" name="shop_pass" required>
                        @error('shop_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">{{ __('shop.shop_name') }}</label>
                        <input type="text" class="form-control @error('shop_name') is-invalid @enderror" id="shop_name" name="shop_name" value="{{ old('shop_name') }}" required>
                        @error('shop_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="principal" class="form-label">{{ __('shop.principal') }}</label>
                        <input type="number" class="form-control @error('principal') is-invalid @enderror" id="principal" name="principal" value="{{ old('principal') }}" min="0" step="1" required>
                        @error('principal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="lowestbalance" class="form-label">{{ __('shop.lowestbalance') }}</label>
                        <input type="number" class="form-control @error('lowestbalance') is-invalid @enderror" id="lowestbalance" name="lowestbalance" value="{{ old('lowestbalance') }}" min="0" step="1">
                        @error('lowestbalance')
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
                                <option value="{{ $manager->manager_id }}" {{ old('manager_id') == $manager->manager_id ? 'selected' : '' }}>
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
                            $managerpermissions = old('permission_user', []);
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
                            @foreach ($permissions as $key => $label)
                                <div class="form-check">
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        value="1"
                                        {{ old($key, 0) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="{{ $key }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
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

        selectAll.addEventListener('change', function () {
            const checked = this.checked;
            checkboxes.forEach(cb => cb.checked = checked);
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAll.checked = Array.from(checkboxes).every(c => c.checked);
            });
        });

        window.addEventListener('load', function () {
            selectAll.checked = Array.from(checkboxes).every(c => c.checked);
        });

        //Manager Permission
        $(document).ready(function () {
            const managerSelect = $('#manager_id');
            const permissionSelect = $('#permission_user');

            function loadPermissionUsers(managerId, selectedValues = []) {
                if (!managerId) {
                    permissionSelect.empty().trigger('change');
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.shop.permissionlist', ':id') }}".replace(':id', managerId),
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (res) {
                        if (res.status) {
                            permissionSelect.empty();

                            let options = [];

                            res.data.forEach(function (manager) {
                                // ❌ Exclude selected manager itself
                                if (manager.manager_id != managerId) {
                                    options.push(
                                        new Option(
                                            `${manager.manager_name} (${manager.manager_id})`,
                                            manager.manager_id,
                                            false,
                                            selectedValues.includes(manager.manager_id.toString())
                                        )
                                    );
                                }
                            });

                            permissionSelect.append(options).trigger('change');
                        }
                    },
                    error: function () {
                        console.error('Failed to load permission users');
                    }
                });
            }

            // 🔹 Initial load (for old values)
            let initialManager = managerSelect.val();
            let oldValues = permissionSelect.val() || [];

            if (initialManager) {
                loadPermissionUsers(initialManager, oldValues);
            }

            // 🔹 On change
            managerSelect.on('change', function () {
                let managerId = $(this).val();
                loadPermissionUsers(managerId);
            });
        });
    </script>
@endpush
