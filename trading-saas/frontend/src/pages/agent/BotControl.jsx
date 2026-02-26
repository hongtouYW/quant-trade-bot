import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import api from '../../api/client';
import { Play, Square, Pause, PlayCircle, ScrollText } from 'lucide-react';

const levelColors = {
  trade: 'text-primary',
  signal: 'text-blue-400',
  warn: 'text-warning',
  error: 'text-danger',
  info: 'text-text-secondary',
};

export default function BotControl() {
  const { data: bot, loading, refetch } = useApi('/agent/bot/status', { interval: 3000 });
  const { data: logsData } = useApi('/agent/bot/logs', { interval: 5000 });

  const action = async (act) => {
    try {
      await api.post(`/agent/bot/${act}`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.error || `Failed to ${act}`);
    }
  };

  if (loading) {
    return <div className="animate-pulse text-text-secondary">Loading bot status...</div>;
  }

  const status = bot?.status || 'stopped';

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Bot Control</h2>

      <Card className="flex flex-col items-center py-10">
        <div className={`w-20 h-20 rounded-full flex items-center justify-center mb-4 ${
          status === 'running' ? 'bg-success/15' :
          status === 'paused' ? 'bg-warning/15' :
          status === 'error' ? 'bg-danger/15' : 'bg-white/5'
        }`}>
          <div className={`w-5 h-5 rounded-full ${
            status === 'running' ? 'bg-success animate-pulse' :
            status === 'paused' ? 'bg-warning' :
            status === 'error' ? 'bg-danger' : 'bg-text-secondary'
          }`} />
        </div>

        <StatusBadge status={status} />

        <div className="flex items-center gap-3 mt-6">
          {status === 'stopped' || status === 'error' ? (
            <button
              onClick={() => action('start')}
              className="flex items-center gap-2 px-6 py-2.5 bg-success hover:bg-success/80 text-white rounded-lg text-sm font-medium transition-colors"
            >
              <Play size={16} /> Start Bot
            </button>
          ) : status === 'running' ? (
            <>
              <button
                onClick={() => action('pause')}
                className="flex items-center gap-2 px-4 py-2.5 bg-warning hover:bg-warning/80 text-black rounded-lg text-sm font-medium transition-colors"
              >
                <Pause size={16} /> Pause
              </button>
              <button
                onClick={() => action('stop')}
                className="flex items-center gap-2 px-4 py-2.5 bg-danger hover:bg-danger/80 text-white rounded-lg text-sm font-medium transition-colors"
              >
                <Square size={16} /> Stop
              </button>
            </>
          ) : status === 'paused' ? (
            <>
              <button
                onClick={() => action('resume')}
                className="flex items-center gap-2 px-4 py-2.5 bg-success hover:bg-success/80 text-white rounded-lg text-sm font-medium transition-colors"
              >
                <PlayCircle size={16} /> Resume
              </button>
              <button
                onClick={() => action('stop')}
                className="flex items-center gap-2 px-4 py-2.5 bg-danger hover:bg-danger/80 text-white rounded-lg text-sm font-medium transition-colors"
              >
                <Square size={16} /> Stop
              </button>
            </>
          ) : null}
        </div>
      </Card>

      <Card>
        <h3 className="text-sm font-semibold mb-3">Details</h3>
        <div className="space-y-2 text-sm">
          <Row label="Risk Score" value={
            <span className={
              (bot?.risk_score || 0) >= 7 ? 'text-danger' :
              (bot?.risk_score || 0) >= 4 ? 'text-warning' : 'text-success'
            }>
              {bot?.risk_score ?? '-'}/10
            </span>
          } />
          <Row label="Open Positions" value={bot?.open_positions ?? '-'} />
          <Row label="Last Scan" value={bot?.last_scan ? new Date(bot.last_scan).toLocaleString() : 'Never'} />
        </div>
      </Card>

      <Card>
        <div className="flex items-center gap-2 mb-3">
          <ScrollText size={16} className="text-text-secondary" />
          <h3 className="text-sm font-semibold">Activity Log</h3>
          <span className="text-xs text-text-secondary ml-auto">
            {logsData?.logs?.length || 0} entries (last 100)
          </span>
        </div>
        {logsData?.logs?.length > 0 ? (
          <div className="max-h-72 overflow-y-auto space-y-0.5 font-mono text-xs">
            {[...logsData.logs].reverse().map((log, i) => (
              <div key={i} className="flex gap-2 py-0.5">
                <span className="text-text-secondary/60 shrink-0">{log.time?.slice(11)}</span>
                <span className={`shrink-0 w-12 ${levelColors[log.level] || 'text-text-secondary'}`}>
                  [{log.level}]
                </span>
                <span className="text-text">{log.message}</span>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-xs text-text-secondary">
            {status === 'running' ? 'Waiting for activity...' : 'Start the bot to see activity logs.'}
          </p>
        )}
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
