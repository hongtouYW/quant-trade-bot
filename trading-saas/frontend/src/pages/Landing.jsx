import { useNavigate } from 'react-router-dom';
import { TrendingUp, Shield, Zap, BarChart3, ArrowRight, Trophy, Target, Lock } from 'lucide-react';

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

  return (
    <div className="min-h-screen bg-slate-950 text-white">
      {/* Nav */}
      <nav className="border-b border-slate-800 px-6 py-4">
        <div className="max-w-6xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-2">
            <TrendingUp className="w-6 h-6 text-blue-400" />
            <span className="text-lg font-bold">Trading SaaS</span>
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => navigate('/agent/login')}
              className="px-4 py-2 text-sm text-slate-300 hover:text-white transition"
            >
              Login
            </button>
            <button
              onClick={() => navigate('/agent/register')}
              className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-500 rounded-lg transition"
            >
              Get Started
            </button>
          </div>
        </div>
      </nav>

      {/* Hero */}
      <section className="px-6 py-20">
        <div className="max-w-4xl mx-auto text-center">
          <h1 className="text-4xl sm:text-5xl font-bold mb-6 leading-tight">
            AI-Powered Crypto Futures Trading
          </h1>
          <p className="text-xl text-slate-400 mb-8 max-w-2xl mx-auto">
            Automated trading across 150 crypto pairs with proven backtested strategies.
            You only pay when we profit.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              onClick={() => navigate('/agent/register')}
              className="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-lg font-semibold text-lg transition flex items-center justify-center gap-2"
            >
              Start Trading <ArrowRight className="w-5 h-5" />
            </button>
            <a
              href="#performance"
              className="px-8 py-3 border border-slate-600 hover:border-slate-500 rounded-lg font-semibold text-lg transition text-slate-300 text-center"
            >
              View Performance
            </a>
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="px-6 py-12 bg-slate-900/50">
        <div className="max-w-5xl mx-auto grid grid-cols-2 sm:grid-cols-4 gap-4">
          <StatCard icon={BarChart3} label="Total Trades" value={STATS.totalTrades} sub="in 30 days" />
          <StatCard icon={Target} label="Win Rate" value={`${STATS.winRate}%`} sub="232 wins / 141 losses" />
          <StatCard icon={TrendingUp} label="Total PnL" value={`+${STATS.totalPnl} U`} sub="Paper trading verified" />
          <StatCard icon={Zap} label="Monitored Coins" value={STATS.coins} sub="24/7 scanning" />
        </div>
      </section>

      {/* Performance */}
      <section id="performance" className="px-6 py-16">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-12">Verified Performance</h2>

          {/* Monthly table */}
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-8">
            <div className="px-6 py-4 border-b border-slate-700">
              <h3 className="text-lg font-semibold">Monthly Results</h3>
              <p className="text-sm text-slate-400">Paper trading data from Jan 27 - Feb 26, 2026</p>
            </div>
            <table className="w-full">
              <thead>
                <tr className="text-xs text-slate-400 uppercase border-b border-slate-700">
                  <th className="px-6 py-3 text-left">Month</th>
                  <th className="px-6 py-3 text-right">Trades</th>
                  <th className="px-6 py-3 text-right">Wins</th>
                  <th className="px-6 py-3 text-right">Win Rate</th>
                  <th className="px-6 py-3 text-right">PnL (USDT)</th>
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
                  <td className="px-6 py-3 font-bold">Total</td>
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
                <Trophy className="w-5 h-5 text-yellow-400" /> Top 5 Trades
              </h3>
            </div>
            <table className="w-full">
              <thead>
                <tr className="text-xs text-slate-400 uppercase border-b border-slate-700">
                  <th className="px-6 py-3 text-left">Pair</th>
                  <th className="px-6 py-3 text-center">Direction</th>
                  <th className="px-6 py-3 text-right">PnL (USDT)</th>
                  <th className="px-6 py-3 text-right">ROI</th>
                </tr>
              </thead>
              <tbody>
                {TOP_TRADES.map((t, i) => (
                  <tr key={i} className="border-b border-slate-700/50">
                    <td className="px-6 py-3 font-medium">{t.symbol}/USDT</td>
                    <td className="px-6 py-3 text-center">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                        t.dir === 'LONG' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                      }`}>
                        {t.dir}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-right font-semibold text-green-400">+{t.pnl}</td>
                    <td className="px-6 py-3 text-right text-blue-400">{t.roi}%</td>
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
          <h2 className="text-3xl font-bold text-center mb-12">Security First</h2>
          <div className="grid sm:grid-cols-3 gap-6">
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <Lock className="w-8 h-8 text-blue-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">API Key Safety</h3>
              <p className="text-sm text-slate-400">
                Your Binance API Key only needs <strong className="text-slate-200">trading permission</strong> — no withdrawal access.
                Keys are encrypted with AES-256-GCM before storage.
              </p>
            </div>
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <Shield className="w-8 h-8 text-green-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">5-Layer Risk Control</h3>
              <p className="text-sm text-slate-400">
                Daily loss limits, max drawdown protection, position limits,
                leverage caps, and real-time risk scoring (0-10).
              </p>
            </div>
            <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
              <BarChart3 className="w-8 h-8 text-purple-400 mb-4" />
              <h3 className="text-lg font-semibold mb-2">Transparent Billing</h3>
              <p className="text-sm text-slate-400">
                High-water mark profit sharing — you only pay commission on
                <strong className="text-slate-200"> new profits above your peak</strong>. Loss periods? Zero fees.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* High Water Mark explanation */}
      <section className="px-6 py-16">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-8">How Billing Works</h2>
          <p className="text-center text-slate-400 mb-10">
            We use the <strong className="text-white">High-Water Mark</strong> method —
            you only pay for profits that exceed your all-time peak.
          </p>
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6 font-mono text-sm space-y-3">
            <div className="flex justify-between">
              <span className="text-slate-400">Month 1: 2000U {'→'} 2500U</span>
              <span className="text-green-400">Profit 500U, Commission 100U (20%)</span>
            </div>
            <div className="border-t border-slate-700 pt-3 flex justify-between">
              <span className="text-slate-400">Month 2: 2500U {'→'} 2300U (loss)</span>
              <span className="text-yellow-400">Commission: 0U</span>
            </div>
            <div className="border-t border-slate-700 pt-3 flex justify-between">
              <span className="text-slate-400">Month 3: 2300U {'→'} 2700U</span>
              <span className="text-green-400">New profit above HWM: 200U, Commission 40U</span>
            </div>
          </div>
        </div>
      </section>

      {/* How it works */}
      <section className="px-6 py-16 bg-slate-900/50">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl font-bold text-center mb-12">Get Started in 3 Steps</h2>
          <div className="space-y-8">
            <StepCard
              num={1}
              title="Create Account"
              desc="Register with your email. Admin will review and activate your trading access."
            />
            <StepCard
              num={2}
              title="Connect Binance API"
              desc="Create a Binance API Key with Futures trading permission and IP whitelist. Paste it in Settings."
            />
            <StepCard
              num={3}
              title="Start Your Bot"
              desc="Click Start Bot and the AI scans 150 coins every 60 seconds, opening positions when signals are strong enough."
            />
          </div>
          <div className="text-center mt-12">
            <button
              onClick={() => navigate('/agent/register')}
              className="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-lg font-semibold text-lg transition inline-flex items-center gap-2"
            >
              Create Free Account <ArrowRight className="w-5 h-5" />
            </button>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-800 px-6 py-8">
        <div className="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-slate-400">
            <TrendingUp className="w-5 h-5" />
            <span className="text-sm">Trading SaaS &copy; 2026</span>
          </div>
          <div className="flex gap-6 text-sm text-slate-500">
            <button onClick={() => navigate('/agent/login')} className="hover:text-slate-300 transition">
              Agent Login
            </button>
            <button onClick={() => navigate('/admin/login')} className="hover:text-slate-300 transition">
              Admin Login
            </button>
          </div>
        </div>
      </footer>
    </div>
  );
}
