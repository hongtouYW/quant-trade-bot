<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm">
        <thead class="thead-light">
        <tr>
            <th>{{ __('messages.transaction_time') }}</th>
            <th>{{ __('messages.transaction_type') }}</th>
            <th>{{ __('messages.edit_by') }}</th>
            <th class="text-right">{{ __('messages.before_balance') }}</th>
            <th class="text-right">{{ __('messages.amount') }}</th>
            <th class="text-right">{{ __('messages.after_balance') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($shopcredits as $credit)
            <tr>
                <td>{{ $credit->created_on }}</td>
                <td>{{ __($credit->type) }}</td>
                <td>{{ __($credit->agent->agent_name) }}</td>
                <td class="text-right font-weight-bold">
                    {{ number_format($credit->before_balance, 2) }}
                </td>
                <td class="text-right font-weight-bold {{ $credit->amount < 0 ? 'text-danger' : 'text-success' }}">
                    {{ $credit->amount < 0
                        ? '-' . number_format(abs($credit->amount), 2)
                        : '+' . number_format($credit->amount, 2) }}
                </td>
                <td class="text-right text-primary font-weight-bold">
                    {{ number_format($credit->after_balance, 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="100" class="text-center text-muted">
                    {{ __('messages.nodata') }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
