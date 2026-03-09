import { useNavigate } from 'react-router-dom';
import { TrendingUp, Shield, Zap, BarChart3, ArrowRight, Trophy, Target, Lock } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';

const STATS = {
  totalTrades: 373,
  winRate: 62.2,
  totalPnl: 480.42,
  runDays: 30,
  bestTrade: 282.12,
  coins: 150,
};

const MONTHLY = [
  { month: '2026-01', trades: 33, wins: 16, pnl: 546.65, winRate: 48.5 },
  { month: '2026-02', trades: 340, wins: 216, pnl: -66.23, winRate: 63.5 },
];

const TOP_TRADES = [
  { symbol: 'XRP', dir: 'SHORT', pnl: 282.12, roi: 58.28 },
  { symbol: 'SOL', dir: 'SHORT', pnl: 250.24, roi: 57.47 },
  { symbol: 'AXS', dir: 'SHORT', pnl: 104.16, roi: 84.88 },
  { symbol: 'ROSE', dir: 'LONG', pnl: 84.82, roi: 101.78 },
  { symbol: 'ATOM', dir: 'SHORT', pnl: 44.23, roi: 44.66 },
];

function StatCard({ icon: Icon, label, value, sub }) {
  return (
    <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6 text-center">
      <Icon className="w-8 h-8 text-blue-400 mx-auto mb-3" />
      <div className="text-3xl font-bold text-white mb-1">{value}</div>
      <div className="text-sm text-slate-400">{label}</div>
      {sub && <div className="text-xs text-slate-500 mt-1">{sub}</div>}
    </div>
  );
}

function StepCard({ num, title, desc }) {
  return (
    <div className="flex items-start gap-4">
      <div className="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-500/40 flex items-center justify-center text-blue-400 font-bold shrink-0">
        {num}
      </div>
      <div>
        <h4 className="text-white font-semibold mb-1">{title}</h4>
        <p className="text-slate-400 text-sm">{desc}</p>
      </div>
    </div>
  );
}

export default function Landing() {
  const navigate = useNavigate();
  const { t } = useLanguage();

  return (
    <div className="min-h-screen bg-slate-950 text-white">
      {/* Nav */}
      <nav className="border-b border-slate-800 px-6 py-4">
        <div className="max-w-6xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-2">
            <TrendingUp className="w-6 h-6 text-blue-400" />
            <span className="text-lg font-bold">{t('nav.brand')}</span>
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => navigate('/agent/login')}
              className="px-4 py-2 text-sm text-slate-300 hover:text-white transition"
            >
              {t('landing.login')}
            </button>
            <button
              onClick={() => navigate('/agent/register')}
              className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-500 rounded-lg transition"
            >
              {t('landing.getStarted')}
            </button>
          </div>
        </div>
      </nav>

      {/* Hero */}
      <section className="px-6 py-20">
        <div className="max-w-4xl mx-auto text-center">
          <h1 className="text-4xl sm:text-5xl font-bold mb-6 leading-tight">
            {t('landing.heroTitle')}
          </h1>
          <p className="text-xl text-slate-400 mb-8 max-w-2xl mx-auto">
            {t('landing.heroDesc')}
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              onClick={() => navigate('/agent/register')}
              className="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-lg font-semibold text-lg transition flex items-center justify-center gap-2"
            >
              {t('landing.startTrading')} <ArrowRight className="w-5 h-5" />
            </button>
            <a
              href="#performance"
              className="px-8 py-3 border border-slate-600 hover:border-slate-500 rounded-lg font-semibold text-lg transition text-slate-300 text-center"
            >
              {t('landing.viewPerformance')}
            </a>
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="px-6 py-12 bg-slate-900/50">
        <div className="max-w-5xl mx-auto grid grid-cols-2 sm:grid-cols-4 gap-4">
          <StatCard icon={BarChart3} label={t('landing.totalTrades')} value={STATS.totalTrades} sub={t('landing.in30Days')} />
          <StatCard icon={Target} label={t('dashboard.winRate')} value={`${STATS.winRate}%`} sub={t('landing.winsLosses')} />
          <StatCard icon={TrendingUp} label={t('landing.totalPnl')} value={`+${STATS.totalPnl} U`} sub={t('landing.paperVerified')} />
          <StatCard icon={Zap} label={t('landing.monitoredCoins')} value={STATS.coins} sub={t('landing.scanning247')} />
        </div>
      </section>

      {/* Performance */}
      <section id="performance" className="px-6 py-16">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-12">{t('landing.verifiedPerformance')}</h2>

          {/* Monthly table */}
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-8">
            <div className="px-6 py-4 border-b border-slate-700">
              <h3 className="text-lg font-semibold">{t('landing.monthlyResults')}</h3>
              <p className="text-sm text-slate-400">{t('landing.monthlyResultsDesc')}</p>
            </div>
            <table className="w-full">
              <thead>
                <tr className="text-xs text-slate-400 uppercase border-b border-slate-700">
                  <th className="px-6 py-3 text-left">{t('landing.month')}</th>
                  <th className="px-6 py-3 text-right">{t('landing.trades')}</th>
                  <th className="px-6 py-3 text-right">{t('landing.wins')}</th>
                  <th className="px-6 py-3 text-right">{t('dashboard.winRate')}</th>
                  <th className="px-6 py-3 text-right">{t('landing.pnlUsdt')}</th>
                </tr>
              </thead>
              <tbody>
                {MONTHLY.map((m) => (
                  <tr key={m.month} className="border-b border-slate-700/50">
                    <td className="px-6 py-3 font-medium">{m.month}</td>
                    <td className="px-6 py-3 text-right">{m.trades}</td>
                    <td className="px-6 py-3 text-right">{m.wins}</td>
                    <td className="px-6 py-3 text-right">{m.winRate}%</td>
                    <td className={`px-6 py-3 text-right font-semibold ${m.pnl >= 0 ? 'text-green-400' : 'text-red-400'}`}>
                      {m.pnl >= 0 ? '+' : ''}{m.pnl}
                    </td>
                  </tr>
                ))}
                <tr className="bg-slate-800">
                  <td className="px-6 py-3 font-bold">{t('landing.total')}</td>
                  <td className="px-6 py-3 text-right font-bold">{STATS.totalTrades}</td>
                  <td className="px-6 py-3 text-right font-bold">232</td>
                  <td className="px-6 py-3 text-right font-bold">{STATS.winRate}%</td>
                  <td className="px-6 py-3 text-right font-bold text-green-400">+{STATS.totalPnl}</td>
                </tr>
              </tbody>
            </table>
          </div>

          {/* Top trades */}
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-700">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Trophy className="w-5 h-5 text-yellow-400" /> {t('landing.top5Trades')}
              </h3>
            </div>
            <table className="w-full">
              <thead>
                <tr className="text-xs text-slate-400 uppercase border-b border-slate-700">
                  <th className="px-6 py-3 text-left">{t('landing.pair')}</th>
                  <th className="px-6 py-3 text-center">{t('landing.direction')}</th>
                  <th className="px-6 py-3 text-right">{t('landing.pnlUsdt')}</th>
                  <th className="px-6 py-3 text-right">ROI</th>
                </tr>
              </thead>
              <tbody>
                {TOP_TRADES.map((tr, i) => (
                  <tr key={i} className="border-b border-slate-700/50">
                    <td className="px-6 py-3 font-medium">{tr.symbol}/USDT</td>
                    <td className="px-6 py-3 text-center">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                        tr.dir === 'LONG' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                      }`}>
                        {tr.dir}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-right font-semibold text-green-400">+{tr.pnl}</td>
                    <td className="px-6 py-3 text-right text-blue-400">{tr.roi}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* Trust & Security */}
      <section className="px-6 py-16 bg-slate-900/50">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-12">{t('landing.securityFirst')}</h2>
          <div className="grid sm:grid-cols-3 gap-6">
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <Lock className="w-8 h-8 text-blue-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">{t('landing.apiKeySafety')}</h3>
              <p className="text-sm text-slate-400">
                {t('landing.apiKeySafetyDesc')}
              </p>
            </div>
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <Shield className="w-8 h-8 text-green-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">{t('landing.riskControl')}</h3>
              <p className="text-sm text-slate-400">
                {t('landing.riskControlDesc')}
              </p>
            </div>
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <BarChart3 className="w-8 h-8 text-purple-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">{t('landing.transparentBilling')}</h3>
              <p className="text-sm text-slate-400">
                {t('landing.transparentBillingDesc')}
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* High Water Mark explanation */}
      <section className="px-6 py-16">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-8">{t('landing.howBillingWorks')}</h2>
          <p className="text-center text-slate-400 mb-10">
            {t('landing.hwmExplanation')}
          </p>
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6 font-mono text-sm space-y-3">
            <div className="flex justify-between">
              <span className="text-slate-400">{t('landing.hwmMonth1')}</span>
              <span className="text-green-400">{t('landing.hwmMonth1Result')}</span>
            </div>
            <div className="border-t border-slate-700 pt-3 flex justify-between">
              <span className="text-slate-400">{t('landing.hwmMonth2')}</span>
              <span className="text-yellow-400">{t('landing.hwmMonth2Result')}</span>
            </div>
            <div className="border-t border-slate-700 pt-3 flex justify-between">
              <span className="text-slate-400">{t('landing.hwmMonth3')}</span>
              <span className="text-green-400">{t('landing.hwmMonth3Result')}</span>
            </div>
          </div>
        </div>
      </section>

      {/* How it works */}
      <section className="px-6 py-16 bg-slate-900/50">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-12">{t('landing.getStartedSteps')}</h2>
          <div className="space-y-8">
            <StepCard
              num={1}
              title={t('landing.step1Title')}
              desc={t('landing.step1Desc')}
            />
            <StepCard
              num={2}
              title={t('landing.step2Title')}
              desc={t('landing.step2Desc')}
            />
            <StepCard
              num={3}
              title={t('landing.step3Title')}
              desc={t('landing.step3Desc')}
            />
          </div>
          <div className="text-center mt-12">
            <button
              onClick={() => navigate('/agent/register')}
              className="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-lg font-semibold text-lg transition inline-flex items-center gap-2"
            >
              {t('landing.createFreeAccount')} <ArrowRight className="w-5 h-5" />
            </button>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-800 px-6 py-8">
        <div className="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-slate-400">
            <TrendingUp className="w-5 h-5" />
            <span className="text-sm">{t('nav.brand')} &copy; 2026</span>
          </div>
          <div className="flex gap-6 text-sm text-slate-500">
            <button onClick={() => navigate('/agent/login')} className="hover:text-slate-300 transition">
              {t('landing.agentLogin')}
            </button>
            <button onClick={() => navigate('/admin/login')} className="hover:text-slate-300 transition">
              {t('landing.adminLogin')}
            </button>
          </div>
        </div>
      </footer>
    </div>
  );
}
