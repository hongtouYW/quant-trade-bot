import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { useLanguage } from '../../contexts/LanguageContext';
import { Receipt, TrendingUp, DollarSign } from 'lucide-react';

export default function Billing() {
  const { t } = useLanguage();
  const { data: current, loading } = useApi('/agent/billing/current');
  const { data: historyData } = useApi('/agent/billing/history');

  if (loading) {
    return <div className="animate-pulse text-text-secondary">{t('billing.loadingBilling')}</div>;
  }

  const period = current?.period;
  const history = historyData?.periods || [];

  const columns = [
    { key: 'period_start', label: t('billing.period'), render: (_, r) => `${r.period_start} ~ ${r.period_end}` },
    { key: 'starting_capital', label: t('billing.start'), render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'ending_capital', label: t('billing.end'), render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'gross_pnl', label: t('billing.pnl'), render: (v) => <PnlValue value={v} /> },
    { key: 'high_water_mark', label: t('billing.hwm'), render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'commission_amount', label: t('billing.commission'), render: (v) => `${Number(v || 0).toFixed(2)}U` },
    { key: 'status', label: t('billing.status'), render: (v) => <StatusBadge status={v} /> },
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">{t('billing.title')}</h2>

      {period && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <StatCard
              label={t('billing.currentPeriod')}
              value={`${period.period_start} ~ ${period.period_end}`}
              icon={Receipt}
            />
            <StatCard
              label={t('billing.highWaterMark')}
              value={`${Number(period.high_water_mark || 0).toFixed(2)}U`}
              icon={TrendingUp}
              color="text-warning"
            />
            <StatCard
              label={t('billing.shareRate')}
              value={`${period.profit_share_pct || 20}%`}
              icon={DollarSign}
            />
          </div>

          <Card>
            <h3 className="text-sm font-semibold mb-2">{t('billing.currentPeriodDetails')}</h3>
            <div className="space-y-2 text-sm">
              <Row label={t('billing.startingCapital')} value={`${Number(period.starting_capital || 0).toFixed(2)}U`} />
              <Row label={t('billing.currentPnl')} value={<PnlValue value={current.current_pnl} />} />
              <Row label={t('billing.projectedCommission')} value={`${Number(current.projected_commission || 0).toFixed(2)}U`} />
              <Row label={t('billing.status')} value={<StatusBadge status={period.status} />} />
            </div>
          </Card>
        </>
      )}

      <Card>
        <h3 className="text-sm font-semibold mb-3">{t('billing.billingHistory')}</h3>
        <Table columns={columns} data={history} emptyText={t('billing.noBillingHistory')} />
      </Card>
    </div>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex items-center justify-between py-1 border-b border-border/30">
      <span className="text-text-secondary">{label}</span>
      <span>{value}</span>
    </div>
  );
}
