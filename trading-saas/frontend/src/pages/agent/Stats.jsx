import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import PnlValue from '../../components/common/PnlValue';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Cell } from 'recharts';
import { Target, TrendingUp, TrendingDown, Activity } from 'lucide-react';

export default function Stats() {
  const { data: stats, loading } = useApi('/agent/trades/stats');
  const { data: daily } = useApi('/agent/trades/daily?days=30');

  if (loading || !stats) {
    return <div className="animate-pulse text-text-secondary">Loading statistics...</div>;
  }

  const chartData = (daily?.daily || []).map((d) => ({
    ...d,
    fill: d.total_pnl >= 0 ? '#10b981' : '#ef4444',
  }));

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Trading Statistics</h2>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Trades" value={stats.total_trades} icon={Activity} />
        <StatCard
          label="Win Rate"
          value={`${stats.win_rate}%`}
          sub={`${stats.win_trades}W / ${stats.loss_trades}L`}
          icon={Target}
          color="text-primary"
        />
        <StatCard
          label="Profit Factor"
          value={stats.profit_factor}
          icon={TrendingUp}
          color={stats.profit_factor >= 1 ? 'text-success' : 'text-danger'}
        />
        <StatCard
          label="Max Drawdown"
          value={`${stats.max_drawdown}%`}
          icon={TrendingDown}
          color="text-danger"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <h3 className="text-sm font-semibold mb-3">Summary</h3>
          <div className="space-y-2 text-sm">
            <Row label="Total PnL" value={<PnlValue value={stats.total_pnl} />} />
            <Row label="Current Capital" value={`${stats.current_capital}U`} />
            <Row label="Average PnL" value={<PnlValue value={stats.avg_pnl} />} />
            <Row label="Best Trade" value={<PnlValue value={stats.best_trade} />} />
            <Row label="Worst Trade" value={<PnlValue value={stats.worst_trade} />} />
            <Row label="Total Fees" value={`${stats.total_fees}U`} />
            <Row label="Open Positions" value={stats.open_positions} />
          </div>
        </Card>

        {chartData.length > 0 && (
          <Card>
            <h3 className="text-sm font-semibold mb-3">Daily PnL</h3>
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                  <XAxis dataKey="date" tick={{ fill: '#94a3b8', fontSize: 10 }} />
                  <YAxis tick={{ fill: '#94a3b8', fontSize: 11 }} />
                  <Tooltip
                    contentStyle={{ background: '#1e293b', border: '1px solid #334155', borderRadius: 8 }}
                    labelStyle={{ color: '#94a3b8' }}
                  />
                  <Bar dataKey="total_pnl" name="PnL" radius={[3, 3, 0, 0]}>
                    {chartData.map((entry, i) => (
                      <Cell key={i} fill={entry.fill} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        )}
      </div>
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
