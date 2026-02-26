import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import { History as HistoryIcon, Download } from 'lucide-react';

export default function History() {
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ symbol: '', direction: '' });
  const params = new URLSearchParams({ page, per_page: 20 });
  if (filters.symbol) params.set('symbol', filters.symbol);
  if (filters.direction) params.set('direction', filters.direction);

  const { data, loading } = useApi(`/agent/trades/history?${params}`);
  const { data: symbolsData } = useApi('/agent/trades/symbols');

  const columns = [
    { key: 'symbol', label: 'Symbol', render: (v) => <span className="font-medium">{v}</span> },
    { key: 'direction', label: 'Dir', render: (v) => (
      <Badge variant={v === 'LONG' ? 'success' : 'danger'}>{v}</Badge>
    )},
    { key: 'entry_price', label: 'Entry', render: (v) => `$${Number(v).toFixed(6)}` },
    { key: 'exit_price', label: 'Exit', render: (v) => v ? `$${Number(v).toFixed(6)}` : '-' },
    { key: 'amount', label: 'Amount', render: (v) => `${v}U` },
    { key: 'leverage', label: 'Lev', render: (v) => `${v}x` },
    { key: 'pnl', label: 'PnL', render: (v) => <PnlValue value={v} /> },
    { key: 'roi', label: 'ROI', render: (v) => (
      <PnlValue value={v} suffix="%" />
    )},
    { key: 'close_reason', label: 'Reason', render: (v) => (
      <span className="text-xs text-text-secondary">{v || '-'}</span>
    )},
    { key: 'exit_time', label: 'Closed', render: (v) =>
      v ? new Date(v).toLocaleString() : '-'
    },
  ];

  const symbols = symbolsData?.symbols || [];

  const handleExportCSV = () => {
    const csvParams = new URLSearchParams();
    if (filters.symbol) csvParams.set('symbol', filters.symbol);
    if (filters.direction) csvParams.set('direction', filters.direction);
    const token = localStorage.getItem('token');
    const base = import.meta.env.VITE_API_BASE || '/api';
    const url = `${base}/agent/trades/export/csv?${csvParams}`;
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then((res) => res.blob())
      .then((blob) => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `trades_${new Date().toISOString().slice(0, 10).replace(/-/g, '')}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
      });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">Trade History</h2>
        {data?.trades?.length > 0 && (
          <button
            onClick={handleExportCSV}
            className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-bg-input hover:bg-border rounded-lg border border-border text-text transition-colors"
          >
            <Download size={14} />
            Export CSV
          </button>
        )}
      </div>

      {/* Filters */}
      <Card className="flex flex-wrap items-center gap-3">
        <select
          value={filters.symbol}
          onChange={(e) => { setFilters({ ...filters, symbol: e.target.value }); setPage(1); }}
          className="px-3 py-1.5 bg-bg-input rounded-lg border border-border text-text text-sm"
        >
          <option value="">All Symbols</option>
          {symbols.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select
          value={filters.direction}
          onChange={(e) => { setFilters({ ...filters, direction: e.target.value }); setPage(1); }}
          className="px-3 py-1.5 bg-bg-input rounded-lg border border-border text-text text-sm"
        >
          <option value="">All Directions</option>
          <option value="LONG">LONG</option>
          <option value="SHORT">SHORT</option>
        </select>
        <span className="text-xs text-text-secondary ml-auto">
          {data?.total || 0} trades total
        </span>
      </Card>

      <Card>
        {!loading && (!data?.trades || data.trades.length === 0) ? (
          <div className="flex flex-col items-center py-12 text-text-secondary">
            <HistoryIcon size={40} className="mb-3 opacity-40" />
            <p className="text-sm mb-1">No trade history yet</p>
            <p className="text-xs">Closed trades will appear here once the bot starts trading.</p>
          </div>
        ) : (
          <Table columns={columns} data={data?.trades || []} emptyText="Loading..." />
        )}

        {/* Pagination */}
        {data && data.pages > 1 && (
          <div className="flex items-center justify-center gap-2 mt-4 pt-4 border-t border-border/50">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page <= 1}
              className="px-3 py-1 text-sm rounded bg-bg-input text-text disabled:opacity-30"
            >
              Prev
            </button>
            <span className="text-sm text-text-secondary">
              {page} / {data.pages}
            </span>
            <button
              onClick={() => setPage(Math.min(data.pages, page + 1))}
              disabled={page >= data.pages}
              className="px-3 py-1 text-sm rounded bg-bg-input text-text disabled:opacity-30"
            >
              Next
            </button>
          </div>
        )}
      </Card>
    </div>
  );
}
