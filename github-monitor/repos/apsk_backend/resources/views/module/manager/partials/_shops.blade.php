@php
    $totalAmountType = 0;
    $totalBalance = 0;
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
        <tr>
            <th>{{ __('shop.shop_login') }}</th>
            <th>{{ __('shop.shop_name') }}</th>
            <th>{{ __('shop.principal') }}</th>
            <th class="text-primary text-md font-weight-bold mb-0">{{ __('shop.balance') }}</th>
            <th>{{ __('shop.status') }}</th>
            <th>{{ __('shop.last_login') }}</th>
            <th>{{ __('messages.created_on') }}</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($shops as $shop)
            @php
                $totalAmountType += $shop->principal;
                $totalBalance += $shop->balance;
            @endphp
            <tr>
                <td>{{ $shop->shop_login }}</td>
                <td>{{ $shop->shop_name }}</td>
                <td>{{ number_format($shop->principal, 2) }}</td>
                <td>
                    <a href="{{ route('admin.shop.show', $shop->shop_id) }}"
                       class="text-primary text-md font-weight-bold mb-0">
                        {{ number_format($shop->balance, 2) }}
                    </a>
                </td>
                <td>
                    @if ($shop->status)
                        <span class="badge badge-success">{{ __('messages.active') }}</span>
                    @else
                        <span class="badge badge-secondary">{{ __('messages.inactive') }}</span>
                    @endif
                </td>
                <td>{{ $manager->lastlogin_on ?? '-' }}</td>
                <td>{{ $shop->created_on }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="100" class="text-center">
                    {{ __('shop.no_data_found') }}
                </td>
            </tr>
        @endforelse
        </tbody>

        @if ($shops->count())
            <tfoot>
            <tr class="bg-light font-weight-bold">
                <td>{{ __('messages.total') }}</td>
                <td>{{ number_format($totalAmountType, 2) }}</td>
                <td>{{ number_format($totalBalance, 2) }}</td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
