<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
        <tr>
            <th>{{ __('member.member_login') }}</th>
            <th>{{ __('member.member_name') }}</th>
            <th class="text-primary text-md font-weight-bold mb-0">{{ __('member.balance') }}</th>
            <th>{{ __('member.phone') }}</th>
            <th>{{ __('member.status') }}</th>
            <th>{{ __('member.last_login') }}</th>
            <th>{{ __('messages.created_on') }}</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($members as $member)
            <tr>
                <td>{{ $member->member_login }}</td>
                <td>{{ $member->member_name }}</td>
                <td>
                    <a class="text-primary text-md font-weight-bold mb-0" href="{{ route('admin.member.show', $member->member_id) }}">
                        {{ number_format($member->balance, 2) }}
                    </a>
                </td>
                <th>{{ formatPhone($member->phone) }}</th>
                <td>
                    @if ($member->status)
                        <span class="badge badge-success">{{ __('messages.active') }}</span>
                    @else
                        <span class="badge badge-secondary">{{ __('messages.inactive') }}</span>
                    @endif
                </td>
                <td>{{ $member->lastlogin_on }}</td>
                <td>{{ $member->created_on }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center">
                    {{ __('member.no_data_found') }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
