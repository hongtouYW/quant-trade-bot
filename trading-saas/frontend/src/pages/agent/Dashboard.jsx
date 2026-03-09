import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import PnlValue from '../../components/common/PnlValue';
import { StatusBadge } from '../../components/common/Badge';
import SetupChecklist from '../../components/agent/SetupChecklist';
import { useLanguage } from '../../contexts/LanguageContext';
import { formatDateTime } from '../../utils/formatDate';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { DollarSign, TrendingUp, BarChart3, Activity, Wallet } from 'lucide-react';

export default function AgentDashboard() {
  const { t } = useLanguage();
  const { data, loading } = useApi('/agent/dashboard', { interval: 15000 });
  const { data: daily } = useApi('/agent/trades/daily?days=30');
  const { data: stats } = useApi('/agent/trades/stats');
  const { data: bot } = useApi('/agent/bot/status', { interval: 5000 });
  const { data: balanceData } = useApi('/agent/balance');

  if (loading || !data) {
    return <div className="animate-pulse text-text-secondary">{t('dashboard.loadingDashboard')}</div>;
  }

  const chartData = daily?.daily || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">{t('dashboard.title')}</h2>
        {bot && <StatusBadge status={bot.status} />}
      </div>

      <SetupChecklist />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label={t('dashboard.binanceBalance')}
          value={balanceData?.total != null ? `${balanceData.total.toFixed(2)}U` : `${stats?.current_capital?.toFixed(2) || '-'}U`}
          sub={balanceData?.free != null ? `${t('dashboard.available')}: ${balanceData.free.toFixed(2)}U` : undefined}
          icon={Wallet}
        />
        <StatCard
          label={t('dashboard.totalPnl')}
          value={<PnlValue value={stats?.total_pnl} />}
          icon={TrendingUp}
          color={stats?.total_pnl >= 0 ? 'text-success' : 'text-danger'}
        />
        <StatCard
          label={t('dashboard.winRate')}
          value={`${stats?.win_rate || 0}%`}
          sub={`${stats?.win_trades || 0}W / ${stats?.loss_trades || 0}L`}
          icon={BarChart3}
        />
        <StatCard
          label={t('dashboard.openPositions')}
          value={stats?.open_positions || 0}
          sub={`${t('dashboard.maxDD')}: ${stats?.max_drawdown || 0}%`}
          icon={Activity}
        />
      </div>

      {chartData.length > 0 && (
        <Card>
          <h3 className="text-sm font-semibold mb-3">{t('dashboard.pnlTrend')}</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                <XAxis dataKey="date" tick={{ fill: '#94a3b8', fontSize: 11 }} />
                <YAxis tick={{ fill: '#94a3b8', fontSize: 11 }} />
                <Tooltip
                  contentStyle={{ background: '#1e293b', border: '1px solid #334155', borderRadius: 8 }}
                  labelStyle={{ color: '#94a3b8' }}
                />
                <Line
                  type="monotone"
                  dataKey="cumulative_pnl"
                  stroke="#3b82f6"
                  strokeWidth={2}
                  dot={false}
                  name={t('dashboard.cumulativePnl')}
                />
                <Line
                  type="monotone"
                  dataKey="total_pnl"
                  stroke="#10b981"
                  strokeWidth={1}
                  dot={false}
                  name={t('dashboard.dailyPnl')}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </Card>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <h3 className="text-sm font-semibold mb-2">{t('dashboard.performance')}</h3>
          <div className="space-y-2 text-sm">
            <Row label={t('dashboard.totalTrades')} value={stats?.total_trades || 0} />
            <Row label={t('dashboard.avgPnl')} value={<PnlValue value={stats?.avg_pnl} />} />
            <Row label={t('dashboard.bestTrade')} value={<PnlValue value={stats?.best_trade} />} />
            <Row label={t('dashboard.worstTrade')} value={<PnlValue value={stats?.worst_trade} />} />
            <Row label={t('dashboard.profitFactor')} value={stats?.profit_factor || '-'} />
            <Row label={t('dashboard.totalFees')} value={`${stats?.total_fees?.toFixed(4) || 0}U`} />
          </div>
        </Card>

        <Card>
          <h3 className="text-sm font-semibold mb-2">{t('dashboard.botInfo')}</h3>
          <div className="space-y-2 text-sm">
            <Row label={t('dashboard.status')} value={<StatusBadge status={bot?.status || 'stopped'} />} />
            <Row label={t('dashboard.riskScore')} value={
              <span className={bot?.risk_score >= 7 ? 'text-danger' : bot?.risk_score >= 4 ? 'text-warning' : 'text-success'}>
                {bot?.risk_score ?? '-'}/10
              </span>
            } />
            <Row label={t('dashboard.lastScan')} value={bot?.last_scan ? formatDateTime(bot.last_scan) : '-'} />
            <Row label={t('dashboard.strategy')} value={data.strategy_version || 'v4.2'} />
          </div>
        </Card>
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
