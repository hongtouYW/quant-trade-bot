@extends('adminlte::page')

@section('title', __('member.member_detail'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <button onclick="window.history.back()"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </button>
            </div>
            {{-- Member Info --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3>
                        {{ $member->member_login }}
                    </h3>
                    <h5 class="text-muted">
                        {{ __('member.member_name') }}: {{ $member->member_name }} |
                        {{ __('member.balance') }}:
                        <span class="font-weight-bold text-primary">
                        {{ number_format($member->balance, 2) }}</span>
                        | {{ __('member.phone') }}: {{ formatPhone($member->phone) }}
                        | {{ __('shop.shop_name') }}: {{ $member->shop->shop_name ?? '-' }}
                        | {{ __('area.area_name') }}: {{ $member->areas->area_name ?? '-' }}
                        | {{ __('member.score') }}: {{$member->score->amount ?? '-' }} ({{ $member->myvip->vip_name ?? '-' }})
                    </h5>
                </div>
            </div>
            {{-- Bank Account --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h4>{{ __('member.bank_account') }}</h4>
                </div>
                <div class="card-body">
                @foreach( $bankaccounts as $key => $bankaccount)
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong>{{ __('bank.bank_name') }}:</strong>
                            {{ optional($bankaccount->Bank)->bank_name }}
                            @if ( $bankaccount->fastpay )
                                <span class="badge bg-success me-1" title="" data-original-title="">
                                    {{ __('bank.bank_account_fastpay_enable') }}
                                </span>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <strong>{{ __('member.bank_account') }}:</strong>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="text-muted">
                                        {{$bankaccount->bank_account}}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <strong>{{ __('member.bank_full_name') }}:</strong>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="text-muted">
                                        {{$bankaccount->bank_full_name}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h4>{{ __('member.devicemeta') }}</h4>
                </div>
                <div class="card-body">
                    @if(!empty($member->devicemeta))
                        @php
                            $devicemeta = json_decode($member->devicemeta, true);
                        @endphp

                        @if(is_array($devicemeta) && count($devicemeta))
                            <div class="row">
                                @foreach([
                                    'ua' => 'UA',
                                    'platform' => 'Platform',
                                    'lang' => __('messages.language'),
                                    'tz' => 'Timezone',
                                    'device_id' => 'Device ID',
                                    'os' => 'OS',
                                    'os_version' => 'OS Version',
                                    'brand' => 'Brand',
                                    'model' => 'Model',
                                    'app_version' => 'App Version'
                                ] as $key => $label)

                                    @if(isset($devicemeta[$key]) && $devicemeta[$key] !== '')
                                        <div class="col-md-6 mb-2">
                                            <strong>{{ $label }}:</strong>
                                            <div class="text-muted">
                                                {{ $devicemeta[$key] }}
                                            </div>
                                        </div>
                                    @endif

                                @endforeach
                            </div>

                        @else
                            <div class="text-center text-muted">
                                No Device Found
                            </div>
                        @endif

                    @else
                        <div class="text-center text-muted">
                            No Device Found
                        </div>
                    @endif

                </div>
            </div>

            {{-- Member Credit History --}}
            <div class="card">
                <div class="card-header">
                    <h4>{{ __('credit.credit_list') }}</h4>
                </div>
                <div class="card-body">
                    @include('module.member.partials._membercredits', [
                        'membercredits' => $membercredits
                    ])
                </div>
            </div>

        </div>
    </div>
@endsection
