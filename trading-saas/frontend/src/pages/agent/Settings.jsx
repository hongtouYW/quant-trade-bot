import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import api from '../../api/client';
import { useLanguage } from '../../contexts/LanguageContext';
import { Key, MessageSquare, Sliders, Check, ChevronRight, ChevronLeft, ExternalLink, Shield, Copy } from 'lucide-react';

export default function Settings() {
  const { t } = useLanguage();
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">{t('settings.title')}</h2>
      <ApiKeySection />
      <TelegramSection />
      <TradingConfigSection />
    </div>
  );
}

const SERVER_IP = '43.98.81.206';

const EXCHANGE_INFO = {
  binance: {
    name: 'Binance',
    apiUrl: 'https://www.binance.com/en/my/settings/api-management',
    color: 'text-yellow-400',
    bg: 'bg-yellow-400/10',
    border: 'border-yellow-400/50',
  },
  bitget: {
    name: 'Bitget',
    apiUrl: 'https://www.bitget.com/account/newapi',
    color: 'text-cyan-400',
    bg: 'bg-cyan-400/10',
    border: 'border-cyan-400/50',
  },
};

function getWizardSteps(t, exchange) {
  const info = EXCHANGE_INFO[exchange] || EXCHANGE_INFO.binance;
  const isBitget = exchange === 'bitget';

  return [
    {
      title: t('settings.wizardStep1Title'),
      content: (
        <div className="space-y-3 text-sm text-text-secondary">
          <p>{t('settings.wizardStep1Desc1')} <b className="text-text">{t('settings.apiManagement')}</b>.</p>
          <a
            href={info.apiUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-primary hover:underline"
          >
            {t('settings.openExchangeApi').replace('{exchange}', info.name)} <ExternalLink size={12} />
          </a>
          <p>{t('settings.wizardStep1Desc2')} <b className="text-text">"{t('settings.createApi')}"</b> {t('settings.wizardStep1Desc3')} <b className="text-text">"{t('settings.systemGenerated')}"</b>.</p>
        </div>
      ),
    },
    {
      title: t('settings.wizardStep2Title'),
      content: (
        <div className="space-y-3 text-sm text-text-secondary">
          <p>{t('settings.wizardStep2Desc')} <b className="text-text">{t('settings.ipAccessRestriction')}</b>:</p>
          <div className="flex items-center gap-2 bg-surface/50 rounded-lg px-3 py-2 font-mono text-text">
            <span>{SERVER_IP}</span>
            <button
              onClick={() => navigator.clipboard.writeText(SERVER_IP)}
              className="p-1 hover:bg-white/10 rounded"
              title={t('settings.copyIp')}
            >
              <Copy size={14} className="text-text-secondary" />
            </button>
          </div>
          <div className="flex items-start gap-2 text-xs bg-warning/10 text-warning rounded-lg px-3 py-2">
            <Shield size={14} className="shrink-0 mt-0.5" />
            <span>{t('settings.ipWarning')}</span>
          </div>
        </div>
      ),
    },
    {
      title: t('settings.wizardStep3Title'),
      content: (
        <div className="space-y-3 text-sm text-text-secondary">
          <p>{t('settings.wizardStep3Desc')}</p>
          <ul className="space-y-1.5 ml-4">
            <li className="flex items-center gap-2">
              <Check size={14} className="text-success" />
              <span><b className="text-text">{isBitget ? t('settings.enableContract') : t('settings.enableFutures')}</b> - {t('settings.requiredForTrading')}</span>
            </li>
          </ul>
          <div className="flex items-start gap-2 text-xs bg-success/10 text-success rounded-lg px-3 py-2">
            <Shield size={14} className="shrink-0 mt-0.5" />
            <span>{t('settings.noWithdrawalsWarning')}</span>
          </div>
        </div>
      ),
    },
    {
      title: t('settings.wizardStep4Title'),
      isForm: true,
    },
  ];
}

function ApiKeySection() {
  const { t } = useLanguage();
  const { data: status, refetch } = useApi('/agent/api-keys/status');
  const [step, setStep] = useState(0);
  const [showWizard, setShowWizard] = useState(false);
  const [selectedExchange, setSelectedExchange] = useState('binance');
  const [form, setForm] = useState({ api_key: '', api_secret: '', passphrase: '', is_testnet: false });
  const [msg, setMsg] = useState('');
  const [verifying, setVerifying] = useState(false);

  // Sync exchange from server status
  useEffect(() => {
    if (status?.exchange) setSelectedExchange(status.exchange);
  }, [status]);

  const wizardSteps = getWizardSteps(t, selectedExchange);
  const isBitget = selectedExchange === 'bitget';
  const exchangeInfo = EXCHANGE_INFO[selectedExchange] || EXCHANGE_INFO.binance;

  const handleSave = async (e) => {
    e.preventDefault();
    setMsg('');
    try {
      await api.put('/agent/api-keys', {
        ...form,
        exchange: selectedExchange,
      });
      setMsg(t('settings.apiKeysSaved'));
      setForm({ api_key: '', api_secret: '', passphrase: '', is_testnet: form.is_testnet });
      refetch();
      setShowWizard(false);
      setStep(0);
    } catch (err) {
      setMsg(err.response?.data?.error || t('settings.failedToSave'));
    }
  };

  const handleVerify = async () => {
    setVerifying(true);
    try {
      const res = await api.post('/agent/api-keys/verify');
      setMsg(res.data.verified ? t('settings.apiKeyVerified') : `${t('settings.verificationFailed')}: ${res.data.error}`);
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || t('settings.verificationFailed'));
    }
    setVerifying(false);
  };

  const currentStep = wizardSteps[step];

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Key size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">{t('settings.exchangeApiKeys')}</h3>
        {status?.has_api_key && (
          <span className={`ml-auto text-xs px-2 py-0.5 rounded ${exchangeInfo.bg} ${exchangeInfo.color}`}>
            {exchangeInfo.name}
            {status.permissions_verified ? ' \u2713' : ''}
          </span>
        )}
      </div>

      {/* Current status + actions when keys exist */}
      {status?.has_api_key && !showWizard && (
        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm">
            <Check size={16} className="text-success" />
            <span className="text-text">{t('settings.apiKeyConfigured')}</span>
            {status.is_testnet && <span className="text-xs bg-warning/15 text-warning px-2 py-0.5 rounded">{t('settings.testnet')}</span>}
          </div>
          <div className="flex gap-2">
            <button onClick={() => setShowWizard(true)} className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
              {t('settings.updateKeys')}
            </button>
            <button onClick={handleVerify} disabled={verifying}
              className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors">
              {verifying ? t('settings.verifying') : t('settings.verify')}
            </button>
          </div>
          {msg && <p className="text-xs text-text-secondary">{msg}</p>}
        </div>
      )}

      {/* Wizard */}
      {(showWizard || !status?.has_api_key) && (
        <div className="space-y-4">
          {/* Exchange selector */}
          <div>
            <p className="text-xs text-text-secondary mb-2">{t('settings.selectExchange')}</p>
            <div className="flex gap-2">
              {Object.entries(EXCHANGE_INFO).map(([key, info]) => (
                <button
                  key={key}
                  onClick={() => { setSelectedExchange(key); setStep(0); }}
                  className={`px-4 py-2 text-sm rounded-lg border transition-colors ${
                    selectedExchange === key
                      ? `${info.border} ${info.bg} ${info.color} font-semibold`
                      : 'border-border text-text-secondary hover:border-white/30'
                  }`}
                >
                  {info.name}
                </button>
              ))}
            </div>
          </div>

          {/* Step indicator */}
          <div className="flex items-center gap-1">
            {wizardSteps.map((s, i) => (
              <div key={i} className="flex items-center">
                <div className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                  i < step ? 'bg-success text-white' :
                  i === step ? 'bg-primary text-white' : 'bg-white/10 text-text-secondary'
                }`}>
                  {i < step ? <Check size={12} /> : i + 1}
                </div>
                {i < wizardSteps.length - 1 && (
                  <div className={`w-8 h-0.5 mx-1 ${i < step ? 'bg-success' : 'bg-white/10'}`} />
                )}
              </div>
            ))}
          </div>

          <h4 className="text-sm font-semibold">{currentStep.title}</h4>

          {currentStep.isForm ? (
            <form onSubmit={handleSave} className="space-y-3">
              <p className="text-sm text-text-secondary">{t('settings.pasteKeysDesc')}</p>
              <input
                type="password"
                placeholder={t('settings.apiKeyPlaceholder')}
                value={form.api_key}
                onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                required
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
              <input
                type="password"
                placeholder={t('settings.apiSecretPlaceholder')}
                value={form.api_secret}
                onChange={(e) => setForm({ ...form, api_secret: e.target.value })}
                required
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
              {isBitget && (
                <input
                  type="password"
                  placeholder={t('settings.passphrasePlaceholder')}
                  value={form.passphrase}
                  onChange={(e) => setForm({ ...form, passphrase: e.target.value })}
                  required
                  className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                />
              )}
              <label className="flex items-center gap-2 text-sm text-text-secondary">
                <input type="checkbox" checked={form.is_testnet}
                  onChange={(e) => setForm({ ...form, is_testnet: e.target.checked })} className="rounded" />
                {isBitget ? t('settings.demoMode') : t('settings.testnetMode')}
              </label>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(step - 1)}
                  className="flex items-center gap-1 px-4 py-2 text-text-secondary hover:text-text text-sm rounded-lg border border-border transition-colors">
                  <ChevronLeft size={14} /> {t('settings.back')}
                </button>
                <button type="submit" className="flex items-center gap-1 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                  {t('settings.saveAndFinish')} <Check size={14} />
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
                    <ChevronLeft size={14} /> {t('settings.back')}
                  </button>
                )}
                <button onClick={() => setStep(step + 1)}
                  className="flex items-center gap-1 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                  {t('settings.next')} <ChevronRight size={14} />
                </button>
                {status?.has_api_key && (
                  <button onClick={() => { setShowWizard(false); setStep(0); }}
                    className="px-4 py-2 text-text-secondary hover:text-text text-sm transition-colors">
                    {t('settings.cancel')}
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
  const { t } = useLanguage();
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
    if (!form.bot_token && !existing?.configured) {
      setMsg(t('settings.enterBotToken'));
      setMsgColor('text-warning');
      return;
    }
    try {
      await api.put('/agent/telegram', form);
      setMsg(t('settings.telegramSaved'));
      setMsgColor('text-success');
      setForm(f => ({ ...f, bot_token: '' }));
    } catch (err) {
      setMsg(err.response?.data?.error || t('settings.failedToSave'));
      setMsgColor('text-danger');
    }
  };

  const handleTest = async () => {
    setMsg(t('settings.sending'));
    setMsgColor('text-text-secondary');
    try {
      const res = await api.post('/agent/telegram/test');
      if (res.data.success) {
        setMsg(t('settings.testSent'));
        setMsgColor('text-success');
      } else {
        setMsg(`${t('settings.failed')}: ${res.data.error || t('settings.unknownError')}`);
        setMsgColor('text-danger');
      }
    } catch (err) {
      setMsg(err.response?.data?.error || t('settings.testFailed'));
      setMsgColor('text-danger');
    }
  };

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <MessageSquare size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">{t('settings.telegramNotifications')}</h3>
        {existing?.configured && (
          <span className="ml-auto text-xs text-success">{t('settings.configured')}</span>
        )}
      </div>
      <form onSubmit={handleSave} className="space-y-3">
        <input
          type="password"
          placeholder={existing?.configured ? t('settings.botTokenKeepCurrent') : t('settings.botTokenPlaceholder')}
          value={form.bot_token}
          onChange={(e) => setForm({ ...form, bot_token: e.target.value })}
          required={!existing?.configured}
          className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
        />
        <input
          type="text"
          placeholder={t('settings.chatIdPlaceholder')}
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
          {t('settings.enableNotifications')}
        </label>
        <div className="flex gap-2">
          <button type="submit" className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
            {t('settings.save')}
          </button>
          {existing?.configured && (
            <button
              type="button"
              onClick={handleTest}
              className="px-4 py-2 bg-success/15 text-success hover:bg-success/25 text-sm rounded-lg transition-colors"
            >
              {t('settings.sendTest')}
            </button>
          )}
        </div>
        {msg && <p className={`text-xs ${msgColor}`}>{msg}</p>}
      </form>
    </Card>
  );
}

function TradingConfigSection() {
  const { t } = useLanguage();
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
      setMsg(t('settings.configSaved'));
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || 'Failed');
    }
  };

  const applyStrategy = async (version) => {
    try {
      await api.post(`/agent/trading/strategy/${version}`);
      setMsg(`${t('settings.strategyApplied')} ${version}`);
      refetch();
    } catch (err) {
      setMsg(err.response?.data?.error || t('settings.failedToSave'));
    }
  };

  if (loading || !form) return null;

  const fields = [
    { key: 'initial_capital', label: t('settings.initialCapital'), type: 'number', step: '0.01' },
    { key: 'max_positions', label: t('settings.maxPositions'), type: 'number' },
    { key: 'max_leverage', label: t('settings.maxLeverage'), type: 'number' },
    { key: 'min_score', label: t('settings.minScore'), type: 'number' },
    { key: 'roi_stop_loss', label: t('settings.roiStopLoss'), type: 'number', step: '0.01' },
    { key: 'roi_trailing_start', label: t('settings.trailingStart'), type: 'number', step: '0.01' },
    { key: 'roi_trailing_distance', label: t('settings.trailingDistance'), type: 'number', step: '0.01' },
    { key: 'daily_loss_limit', label: t('settings.dailyLossLimit'), type: 'number', step: '0.01' },
  ];

  const strategies = strategiesData?.strategies || [];

  return (
    <Card>
      <div className="flex items-center gap-2 mb-4">
        <Sliders size={18} className="text-primary" />
        <h3 className="text-sm font-semibold">{t('settings.tradingConfig')}</h3>
      </div>

      {/* Strategy presets */}
      {strategies.length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-text-secondary mb-2">{t('settings.quickApply')}</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {strategies.map((s) => {
              const isCurrent = form.strategy_version === s.version;
              const c = s.config || {};
              return (
                <button
                  key={s.version}
                  onClick={() => applyStrategy(s.version)}
                  className={`text-left px-3 py-2 rounded-lg border transition-colors ${
                    isCurrent
                      ? 'border-primary bg-primary/10'
                      : 'border-border hover:border-primary/50'
                  }`}
                >
                  <div className="flex items-center gap-2 mb-1">
                    <span className={`text-xs font-semibold ${isCurrent ? 'text-primary' : 'text-text'}`}>
                      {s.label || s.version}
                    </span>
                    {isCurrent && <span className="text-[10px] bg-primary/20 text-primary px-1.5 py-0.5 rounded">{t('settings.current')}</span>}
                  </div>
                  {s.description && (
                    <p className="text-[11px] text-text-secondary mb-1.5 line-clamp-2">{s.description}</p>
                  )}
                  <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] text-text-tertiary">
                    {c.max_leverage && <span>{t('settings.maxLeverage')}: {c.max_leverage}x</span>}
                    {c.max_positions && <span>{t('settings.maxPositions')}: {c.max_positions}</span>}
                    {c.min_score && <span>{t('settings.minScore')}: {c.min_score}</span>}
                  </div>
                </button>
              );
            })}
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
            {t('settings.saveConfig')}
          </button>
          {msg && <span className="text-xs text-text-secondary">{msg}</span>}
        </div>
      </form>
    </Card>
  );
}
