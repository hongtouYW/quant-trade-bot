import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { Users, DollarSign, Bot, TrendingUp } from 'lucide-react';

export default function AdminDashboard() {
  const { data, loading } = useApi('/admin/dashboard', { interval: 30000 });
  const { data: bots } = useApi('/admin/bots/status', { interval: 10000 });

  if (loading || !data) {
    return <div className="animate-pulse text-text-secondary">Loading dashboard...</div>;
  }

  const botList = bots?.bots || [];
  const runningBots = botList.filter((b) => b.status === 'running').length;

  const columns = [
    { key: 'username', label: 'Agent' },
    { key: 'status', label: 'Bot', render: (_, r) => <StatusBadge status={r.status} /> },
    { key: 'risk_score', label: 'Risk', render: (v) => (
      <span className={v >= 7 ? 'text-danger' : v >= 4 ? 'text-warning' : 'text-success'}>{v}/10</span>
    )},
    { key: 'last_scan', label: 'Last Scan', render: (v) => v ? new Date(v).toLocaleTimeString() : '-' },
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Admin Dashboard</h2>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Agents" value={data.total_agents} icon={Users} />
        <StatCard label="Active Agents" value={data.active_agents} icon={Users} color="text-success" />
        <StatCard label="Running Bots" value={runningBots} sub={`of ${botList.length}`} icon={Bot} color="text-success" />
        <StatCard
          label="Trading Enabled"
          value={data.trading_enabled}
          icon={TrendingUp}
          color="text-warning"
        />
      </div>

      <Card>
        <h3 className="text-sm font-semibold mb-3">Bot Status</h3>
        <Table columns={columns} data={botList} emptyText="No agents yet" />
      </Card>
    </div>
  );
}
