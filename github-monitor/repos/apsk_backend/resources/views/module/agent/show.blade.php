@extends('adminlte::page')

@section('title', __('agent.agent_detail'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <button onclick="window.history.back()"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </button>
            </div>
            {{-- Agent info --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h2>{{ $agent->agent_name }}</h2>
                    <h5 class="text-muted">
                        {{ __('agent.principal') }}: {{ number_format($agent->principal, 2) }} |
                        {{ __('agent.balance') }}: {{ number_format($agent->balance, 2) }}
                    </h5>
                </div>
            </div>

            {{-- Agent credit history --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{ __('agent.agent_credit') }}</h4>
                </div>
                <div class="card-body">
                    @include('module.agent.partials._agentcredits', ['agentcredits' => $agentcredits])
                </div>
            </div>
            
            {{-- Managers under agent --}}
            <div class="card">
                <div class="card-header">
                    <h4>{{ __('manager.manager_list') }}</h4>
                </div>
                <div class="card-body" id="managers-list">
                    @include('module.agent.partials._managers', ['managers' => $managers])
                </div>
            </div>
        </div>
    </div>
@endsection
