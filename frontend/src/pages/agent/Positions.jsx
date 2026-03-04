import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';

export default function Positions() {
  const { data, loading } = useApi('/agent/trades/positions', { interval: 5000 });

  const columns = [
    { key: 'symbol', label: 'Symbol', render: (v) => <span className="font-medium">{v}</span> },
    { key: 'direction', label: 'Dir', render: (v) => (
      <Badge variant={v === 'LONG' ? 'success' : 'danger'}>{v}</Badge>
    )},
    { key: 'entry_price', label: 'Entry', render: (v) => `$${Number(v).toFixed(6)}` },
    { key: 'amount', label: 'Amount', render: (v) => `${v}U` },
    { key: 'leverage', label: 'Lev', render: (v) => `${v}x` },
    { key: 'stop_loss', label: 'SL', render: (v) => v ? `$${Number(v).toFixed(4)}` : '-' },
    { key: 'take_profit', label: 'TP', render: (v) => v ? `$${Number(v).toFixed(4)}` : '-' },
    { key: 'score', label: 'Score' },
    { key: 'peak_roi', label: 'Peak ROI', render: (v) => v != null ? `${Number(v).toFixed(2)}%` : '-' },
    { key: 'entry_time', label: 'Opened', render: (v) => new Date(v).toLocaleString() },
  ];

  const positions = data?.positions || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">Open Positions</h2>
        <span className="text-sm text-text-secondary">{positions.length} position(s)</span>
      </div>

      <Card>
        <Table columns={columns} data={positions} emptyText={loading ? 'Loading...' : 'No open positions'} />
      </Card>
    </div>
  );
}
