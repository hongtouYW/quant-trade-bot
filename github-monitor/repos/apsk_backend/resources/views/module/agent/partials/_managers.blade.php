@php
    $totalPrincipal = 0;
    $totalBalance = 0;
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
        <tr>
            <th>{{ __('manager.manager_login') }}</th>
            <th>{{ __('manager.manager_name') }}</th>
            <th>{{ __('manager.principal') }}</th>
            <th class="text-primary text-md font-weight-bold mb-0">{{ __('manager.balance') }}</th>
            <th>{{ __('manager.status') }}</th>
            <th>{{ __('manager.last_login') }}</th>
            <th>{{ __('messages.created_on') }}</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($managers as $manager)
            @php
                $totalPrincipal += $manager->principal;
                $totalBalance += $manager->balance;
            @endphp
            <tr>
                <td>{{ $manager->manager_login }}</td>
                <td>{{ $manager->manager_name }}</td>
                <td>{{ number_format($manager->principal, 2) }}</td>
                <td>
                    <a href="{{ route('admin.manager.show', $manager->manager_id) }}" class="text-primary text-md font-weight-bold mb-0">
                        {{ number_format($manager->balance, 2) }}
                    </a>
                </td>
                <td>
                    @if ($manager->status)
                        <span class="badge badge-success">{{ __('messages.active') }}</span>
                    @else
                        <span class="badge badge-secondary">{{ __('messages.inactive') }}</span>
                    @endif
                </td>
                <td>{{ $manager->lastlogin_on ?? '-' }}</td>
                <td>{{ $manager->created_on }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">
                    {{ __('manager.no_data_found') }}
                </td>
            </tr>
        @endforelse
        </tbody>

        @if ($managers->count())
            <tfoot>
            <tr class="bg-light font-weight-bold">
                <td colspan="2">{{ __('messages.total') }}</td>
                <td>{{ number_format($totalPrincipal, 2) }}</td>
                <td>{{ number_format($totalBalance, 2) }}</td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
