@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
    $isMasterAdmin = $user->user_role === 'masteradmin';
@endphp

@section('title', __('messages.dashboard'))
@section('header-title', __('messages.dashboard'))

@section('header-description')
    {{ __('messages.welcome', ['name' => $user->user_name ?? 'User']) }}
@endsection

@section('content')
    <div class="dash-wrap">

        {{-- ═══════════════════════════════════════════════════════
             TODAY PULSE BAR
        ═══════════════════════════════════════════════════════ --}}
        <div class="today-bar">
            <div class="today-label">
                <i class="fas fa-satellite-dish pulse-dot"></i>
                <span>Today</span>
                <small>{{ \Carbon\Carbon::today()->format('d M Y') }}</small>
            </div>
            <div class="today-stats">
                <div class="today-stat deposit">
                    <i class="fas fa-arrow-down"></i>
                    <div>
                        <span class="ts-label">Deposit</span>
                        <span class="ts-val">{{ number_format($todayDeposit, 2) }}</span>
                    </div>
                </div>
                <div class="today-stat withdraw">
                    <i class="fas fa-arrow-up"></i>
                    <div>
                        <span class="ts-label">Withdraw</span>
                        <span class="ts-val">{{ number_format($todayWithdraw, 2) }}</span>
                    </div>
                </div>
                <div class="today-stat register">
                    <i class="fas fa-user-plus"></i>
                    <div>
                        <span class="ts-label">New Register</span>
                        <span class="ts-val">{{ number_format($todayRegister) }}</span>
                    </div>
                </div>
                <div class="today-stat login">
                    <i class="fas fa-sign-in-alt"></i>
                    <div>
                        <span class="ts-label">Active Login</span>
                        <span class="ts-val">{{ number_format($todayLogin) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="content pt-2 pb-4">
            <div class="container-fluid">

                {{-- ═══════════════════════════════════════════════════════
                     FINANCIAL OVERVIEW
                ═══════════════════════════════════════════════════════ --}}
                <div class="section-label">
                    <i class="fas fa-chart-pie"></i> Financial Overview
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-xl-4 col-md-6">
                        <div class="kpi-card kpi-blue">
                            <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
                            <div class="kpi-body">
                                <div class="kpi-title">Total Balance</div>
                                <div class="kpi-value">{{ number_format($totalagentbalance, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="kpi-card kpi-green">
                            <div class="kpi-icon"><i class="fas fa-coins"></i></div>
                            <div class="kpi-body">
                                <div class="kpi-title">Total Principal</div>
                                <div class="kpi-value">{{ number_format($totalagentprincipal, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="kpi-card kpi-amber">
                            <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="kpi-body">
                                <div class="kpi-title">Total Profit</div>
                                <div class="kpi-value">{{ number_format($totalagentincome, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════
                     SYSTEM COUNTS
                ═══════════════════════════════════════════════════════ --}}
                <div class="section-label">
                    <i class="fas fa-sitemap"></i> System Overview
                </div>
                <div class="row g-3 mb-4">
                    @if($isMasterAdmin)
                        <div class="col-xl-3 col-6">
                            <div class="count-card">
                                <i class="fas fa-user-shield count-icon text-purple"></i>
                                <div class="count-val">{{ count($supervisorList) }}</div>
                                <div class="count-lbl">Supervisors</div>
                            </div>
                        </div>
                    @endif
                    <div class="col-xl-3 col-6">
                        <div class="count-card">
                            <i class="fas fa-user-tie count-icon text-danger"></i>
                            <div class="count-val">{{ $totalManagers }}</div>
                            <div class="count-lbl">Managers</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-6">
                        <div class="count-card">
                            <i class="fas fa-store count-icon text-warning"></i>
                            <div class="count-val">{{ $totalShops }}</div>
                            <div class="count-lbl">Shops</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-6">
                        <div class="count-card">
                            <i class="fas fa-users count-icon text-info"></i>
                            <div class="count-val">{{ $totalMembers }}</div>
                            <div class="count-lbl">Members</div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════
                     CREDIT BREAKDOWN
                ═══════════════════════════════════════════════════════ --}}
                <div class="section-label">
                    <i class="fas fa-credit-card"></i> Credit Breakdown
                </div>
                <div class="row g-3 mb-4">

                    {{-- MANAGER --}}
                    <div class="col-xl-4 col-md-6">
                        <div class="credit-card">
                            <div class="cc-header cc-red">
                                <i class="fas fa-user-tie"></i>
                                <span>Manager</span>
                            </div>
                            <div class="cc-body">
                                <div class="cc-row">
                                    <span>Principal</span>
                                    <strong>{{ number_format($totalManagerPrincipal, 2) }}</strong>
                                </div>
                                <div class="cc-row">
                                    <span>Balance</span>
                                    <strong>{{ number_format($totalManagerBalance, 2) }}</strong>
                                </div>
                                <div class="cc-row highlight">
                                    <span>Used</span>
                                    <strong>{{ number_format($totalManagerUsed, 2) }}</strong>
                                </div>
                                <div class="cc-progress-wrap">
                                    <div class="cc-progress-bar" style="width:{{ min($managerUsagePercent,100) }}%; background:#ef4444;"></div>
                                </div>
                                <div class="cc-pct">{{ number_format($managerUsagePercent,1) }}% used</div>
                            </div>
                        </div>
                    </div>

                    {{-- SHOP --}}
                    <div class="col-xl-4 col-md-6">
                        <div class="credit-card">
                            <div class="cc-header cc-amber">
                                <i class="fas fa-store"></i>
                                <span>Shop</span>
                            </div>
                            <div class="cc-body">
                                <div class="cc-row">
                                    <span>Principal</span>
                                    <strong>{{ number_format($totalShopPrincipal, 2) }}</strong>
                                </div>
                                <div class="cc-row">
                                    <span>Balance</span>
                                    <strong>{{ number_format($totalShopBalance, 2) }}</strong>
                                </div>
                                <div class="cc-row highlight">
                                    <span>Used</span>
                                    <strong>{{ number_format($totalShopUsed, 2) }}</strong>
                                </div>
                                <div class="cc-progress-wrap">
                                    <div class="cc-progress-bar" style="width:{{ min($shopUsagePercent,100) }}%; background:#f59e0b;"></div>
                                </div>
                                <div class="cc-pct">{{ number_format($shopUsagePercent,1) }}% used</div>
                            </div>
                        </div>
                    </div>

                    {{-- MEMBER --}}
                    <div class="col-xl-4 col-md-6">
                        <div class="credit-card">
                            <div class="cc-header cc-blue">
                                <i class="fas fa-users"></i>
                                <span>Member</span>
                            </div>
                            <div class="cc-body">
                                <div class="cc-row">
                                    <span>Balance</span>
                                    <strong>{{ number_format($totalMemberBalance, 2) }}</strong>
                                </div>
                                <div class="cc-row">
                                    <span>Total Deposit</span>
                                    <strong class="text-success">{{ number_format($totalMemberDeposit, 2) }}</strong>
                                </div>
                                <div class="cc-row highlight">
                                    <span>Total Withdraw</span>
                                    <strong class="text-danger">{{ number_format($totalMemberWithdraw, 2) }}</strong>
                                </div>
                                <div class="cc-progress-wrap">
                                    <div class="cc-progress-bar" style="width:{{ min($memberWithdrawPercent,100) }}%; background:#3b82f6;"></div>
                                </div>
                                <div class="cc-pct">{{ number_format($memberWithdrawPercent,1) }}% withdrawn</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════
                     TOP GAMES
                ═══════════════════════════════════════════════════════ --}}
                @if($topGames->count())
                    <div class="section-label">
                        <i class="fas fa-gamepad"></i> Most Played Games (Top 5)
                    </div>
                    <div class="card dash-table-card mb-4">
                        <div class="card-body p-0">
                            <table class="table dash-table mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Game</th>
                                    <th>Bet Count</th>
                                    <th>Total Bet Amount</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($topGames as $i => $tg)
                                    <tr>
                                        <td>
                                            @if($i === 0) <span class="rank gold">①</span>
                                            @elseif($i === 1) <span class="rank silver">②</span>
                                            @elseif($i === 2) <span class="rank bronze">③</span>
                                            @else <span class="rank plain">{{ $i+1 }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $tg->Game->game_name ?? ('Game #'.$tg->game_id) }}</td>
                                        <td><span class="badge-count">{{ number_format($tg->bet_count) }}</span></td>
                                        <td>{{ number_format($tg->total_bet, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- ═══════════════════════════════════════════════════════
                     MASTER ADMIN: SUPERVISOR BREAKDOWN
                ═══════════════════════════════════════════════════════ --}}
                @if($isMasterAdmin && count($supervisorList))
                    <div class="section-label">
                        <i class="fas fa-user-shield"></i> Supervisor Breakdown
                    </div>
                    <div class="card dash-table-card mb-4">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Supervisor</th>
                                        <th>Managers</th>
                                        <th>Shops</th>
                                        <th>Members</th>
                                        <th>Principal</th>
                                        <th>Balance</th>
                                        <th>Profit</th>
                                        <th>Deposit</th>
                                        <th>Withdraw</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($supervisorList as $sv)
                                        <tr>
                                            <td><strong>{{ $sv['agent']->agent_name ?? $sv['agent']->agent_id }}</strong></td>
                                            <td><span class="badge-count">{{ $sv['manager_count'] }}</span></td>
                                            <td><span class="badge-count">{{ $sv['shop_count'] }}</span></td>
                                            <td><span class="badge-count">{{ $sv['member_count'] }}</span></td>
                                            <td>{{ number_format($sv['principal'], 2) }}</td>
                                            <td>{{ number_format($sv['balance'], 2) }}</td>
                                            <td>
                                    <span class="{{ $sv['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($sv['profit'], 2) }}
                                    </span>
                                            </td>
                                            <td class="text-success">{{ number_format($sv['deposit'], 2) }}</td>
                                            <td class="text-danger">{{ number_format($sv['withdraw'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══════════════════════════════════════════════════════
                     SUPER ADMIN: MANAGER → SHOP BREAKDOWN
                ═══════════════════════════════════════════════════════ --}}
                @if(!$isMasterAdmin && count($managerList))
                    <div class="section-label">
                        <i class="fas fa-user-tie"></i> Manager & Shop Breakdown
                    </div>

                    @foreach($managerList as $mgr)
                        <div class="manager-block mb-3">
                            {{-- Manager header --}}
                            <div class="mgr-header" data-bs-toggle="collapse" data-bs-target="#mgr-{{ $loop->index }}" role="button">
                                <div class="mgr-title">
                                    <i class="fas fa-user-tie"></i>
                                    <strong>{{ $mgr['manager']->manager_name ?? ('Manager #'.$mgr['manager']->manager_id) }}</strong>
                                    <span class="mgr-badge">{{ $mgr['shop_count'] }} shops</span>
                                </div>
                                <div class="mgr-meta">
                                    <span>Principal: <strong>{{ number_format($mgr['principal'],2) }}</strong></span>
                                    <span>Balance: <strong>{{ number_format($mgr['balance'],2) }}</strong></span>
                                    <span class="{{ $mgr['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                        Profit: <strong>{{ number_format($mgr['profit'],2) }}</strong>
                    </span>
                                    <i class="fas fa-chevron-down mgr-chevron"></i>
                                </div>
                            </div>

                            {{-- Shop sub-table --}}
                            <div class="collapse show" id="mgr-{{ $loop->index }}">
                                @if(count($mgr['shops']))
                                    <div class="table-responsive">
                                        <table class="table dash-table sub-table mb-0">
                                            <thead>
                                            <tr>
                                                <th>Shop</th>
                                                <th>Members</th>
                                                <th>Principal</th>
                                                <th>Balance</th>
                                                <th>Profit</th>
                                                <th>Deposit</th>
                                                <th>Withdraw</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($mgr['shops'] as $sp)
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-store text-warning me-1"></i>
                                                        {{ $sp['shop']->shop_name ?? ('Shop #'.$sp['shop']->shop_id) }}
                                                    </td>
                                                    <td><span class="badge-count">{{ $sp['member_count'] }}</span></td>
                                                    <td>{{ number_format($sp['principal'], 2) }}</td>
                                                    <td>{{ number_format($sp['balance'], 2) }}</td>
                                                    <td>
                                    <span class="{{ $sp['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($sp['profit'], 2) }}
                                    </span>
                                                    </td>
                                                    <td class="text-success">{{ number_format($sp['deposit'], 2) }}</td>
                                                    <td class="text-danger">{{ number_format($sp['withdraw'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="no-shops">No shops found for this manager.</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif

            </div>
        </section>
    </div>
@endsection

@section('css')
    <style>
        /* ── Root variables ─────────────────────────────── */
        :root {
            --dash-bg: #f0f2f5;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --blue: #2563eb;
            --green: #16a34a;
            --amber: #d97706;
            --red: #dc2626;
            --purple: #7c3aed;
            --radius: 10px;
            --shadow: 0 1px 4px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.05);
        }

        .dash-wrap {
            background: var(--dash-bg);
            min-height: 100vh;
        }

        /* ── Section label ─────────────────────────────── */
        .section-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 1.5rem 0 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-label i { color: var(--blue); }

        /* ── Today bar ─────────────────────────────────── */
        .today-bar {
            background: #1e293b;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
            padding: 0 1.5rem;
            min-height: 56px;
            font-size: 0.82rem;
        }
        .today-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.25rem 0.75rem 0;
            border-right: 1px solid #334155;
            margin-right: 1rem;
            white-space: nowrap;
        }
        .today-label span { font-weight: 700; font-size: 0.9rem; }
        .today-label small { color: #94a3b8; font-size: 0.72rem; }
        .pulse-dot {
            color: #4ade80;
            animation: pulse 1.8s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

        .today-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
        }
        .today-stat {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1.25rem;
            border-right: 1px solid #334155;
        }
        .today-stat:last-child { border-right: none; }
        .today-stat i { font-size: 1.1rem; }
        .today-stat.deposit i  { color: #4ade80; }
        .today-stat.withdraw i { color: #f87171; }
        .today-stat.register i { color: #60a5fa; }
        .today-stat.login i    { color: #fbbf24; }
        .ts-label {
            display: block;
            font-size: 0.68rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ts-val {
            display: block;
            font-weight: 700;
            font-size: 0.92rem;
            color: #f1f5f9;
        }

        /* ── KPI cards ─────────────────────────────────── */
        .kpi-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 1.1rem 1.25rem;
            border-left: 4px solid transparent;
            transition: transform .15s, box-shadow .15s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,0.1); }
        .kpi-blue  { border-color: var(--blue); }
        .kpi-green { border-color: var(--green); }
        .kpi-amber { border-color: var(--amber); }
        .kpi-icon {
            width: 46px; height: 46px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .kpi-blue  .kpi-icon { background: #eff6ff; color: var(--blue); }
        .kpi-green .kpi-icon { background: #f0fdf4; color: var(--green); }
        .kpi-amber .kpi-icon { background: #fffbeb; color: var(--amber); }
        .kpi-title { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
        .kpi-value { font-size: 1.45rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }

        /* ── Count cards ───────────────────────────────── */
        .count-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.25rem;
            text-align: center;
            transition: transform .15s;
        }
        .count-card:hover { transform: translateY(-2px); }
        .count-icon { font-size: 1.6rem; display: block; margin-bottom: 8px; }
        .count-val { font-size: 2rem; font-weight: 800; color: var(--text-main); line-height: 1; }
        .count-lbl { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
        .text-purple { color: var(--purple) !important; }

        /* ── Credit cards ──────────────────────────────── */
        .credit-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .cc-header {
            display: flex; align-items: center; gap: 10px;
            padding: 0.7rem 1rem;
            font-weight: 700; font-size: 0.85rem; color: #fff;
        }
        .cc-red   { background: #dc2626; }
        .cc-amber { background: #d97706; }
        .cc-blue  { background: #2563eb; }
        .cc-body { padding: 0.75rem 1rem; }
        .cc-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.3rem 0;
            font-size: 0.82rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
        }
        .cc-row:last-of-type { border-bottom: none; }
        .cc-row.highlight { color: var(--text-main); font-weight: 600; }
        .cc-row strong { color: var(--text-main); }
        .cc-progress-wrap {
            height: 5px; background: #f1f5f9; border-radius: 99px;
            margin: 0.5rem 0 0.3rem; overflow: hidden;
        }
        .cc-progress-bar { height: 100%; border-radius: 99px; transition: width .6s ease; }
        .cc-pct { font-size: 0.7rem; color: var(--text-muted); text-align: right; }

        /* ── Shared table styles ───────────────────────── */
        .dash-table-card {
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .dash-table { font-size: 0.82rem; }
        .dash-table thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .dash-table tbody td {
            padding: 0.65rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-main);
        }
        .dash-table tbody tr:last-child td { border-bottom: none; }
        .dash-table tbody tr:hover td { background: #fafbff; }

        /* ranks */
        .rank { font-size: 1.1rem; }
        .rank.gold   { color: #f59e0b; }
        .rank.silver { color: #9ca3af; }
        .rank.bronze { color: #b45309; }
        .rank.plain  { color: var(--text-muted); font-size: 0.85rem; }

        /* badge count */
        .badge-count {
            display: inline-block;
            background: #eff6ff;
            color: var(--blue);
            font-weight: 700;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 99px;
        }

        /* ── Manager blocks ────────────────────────────── */
        .manager-block {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .mgr-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 0.85rem 1.1rem;
            background: #f8fafc;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            user-select: none;
        }
        .mgr-header:hover { background: #f1f5f9; }
        .mgr-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.88rem;
        }
        .mgr-title i { color: var(--red); }
        .mgr-badge {
            background: #fee2e2;
            color: var(--red);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
        }
        .mgr-meta {
            display: flex; align-items: center; flex-wrap: wrap; gap: 14px;
            font-size: 0.78rem; color: var(--text-muted);
        }
        .mgr-meta strong { color: var(--text-main); }
        .mgr-chevron { font-size: 0.7rem; color: var(--text-muted); transition: transform .2s; }
        .sub-table { margin: 0; }
        .sub-table thead th { background: #fffbeb; }
        .no-shops {
            padding: 1rem 1.25rem;
            font-size: 0.82rem;
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
@endsection

@section('js')
    <script>
        // Chevron rotation on collapse
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(el) {
            var target = document.querySelector(el.getAttribute('data-bs-target'));
            if (!target) return;
            target.addEventListener('show.bs.collapse', function() {
                el.querySelector('.mgr-chevron').style.transform = 'rotate(0deg)';
            });
            target.addEventListener('hide.bs.collapse', function() {
                el.querySelector('.mgr-chevron').style.transform = 'rotate(-90deg)';
            });
        });
    </script>
@endsection
