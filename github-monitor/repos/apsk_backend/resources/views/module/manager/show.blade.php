@extends('adminlte::page')

@section('title', __('manager.manager_detail'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <button onclick="window.history.back()"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </button>
            </div>
            {{-- Manager info --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3>{{ $manager->manager_login }} ({{optional($manager->areas)->area_name}})</h3>
                    <h5 class="text-muted">
                        {{ __('manager.manager_name') }}: {{ $manager->manager_name }} |
                        {{ __('manager.principal') }}: {{ $manager->principal }} |
                        {{ __('manager.balance') }}: {{ number_format($manager->balance, 2) }} |
                    </h5>
                </div>
            </div>

            {{-- Manager credit history --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{ __('manager.manager_credit') }}</h4>
                </div>
                <div class="card-body">
                    @include('module.manager.partials._managercredits', ['managercredits' => $managercredits])
                </div>
            </div>

            {{-- Shops under manager --}}
            <div class="card">
                <div class="card-header">
                    <h4>{{ __('shop.shop_list') }}</h4>
                </div>
                <div class="card-body">
                    @include('module.manager.partials._shops', [
                        'shops' => $manager->shops
                    ])
                </div>
            </div>

        </div>
    </div>
@endsection
