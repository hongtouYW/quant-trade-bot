import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { useLanguage } from '../../contexts/LanguageContext';
import { formatDateTime } from '../../utils/formatDate';
import { Inbox } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function Positions() {
  const { t } = useLanguage();
  const { data, loading } = useApi('/agent/trades/positions', { interval: 5000 });

  const columns = [
    { key: 'symbol', label: t('positions.symbol'), render: (v) => <span className="font-medium">{v}</span> },
    { key: 'direction', label: t('positions.dir'), render: (v) => (
      <Badge variant={v === 'LONG' ? 'success' : 'danger'}>{v}</Badge>
    )},
    { key: 'entry_price', label: t('positions.entry'), render: (v) => `$${Number(v).toFixed(6)}` },
    { key: 'amount', label: t('positions.amount'), render: (v) => `${v}U` },
    { key: 'leverage', label: t('positions.lev'), render: (v) => `${v ?? '-'}x` },
    { key: 'stop_loss', label: t('positions.sl'), render: (v) => v ? `$${Number(v).toFixed(4)}` : '-' },
    { key: 'take_profit', label: t('positions.tp'), render: (v) => v ? `$${Number(v).toFixed(4)}` : '-' },
    { key: 'score', label: t('positions.score') },
    { key: 'unrealized_pnl', label: t('positions.unrealizedPnl'), render: (v, row) => {
      if (v == null) return <span className="text-text-secondary">-</span>;
      return (
        <div className="text-right">
          <PnlValue value={v} />
          <div className="text-xs"><PnlValue value={row.current_roi} suffix="%" /></div>
        </div>
      );
    }},
    { key: 'peak_roi', label: t('positions.peakRoi'), render: (v) => v != null ? `${Number(v).toFixed(2)}%` : '-' },
    { key: 'entry_time', label: t('positions.opened'), render: (v) => formatDateTime(v) },
  ];

  const positions = data?.positions || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">{t('positions.title')}</h2>
        <span className="text-sm text-text-secondary">{positions.length} {t('positions.positions')}</span>
      </div>

      <Card>
        {!loading && positions.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-text-secondary">
            <Inbox size={40} className="mb-3 opacity-40" />
            <p className="text-sm mb-1">{t('positions.noPositions')}</p>
            <p className="text-xs">{t('positions.noPositionsDesc')}</p>
            <Link to="/agent/bot" className="mt-3 text-xs text-primary hover:underline">{t('positions.checkBot')}</Link>
          </div>
        ) : (
          <Table columns={columns} data={positions} emptyText={t('common.loading')} />
        )}
      </Card>
    </div>
  );
}
