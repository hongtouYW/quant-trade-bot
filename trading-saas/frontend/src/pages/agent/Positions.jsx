import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { Inbox } from 'lucide-react';
import { Link } from 'react-router-dom';

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
        {!loading && positions.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-text-secondary">
            <Inbox size={40} className="mb-3 opacity-40" />
            <p className="text-sm mb-1">No open positions</p>
            <p className="text-xs">When the bot finds trading signals, positions will appear here.</p>
            <Link to="/agent/bot" className="mt-3 text-xs text-primary hover:underline">Check Bot Status →</Link>
          </div>
        ) : (
          <Table columns={columns} data={positions} emptyText="Loading..." />
        )}
      </Card>
    </div>
  );
}
