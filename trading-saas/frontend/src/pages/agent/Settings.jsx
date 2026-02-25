import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import api from '../../api/client';
import { Key, MessageSquare, Sliders, Check, AlertTriangle } from 'lucide-react';

export default function Settings() {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Settings</h2>
      <ApiKeySection />
      <TelegramSection />
      <TradingConfigSection />
    </div>
  );
}

function ApiKeySection() {
  const { data: status, refetch } = useApi('/agent/api-keys/status');
  const [form, setForm] = useState({ api_key: '', api_secret: '', is_testnet: false });
  const [msg, setMsg] = useState('');
  const [verifying, setVerifying] = useState(false);

  const handleSave = async (e) => {
    e.preventDefault();
    setMsg('');
    try {
      await api.put('/agent/api-keys', form);
      setMsg('API keys saved');
      setForm({ api_key: '', api_secret: '', is_testnet: form.is_testnet });
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed to save');
    }
  };

  const handleVerify = async () => {
    setVerifying(true);
    try {
      const res = await api.post('/agent/api-keys/verify');
      setMsg(res.data.verified ? 'API key verified!' : `Verification failed: ${res.data.error}`);
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || 'Verification failed');
    }
    setVerifying(false);
  };

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Key size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Binance API Keys</h3>
        {status?.has_keys && (
          <span className={`ml-auto text-xs ${status.verified ? 'text-success' : 'text-warning'}`}>
            {status.verified ? 'Verified' : 'Not verified'}
          </span>
        )}
      </div>
      <form onSubmit={handleSave} className="space-y-3">
        <input
          type="password"
          placeholder="API Key"
          value={form.api_key}
          onChange={(e) => setForm({ ...form, api_key: e.target.value })}
          required
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <input
          type="password"
          placeholder="API Secret"
          value={form.api_secret}
          onChange={(e) => setForm({ ...form, api_secret: e.target.value })}
          required
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <label className="flex items-center gap-2 text-sm text-text-secondary">
          <input
            type="checkbox"
            checked={form.is_testnet}
            onChange={(e) => setForm({ ...form, is_testnet: e.target.checked })}
            className="rounded"
          />
          Testnet mode
        </label>
        <div className="flex gap-2">
          <button type="submit" className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
            Save Keys
          </button>
          {status?.has_keys && (
            <button
              type="button"
              onClick={handleVerify}
              disabled={verifying}
              className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors"
            >
              {verifying ? 'Verifying...' : 'Verify'}
            </button>
          )}
        </div>
        {msg && <p className="text-xs text-text-secondary">{msg}</p>}
      </form>
    </Card>
  );
}

function TelegramSection() {
  const [form, setForm] = useState({ bot_token: '', chat_id: '', is_enabled: true });
  const [msg, setMsg] = useState('');

  const handleSave = async (e) => {
    e.preventDefault();
    try {
      await api.put('/agent/telegram', form);
      setMsg('Telegram config saved');
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed');
    }
  };

  const handleTest = async () => {
    try {
      const res = await api.post('/agent/telegram/test');
      setMsg(res.data.success ? 'Test message sent!' : 'Test failed');
    } catch (err) {
      setMsg(err.response?.data?.error || 'Test failed');
    }
  };

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <MessageSquare size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Telegram Notifications</h3>
      </div>
      <form onSubmit={handleSave} className="space-y-3">
        <input
          type="password"
          placeholder="Bot Token"
          value={form.bot_token}
          onChange={(e) => setForm({ ...form, bot_token: e.target.value })}
          required
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <input
          type="text"
          placeholder="Chat ID"
          value={form.chat_id}
          onChange={(e) => setForm({ ...form, chat_id: e.target.value })}
          required
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <label className="flex items-center gap-2 text-sm text-text-secondary">
          <input
            type="checkbox"
            checked={form.is_enabled}
            onChange={(e) => setForm({ ...form, is_enabled: e.target.checked })}
            className="rounded"
          />
          Enable notifications
        </label>
        <div className="flex gap-2">
          <button type="submit" className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
            Save
          </button>
          <button
            type="button"
            onClick={handleTest}
            className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors"
          >
            Send Test
          </button>
        </div>
        {msg && <p className="text-xs text-text-secondary">{msg}</p>}
      </form>
    </Card>
  );
}

function TradingConfigSection() {
  const { data, loading, refetch } = useApi('/agent/trading/config');
  const { data: strategiesData } = useApi('/agent/trading/strategies');
  const [form, setForm] = useState(null);
  const [msg, setMsg] = useState('');

  useEffect(() => {
    if (data?.config) setForm(data.config);
  }, [data]);

  const handleSave = async (e) => {
    e.preventDefault();
    try {
      await api.put('/agent/trading/config', form);
      setMsg('Config saved');
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed');
    }
  };

  const applyStrategy = async (version) => {
    try {
      await api.post(`/agent/trading/strategy/${version}`);
      setMsg(`Strategy ${version} applied`);
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed');
    }
  };

  if (loading || !form) return null;

  const fields = [
    { key: 'initial_capital', label: 'Initial Capital (U)', type: 'number', step: '0.01' },
    { key: 'max_positions', label: 'Max Positions', type: 'number' },
    { key: 'max_leverage', label: 'Max Leverage', type: 'number' },
    { key: 'min_score', label: 'Min Signal Score', type: 'number' },
    { key: 'roi_stop_loss', label: 'ROI Stop Loss (%)', type: 'number', step: '0.01' },
    { key: 'roi_trailing_start', label: 'Trailing Start (%)', type: 'number', step: '0.01' },
    { key: 'roi_trailing_distance', label: 'Trailing Distance (%)', type: 'number', step: '0.01' },
    { key: 'daily_loss_limit', label: 'Daily Loss Limit (U)', type: 'number', step: '0.01' },
  ];

  const strategies = strategiesData?.strategies || {};

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Sliders size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Trading Configuration</h3>
      </div>

      {/* Strategy presets */}
      {Object.keys(strategies).length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-text-secondary mb-2">Quick Apply Strategy:</p>
          <div className="flex flex-wrap gap-2">
            {Object.entries(strategies).map(([ver, info]) => (
              <button
                key={ver}
                onClick={() => applyStrategy(ver)}
                className={`px-3 py-1 text-xs rounded-lg border transition-colors ${
                  form.strategy_version === ver
                    ? 'border-primary bg-primary/15 text-primary'
                    : 'border-border text-text-secondary hover:border-primary/50'
                }`}
              >
                {ver} - {info.name || ver}
              </button>
            ))}
          </div>
        </div>
      )}

      <form onSubmit={handleSave}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {fields.map(({ key, label, type, step }) => (
            <div key={key}>
              <label className="block text-xs text-text-secondary mb-1">{label}</label>
              <input
                type={type}
                step={step}
                value={form[key] ?? ''}
                onChange={(e) => setForm({ ...form, [key]: Number(e.target.value) })}
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
            </div>
          ))}
        </div>
        <div className="flex items-center gap-3 mt-4">
          <button type="submit" className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
            Save Config
          </button>
          {msg && <span className="text-xs text-text-secondary">{msg}</span>}
        </div>
      </form>
    </Card>
  );
}
