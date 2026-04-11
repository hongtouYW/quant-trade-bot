<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm">
        <thead class="thead-light">
        <tr>
            <th>{{ __('messages.transaction_time') }}</th>
            <th>{{ __('messages.transaction_type') }}</th>
            <th class="text-right">{{ __('messages.before_balance') }}</th>
            <th class="text-right">{{ __('messages.amount') }}</th>
            <th class="text-right">{{ __('messages.after_balance') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($membercredits as $credit)
            <tr>
                @php
                    $moneyOut = in_array($credit->type, ['withdraw', 'game_reload']);
                    $creditType = match ($credit->type) {
                        'game_reload' => 'gamedeposit',
                        'game_withdraw' => 'gamewithdraw',
                        default => $credit->type
                    };
                @endphp
                <td>{{ $credit->created_on }}</td>
                <td class="text-right font-weight-bold {{ $moneyOut ? 'text-danger' : 'text-success' }}">
                    {{ __('credit.' . $creditType) }}
                    @if(!empty($credit->provider))
                        <span>({{ $credit->provider }})</span>
                    @endif
                </td>
                <td class="text-right font-weight-bold">
                    {{ number_format($credit->before_balance, 2) }}
                </td>
                <td class="text-right font-weight-bold {{ $moneyOut ? 'text-danger' : 'text-success' }}">
                    {{ $moneyOut
                        ? '-' . number_format(abs($credit->amount), 2)
                        : '+' . number_format($credit->amount, 2) }}
                </td>
                <td class="text-right text-primary font-weight-bold">
                    {{ number_format($credit->after_balance, 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted">
                    {{ __('messages.nodata') }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">
    {{ $membercredits->links() }}
</div>
