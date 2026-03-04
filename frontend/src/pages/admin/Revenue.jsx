import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { DollarSign, Clock, CheckCircle } from 'lucide-react';

export default function Revenue() {
  const { data, loading } = useApi('/admin/billing/revenue');
  const { data: periodsData, refetch: refetchPeriods } = useApi('/admin/billing/periods');

  const approvePeriod = async (id) => {
    await api.post(`/admin/billing/periods/${id}/approve`);
    refetchPeriods();
  };

  const markPaid = async (id) => {
    await api.post(`/admin/billing/periods/${id}/paid`);
    refetchPeriods();
  };

  if (loading || !data) {
    return <div className="animate-pulse text-text-secondary">Loading revenue...</div>;
  }

  const agentCols = [
    { key: 'username', label: 'Agent' },
    { key: 'total_pnl', label: 'Total PnL', render: (v) => <PnlValue value={v} /> },
    { key: 'total_commission', label: 'Commission', render: (v) => `${v?.toFixed(2) || 0}U` },
    { key: 'profit_share_pct', label: 'Share %', render: (v) => `${v}%` },
  ];

  const periodCols = [
    { key: 'agent_username', label: 'Agent', render: (_, r) => r.agent_username || `Agent #${r.agent_id}` },
    { key: 'period_start', label: 'Period' , render: (_, r) => `${r.period_start} ~ ${r.period_end}` },
    { key: 'gross_pnl', label: 'PnL', render: (v) => <PnlValue value={v} /> },
    { key: 'commission_amount', label: 'Commission', render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    { key: 'actions', label: '', render: (_, row) => (
      <div className="flex gap-1">
        {row.status === 'pending' && (
          <button
            onClick={() => approvePeriod(row.id)}
            className="text-xs px-2 py-1 rounded bg-success/15 text-success hover:bg-success/25"
          >
            Approve
          </button>
        )}
        {(row.status === 'pending' || row.status === 'approved') && (
          <button
            onClick={() => markPaid(row.id)}
            className="text-xs px-2 py-1 rounded bg-primary/15 text-primary hover:bg-primary/25"
          >
            Mark Paid
          </button>
        )}
      </div>
    )},
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Revenue Dashboard</h2>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard label="Total Revenue" value={`${data.total_revenue}U`} icon={DollarSign} color="text-success" />
        <StatCard label="Pending" value={`${data.pending_revenue}U`} icon={Clock} color="text-warning" />
        <StatCard label="Paid" value={`${data.paid_revenue}U`} icon={CheckCircle} color="text-primary" />
      </div>

      <Card>
        <h3 className="text-sm font-semibold mb-3">Per-Agent Revenue</h3>
        <Table columns={agentCols} data={data.agents || []} />
      </Card>

      <Card>
        <h3 className="text-sm font-semibold mb-3">Billing Periods</h3>
        <Table columns={periodCols} data={periodsData?.periods || []} emptyText="No billing periods" />
      </Card>
    </div>
  );
}
