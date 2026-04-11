@extends('adminlte::page')

@section('title', __('shop.shop_detail'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <button onclick="window.history.back()"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </button>
            </div>
            {{-- Shop info --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3>{{ $shop->shop_login }} ({{optional($shop->areas)->area_name}})</h3>
                    <h5 class="text-muted">
                        {{ __('shop.shop_name') }}: {{ $shop->shop_name }} |
                        {{ __('shop.principal') }}: {{ $shop->principal }} |
                        {{ __('shop.balance') }}: {{ number_format($shop->balance, 2) }}
                    </h5>
                </div>
            </div>

            {{-- Shop Credits --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6>{{ __('shop.shop_credit') }}</h6>
                </div>
                <div class="card-body">
                    @include('module.shop.partials._shopcredits', ['shopcredits' => $shopcredits])
                </div>
            </div>

            {{-- Members --}}
            <div class="card">
                <div class="card-header">
                    <h6>{{ __('member.member_list') }}</h6>
                </div>
                <div class="card-body">
                    @include('module.shop.partials._members', [
                        'members' => $members
                    ])
                </div>
            </div>

        </div>
    </div>
@endsection
