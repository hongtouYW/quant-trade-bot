import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import api from '../../api/client';
import { Key, MessageSquare, Sliders, Check, AlertTriangle, ChevronRight, ChevronLeft, ExternalLink, Shield, Copy } from 'lucide-react';

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

const SERVER_IP = '139.162.41.38';

const WIZARD_STEPS = [
  {
    title: 'Log in to Binance',
    content: (
      <div className="space-y-3 text-sm text-text-secondary">
        <p>Go to your Binance account and navigate to <b className="text-text">API Management</b>.</p>
        <a
          href="https://www.binance.com/en/my/settings/api-management"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-primary hover:underline"
        >
          Open Binance API Management <ExternalLink size={12} />
        </a>
        <p>Click <b className="text-text">"Create API"</b> and choose <b className="text-text">"System generated"</b>.</p>
      </div>
    ),
  },
  {
    title: 'Set IP Whitelist',
    content: (
      <div className="space-y-3 text-sm text-text-secondary">
        <p>In your API key settings, add our server IP to the <b className="text-text">IP access restriction</b>:</p>
        <div className="flex items-center gap-2 bg-surface/50 rounded-lg px-3 py-2 font-mono text-text">
          <span>{SERVER_IP}</span>
          <button
            onClick={() => navigator.clipboard.writeText(SERVER_IP)}
            className="p-1 hover:bg-white/10 rounded"
            title="Copy IP"
          >
            <Copy size={14} className="text-text-secondary" />
          </button>
        </div>
        <div className="flex items-start gap-2 text-xs bg-warning/10 text-warning rounded-lg px-3 py-2">
          <Shield size={14} className="shrink-0 mt-0.5" />
          <span>IP restriction ensures only our server can use this key. Without it, anyone with your key could trade.</span>
        </div>
      </div>
    ),
  },
  {
    title: 'Enable Futures',
    content: (
      <div className="space-y-3 text-sm text-text-secondary">
        <p>In the API restrictions section, enable:</p>
        <ul className="space-y-1.5 ml-4">
          <li className="flex items-center gap-2">
            <Check size={14} className="text-success" />
            <span><b className="text-text">Enable Futures</b> - Required for trading</span>
          </li>
        </ul>
        <div className="flex items-start gap-2 text-xs bg-success/10 text-success rounded-lg px-3 py-2">
          <Shield size={14} className="shrink-0 mt-0.5" />
          <span>Do NOT enable "Enable Withdrawals". Our bot only needs trading permissions, never withdrawal access.</span>
        </div>
      </div>
    ),
  },
  {
    title: 'Paste Keys',
    isForm: true,
  },
];

function ApiKeySection() {
  const { data: status, refetch } = useApi('/agent/api-keys/status');
  const [step, setStep] = useState(0);
  const [showWizard, setShowWizard] = useState(false);
  const [form, setForm] = useState({ api_key: '', api_secret: '', is_testnet: false });
  const [msg, setMsg] = useState('');
  const [verifying, setVerifying] = useState(false);

  const handleSave = async (e) => {
    e.preventDefault();
    setMsg('');
    try {
      await api.put('/agent/api-keys', form);
      setMsg('API keys saved successfully!');
      setForm({ api_key: '', api_secret: '', is_testnet: form.is_testnet });
      refetch();
      setShowWizard(false);
      setStep(0);
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

  const currentStep = WIZARD_STEPS[step];

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Key size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Binance API Keys</h3>
        {status?.has_api_key && (
          <span className={`ml-auto text-xs ${status.permissions_verified ? 'text-success' : 'text-warning'}`}>
            {status.permissions_verified ? 'Verified' : 'Not verified'}
          </span>
        )}
      </div>

      {/* Current status + actions when keys exist */}
      {status?.has_api_key && !showWizard && (
        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm">
            <Check size={16} className="text-success" />
            <span className="text-text">API Key configured</span>
            {status.is_testnet && <span className="text-xs bg-warning/15 text-warning px-2 py-0.5 rounded">Testnet</span>}
          </div>
          <div className="flex gap-2">
            <button onClick={() => setShowWizard(true)} className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
              Update Keys
            </button>
            <button onClick={handleVerify} disabled={verifying}
              className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors">
              {verifying ? 'Verifying...' : 'Verify'}
            </button>
          </div>
          {msg && <p className="text-xs text-text-secondary">{msg}</p>}
        </div>
      )}

      {/* Wizard */}
      {(showWizard || !status?.has_api_key) && (
        <div className="space-y-4">
          {/* Step indicator */}
          <div className="flex items-center gap-1">
            {WIZARD_STEPS.map((s, i) => (
              <div key={i} className="flex items-center">
                <div className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                  i < step ? 'bg-success text-white' :
                  i === step ? 'bg-primary text-white' : 'bg-white/10 text-text-secondary'
                }`}>
                  {i < step ? <Check size={12} /> : i + 1}
                </div>
                {i < WIZARD_STEPS.length - 1 && (
                  <div className={`w-8 h-0.5 mx-1 ${i < step ? 'bg-success' : 'bg-white/10'}`} />
                )}
              </div>
            ))}
          </div>

          <h4 className="text-sm font-semibold">{currentStep.title}</h4>

          {currentStep.isForm ? (
            <form onSubmit={handleSave} className="space-y-3">
              <p className="text-sm text-text-secondary">Copy the API Key and Secret from Binance and paste them below:</p>
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
                <input type="checkbox" checked={form.is_testnet}
                  onChange={(e) => setForm({ ...form, is_testnet: e.target.checked })} className="rounded" />
                Testnet mode (for testing only)
              </label>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(step - 1)}
                  className="flex items-center gap-1 px-4 py-2 text-text-secondary hover:text-text text-sm rounded-lg border border-border transition-colors">
                  <ChevronLeft size={14} /> Back
                </button>
                <button type="submit" className="flex items-center gap-1 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                  Save & Finish <Check size={14} />
                </button>
              </div>
              {msg && <p className="text-xs text-text-secondary">{msg}</p>}
            </form>
          ) : (
            <>
              {currentStep.content}
              <div className="flex gap-2 pt-2">
                {step > 0 && (
                  <button onClick={() => setStep(step - 1)}
                    className="flex items-center gap-1 px-4 py-2 text-text-secondary hover:text-text text-sm rounded-lg border border-border transition-colors">
                    <ChevronLeft size={14} /> Back
                  </button>
                )}
                <button onClick={() => setStep(step + 1)}
                  className="flex items-center gap-1 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                  Next <ChevronRight size={14} />
                </button>
                {status?.has_api_key && (
                  <button onClick={() => { setShowWizard(false); setStep(0); }}
                    className="px-4 py-2 text-text-secondary hover:text-text text-sm transition-colors">
                    Cancel
                  </button>
                )}
              </div>
            </>
          )}
        </div>
      )}
    </Card>
  );
}

function TelegramSection() {
  const { data: existing } = useApi('/agent/telegram');
  const [form, setForm] = useState({ bot_token: '', chat_id: '', is_enabled: true });
  const [msg, setMsg] = useState('');
  const [msgColor, setMsgColor] = useState('text-text-secondary');

  useEffect(() => {
    if (existing?.configured) {
      setForm(f => ({
        ...f,
        chat_id: existing.chat_id || '',
        is_enabled: existing.is_enabled ?? true,
      }));
    }
  }, [existing]);

  const handleSave = async (e) => {
    e.preventDefault();
    if (!form.bot_token) {
      setMsg('Please enter the Bot Token');
      setMsgColor('text-warning');
      return;
    }
    try {
      await api.put('/agent/telegram', form);
      setMsg('Telegram config saved');
      setMsgColor('text-success');
      setForm(f => ({ ...f, bot_token: '' }));
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed');
      setMsgColor('text-danger');
    }
  };

  const handleTest = async () => {
    setMsg('Sending...');
    setMsgColor('text-text-secondary');
    try {
      const res = await api.post('/agent/telegram/test');
      if (res.data.success) {
        setMsg('Test message sent!');
        setMsgColor('text-success');
      } else {
        setMsg(`Failed: ${res.data.error || 'Unknown error'}`);
        setMsgColor('text-danger');
      }
    } catch (err) {
      setMsg(err.response?.data?.error || 'Test failed');
      setMsgColor('text-danger');
    }
  };

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <MessageSquare size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Telegram Notifications</h3>
        {existing?.configured && (
          <span className="ml-auto text-xs text-success">Configured</span>
        )}
      </div>
      <form onSubmit={handleSave} className="space-y-3">
        <input
          type="password"
          placeholder={existing?.configured ? 'Bot Token (leave blank to keep current)' : 'Bot Token'}
          value={form.bot_token}
          onChange={(e) => setForm({ ...form, bot_token: e.target.value })}
          required={!existing?.configured}
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <input
          type="text"
          placeholder="Chat ID (e.g. -1001234567890)"
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
          {existing?.configured && (
            <button
              type="button"
              onClick={handleTest}
              className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors"
            >
              Send Test
            </button>
          )}
        </div>
        {msg && <p className={`text-xs ${msgColor}`}>{msg}</p>}
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

  const strategies = strategiesData?.strategies || [];

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Sliders size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">Trading Configuration</h3>
      </div>

      {/* Strategy presets */}
      {strategies.length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-text-secondary mb-2">Quick Apply Strategy:</p>
          <div className="flex flex-wrap gap-2">
            {strategies.map((s) => (
              <button
                key={s.version}
                onClick={() => applyStrategy(s.version)}
                className={`px-3 py-1 text-xs rounded-lg border transition-colors ${
                  form.strategy_version === s.version
                    ? 'border-primary bg-primary/15 text-primary'
                    : 'border-border text-text-secondary hover:border-primary/50'
                }`}
              >
                {s.version} - {s.label || s.version}
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
