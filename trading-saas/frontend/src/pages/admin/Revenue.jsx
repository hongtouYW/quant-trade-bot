import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { DollarSign, Clock, CheckCircle } from 'lucide-react';
import { useLanguage } from '../../contexts/LanguageContext';

export default function Revenue() {
  const { t } = useLanguage();
  const { data, loading } = useApi('/admin/billing/revenue');
  const { data: periodsData, refetch: refetchPeriods } = useApi('/admin/billing/periods');

  const approvePeriod = async (id) => {
    try {
      await api.post(`/admin/billing/periods/${id}/approve`);
      refetchPeriods();
    } catch (err) {
      alert(err.response?.data?.error || t('admin.actionFailed'));
    }
  };

  const markPaid = async (id) => {
    try {
      await api.post(`/admin/billing/periods/${id}/paid`);
      refetchPeriods();
    } catch (err) {
      alert(err.response?.data?.error || t('admin.actionFailed'));
    }
  };

  if (loading || !data) {
    return <div className="animate-pulse text-text-secondary">{t('admin.loadingRevenue')}</div>;
  }

  const agentCols = [
    { key: 'username', label: t('admin.agent') },
    { key: 'total_pnl', label: t('admin.totalPnl'), render: (v) => <PnlValue value={v} /> },
    { key: 'total_commission', label: t('billing.commission'), render: (v) => `${v?.toFixed(2) || 0}U` },
    { key: 'profit_share_pct', label: t('admin.profitShare'), render: (v) => `${v}%` },
  ];

  const periodCols = [
    { key: 'agent_username', label: t('admin.agent'), render: (_, r) => r.agent_username || `Agent #${r.agent_id}` },
    { key: 'period_start', label: t('billing.period'), render: (_, r) => `${r.period_start} ~ ${r.period_end}` },
    { key: 'gross_pnl', label: t('billing.pnl'), render: (v) => <PnlValue value={v} /> },
    { key: 'commission_amount', label: t('billing.commission'), render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'status', label: t('dashboard.status'), render: (v) => <StatusBadge status={v} /> },
    { key: 'actions', label: '', render: (_, row) => (
      <div className="flex gap-1">
        {row.status === 'pending' && (
          <button
            onClick={() => approvePeriod(row.id)}
            className="text-xs px-2 py-1 rounded bg-success/15 text-success hover:bg-success/25"
          >
            {t('common.approve')}
          </button>
        )}
        {(row.status === 'pending' || row.status === 'approved') && (
          <button
            onClick={() => markPaid(row.id)}
            className="text-xs px-2 py-1 rounded bg-primary/15 text-primary hover:bg-primary/25"
          >
            {t('admin.markPaid')}
          </button>
        )}
      </div>
    )},
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">{t('admin.revenueDashboard')}</h2>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard label={t('admin.totalRevenue')} value={`${data.total_revenue}U`} icon={DollarSign} color="text-success" />
        <StatCard label={t('admin.pending')} value={`${data.pending_revenue}U`} icon={Clock} color="text-warning" />
        <StatCard label={t('admin.paid')} value={`${data.paid_revenue}U`} icon={CheckCircle} color="text-primary" />
      </div>

      <Card>
        <h3 className="text-sm font-semibold mb-3">{t('admin.perAgentRevenue')}</h3>
        <Table columns={agentCols} data={data.agents || []} />
      </Card>

      <Card>
        <h3 className="text-sm font-semibold mb-3">{t('admin.billingPeriods')}</h3>
        <Table columns={periodCols} data={periodsData?.periods || []} emptyText={t('admin.noBillingPeriods')} />
      </Card>
    </div>
  );
}
