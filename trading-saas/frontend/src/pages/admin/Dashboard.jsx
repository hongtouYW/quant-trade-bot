import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { Users, DollarSign, Bot, TrendingUp, Trophy } from 'lucide-react';

export default function AdminDashboard() {
  const [lbDays, setLbDays] = useState(30);
  const { data, loading } = useApi('/admin/dashboard', { interval: 30000 });
  const { data: bots } = useApi('/admin/bots/status', { interval: 10000 });
  const { data: lb } = useApi(`/admin/leaderboard?days=${lbDays}`, { interval: 60000 });

  if (loading || !data) {
    return <div className="animate-pulse text-text-secondary">Loading dashboard...</div>;
  }

  const botList = bots?.bots || [];
  const runningBots = botList.filter((b) => b.status === 'running').length;
  const leaderboard = lb?.leaderboard || [];

  const botColumns = [
    { key: 'username', label: 'Agent' },
    { key: 'status', label: 'Bot', render: (_, r) => <StatusBadge status={r.status} /> },
    { key: 'risk_score', label: 'Risk', render: (v) => (
      <span className={v >= 7 ? 'text-danger' : v >= 4 ? 'text-warning' : 'text-success'}>{v}/10</span>
    )},
    { key: 'last_scan', label: 'Last Scan', render: (v) => v ? new Date(v).toLocaleTimeString() : '-' },
  ];

  const lbColumns = [
    { key: 'rank', label: '#', render: (_, __, i) => (
      <span className={i === 0 ? 'text-warning font-bold' : ''}>{i + 1}</span>
    )},
    { key: 'display_name', label: 'Agent', render: (v, r) => (
      <span className="font-medium">{v || r.username}</span>
    )},
    { key: 'total_pnl', label: 'PnL', render: (v) => <PnlValue value={v} /> },
    { key: 'win_rate', label: 'Win Rate', render: (v) => `${v}%` },
    { key: 'total_trades', label: 'Trades', render: (v, r) => (
      <span>{v} <span className="text-text-secondary text-xs">({r.win_trades}W/{r.loss_trades}L)</span></span>
    )},
    { key: 'bot_status', label: 'Bot', render: (v) => <StatusBadge status={v} /> },
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Admin Dashboard</h2>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Agents" value={data.total_agents} icon={Users} />
        <StatCard label="Active Agents" value={data.active_agents} icon={Users} color="text-success" />
        <StatCard label="Running Bots" value={runningBots} sub={`of ${botList.length}`} icon={Bot} color="text-success" />
        <StatCard
          label="Total PnL"
          value={<PnlValue value={data.total_pnl} />}
          icon={TrendingUp}
          color={data.total_pnl >= 0 ? 'text-success' : 'text-danger'}
        />
      </div>

      <Card>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Trophy size={16} className="text-warning" />
            <h3 className="text-sm font-semibold">Agent Leaderboard</h3>
          </div>
          <div className="flex gap-1">
            {[7, 30, 90, 9999].map((d) => (
              <button
                key={d}
                onClick={() => setLbDays(d)}
                className={`px-2 py-0.5 text-xs rounded ${
                  lbDays === d ? 'bg-primary text-white' : 'text-text-secondary hover:bg-white/5'
                }`}
              >
                {d === 9999 ? 'All' : `${d}d`}
              </button>
            ))}
          </div>
        </div>
        <Table columns={lbColumns} data={leaderboard} emptyText="No trade data yet" />
      </Card>

      <Card>
        <h3 className="text-sm font-semibold mb-3">Bot Status</h3>
        <Table columns={botColumns} data={botList} emptyText="No agents yet" />
      </Card>
    </div>
  );
}
