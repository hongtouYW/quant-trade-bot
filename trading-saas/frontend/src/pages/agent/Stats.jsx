import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { StatCard, Card } from '../../components/common/Card';
import PnlValue from '../../components/common/PnlValue';
import { useLanguage } from '../../contexts/LanguageContext';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Cell } from 'recharts';
import { Target, TrendingUp, TrendingDown, Activity, ChevronLeft, ChevronRight } from 'lucide-react';

export default function Stats() {
  const { t } = useLanguage();
  const { data: stats, loading } = useApi('/agent/trades/stats');
  const { data: daily } = useApi('/agent/trades/daily?days=365');

  if (loading || !stats) {
    return <div className="animate-pulse text-text-secondary">{t('stats.loadingStats')}</div>;
  }

  const chartData = (daily?.daily || []).map((d) => ({
    ...d,
    fill: d.total_pnl >= 0 ? '#10b981' : '#ef4444',
  }));

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">{t('stats.title')}</h2>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label={t('stats.totalTrades')} value={stats.total_trades} icon={Activity} />
        <StatCard
          label={t('stats.winRate')}
          value={`${stats.win_rate}%`}
          sub={`${stats.win_trades}W / ${stats.loss_trades}L`}
          icon={Target}
          color="text-primary"
        />
        <StatCard
          label={t('stats.profitFactor')}
          value={stats.profit_factor}
          icon={TrendingUp}
          color={stats.profit_factor >= 1 ? 'text-success' : 'text-danger'}
        />
        <StatCard
          label={t('stats.maxDrawdown')}
          value={`${stats.max_drawdown}%`}
          icon={TrendingDown}
          color="text-danger"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <h3 className="text-sm font-semibold mb-3">{t('stats.summary')}</h3>
          <div className="space-y-2 text-sm">
            <Row label={t('stats.totalPnl')} value={<PnlValue value={stats.total_pnl} />} />
            <Row label={t('stats.currentCapital')} value={`${stats.current_capital}U`} />
            <Row label={t('stats.averagePnl')} value={<PnlValue value={stats.avg_pnl} />} />
            <Row label={t('stats.bestTrade')} value={<PnlValue value={stats.best_trade} />} />
            <Row label={t('stats.worstTrade')} value={<PnlValue value={stats.worst_trade} />} />
            <Row label={t('stats.totalFees')} value={`${stats.total_fees}U`} />
            <Row label={t('stats.openPositions')} value={stats.open_positions} />
          </div>
        </Card>

        {chartData.length > 0 && (
          <Card>
            <h3 className="text-sm font-semibold mb-3">{t('stats.dailyPnl')}</h3>
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

      {/* PnL Calendar */}
      <div className="w-[460px]">
        <PnlCalendar dailyData={daily?.daily || []} t={t} />
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

function PnlCalendar({ dailyData, t }) {
  const [currentMonth, setCurrentMonth] = useState(() => {
    const now = new Date();
    return { year: now.getFullYear(), month: now.getMonth() };
  });

  // Build lookup: { '2026-03-05': { total_pnl, trades_closed, win_trades, ... } }
  const pnlMap = {};
  (dailyData || []).forEach(d => { pnlMap[d.date] = d; });

  const { year, month } = currentMonth;
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Monday = 0, Sunday = 6
  let startDow = new Date(year, month, 1).getDay();
  startDow = startDow === 0 ? 6 : startDow - 1;

  const cells = [];
  for (let i = 0; i < startDow; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    cells.push({ day: d, date: dateStr, data: pnlMap[dateStr] || null });
  }

  // Month totals
  let monthPnl = 0, monthTrades = 0;
  cells.forEach(c => {
    if (c?.data) {
      monthPnl += c.data.total_pnl;
      monthTrades += c.data.trades_closed;
    }
  });

  const prevMonth = () => setCurrentMonth(p =>
    p.month === 0 ? { year: p.year - 1, month: 11 } : { ...p, month: p.month - 1 }
  );
  const nextMonth = () => setCurrentMonth(p =>
    p.month === 11 ? { year: p.year + 1, month: 0 } : { ...p, month: p.month + 1 }
  );

  const weekdays = [
    t('stats.mon'), t('stats.tue'), t('stats.wed'),
    t('stats.thu'), t('stats.fri'), t('stats.sat'), t('stats.sun')
  ];
  const monthNames = [
    t('stats.jan'), t('stats.feb'), t('stats.mar'), t('stats.apr'),
    t('stats.may'), t('stats.jun'), t('stats.jul'), t('stats.aug'),
    t('stats.sep'), t('stats.oct'), t('stats.nov'), t('stats.dec')
  ];

  return (
    <Card>
      <div className="flex items-center justify-between mb-2">
        <button onClick={prevMonth} className="p-1 hover:bg-white/5 rounded transition-colors">
          <ChevronLeft size={14} />
        </button>
        <div className="text-center">
          <h3 className="text-xs font-semibold">{monthNames[month]} {year}</h3>
          <p className="text-[10px] text-text-secondary">
            {monthTrades} {t('stats.tradesCount')} · <PnlValue value={monthPnl} className="text-[10px]" />
          </p>
        </div>
        <button onClick={nextMonth} className="p-1 hover:bg-white/5 rounded transition-colors">
          <ChevronRight size={14} />
        </button>
      </div>

      <div className="grid grid-cols-7 gap-0.5 mb-0.5">
        {weekdays.map(wd => (
          <div key={wd} className="text-center text-[10px] text-text-secondary py-0.5">{wd}</div>
        ))}
      </div>

      <div className="grid grid-cols-7 gap-0.5">
        {cells.map((cell, i) => {
          if (!cell) return <div key={`e-${i}`} className="py-3" />;
          const { day, data } = cell;
          const hasTrades = data && data.trades_closed > 0;
          const pnl = data?.total_pnl || 0;
          const bgClass = hasTrades
            ? (pnl >= 0 ? 'bg-emerald-500/20' : 'bg-red-500/20')
            : 'bg-white/[0.03]';

          return (
            <div
              key={day}
              className={`rounded py-1 ${bgClass} flex flex-col items-center justify-center relative group`}
            >
              <span className="text-[10px] text-text-secondary">{day}</span>
              {hasTrades && (
                <span className={`text-[10px] font-mono leading-tight ${pnl >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                  {pnl >= 0 ? '+' : ''}{pnl.toFixed(1)}
                </span>
              )}
              {hasTrades && (
                <div className="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-bg-card border border-border rounded-lg p-2 text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 shadow-lg">
                  <div className="font-medium mb-1">{cell.date}</div>
                  <div>{data.trades_closed} {t('stats.tradesCount')} ({data.win_trades}W / {data.trades_closed - data.win_trades}L)</div>
                  <PnlValue value={pnl} className="text-xs" />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </Card>
  );
}
