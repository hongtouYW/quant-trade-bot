import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { UserPlus, ChevronDown, ChevronUp, Key, MessageCircle, Bot, ShieldCheck, Lock } from 'lucide-react';
import { useLanguage } from '../../contexts/LanguageContext';
import { formatDate, formatDateTime } from '../../utils/formatDate';

export default function Agents() {
  const { t } = useLanguage();
  const { data, loading, refetch } = useApi('/admin/agents');
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState({
    username: '', email: '', password: '', display_name: '', profit_share_pct: 20,
  });
  const [error, setError] = useState('');
  const [expandedId, setExpandedId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [pwForm, setPwForm] = useState({ agentId: null, password: '', msg: '' });

  const toggleExpand = async (agentId) => {
    if (expandedId === agentId) {
      setExpandedId(null);
      setDetail(null);
      return;
    }
    try {
      const res = await api.get(`/admin/agents/${agentId}`);
      setDetail(res.data);
      setExpandedId(agentId);
    } catch {
      setExpandedId(agentId);
      setDetail(null);
    }
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    setError('');
    try {
      await api.post('/admin/agents', form);
      setShowCreate(false);
      setForm({ username: '', email: '', password: '', display_name: '', profit_share_pct: 20 });
      refetch();
    } catch (err) {
      setError(err.response?.data?.error || t('admin.failedCreateAgent'));
    }
  };

  const handleResetPassword = async (agentId) => {
    if (pwForm.password.length < 8) {
      setPwForm(f => ({ ...f, msg: t('admin.passwordMinLength') }));
      return;
    }
    try {
      await api.post(`/admin/agents/${agentId}/reset-password`, { new_password: pwForm.password });
      setPwForm({ agentId: null, password: '', msg: t('admin.passwordReset') });
    } catch (err) {
      setPwForm(f => ({ ...f, msg: err.response?.data?.error || t('settings.failed') }));
    }
  };

  const toggleTrading = async (agentId) => {
    try {
      await api.post(`/admin/agents/${agentId}/toggle-trading`);
      refetch();
    } catch (err) {
      setError(err.response?.data?.error || t('admin.actionFailed'));
    }
  };

  const StatusIcon = ({ ok, label }) => (
    <span className={`inline-flex items-center gap-1 text-xs ${ok ? 'text-success' : 'text-text-tertiary'}`} title={label}>
      {ok ? '✓' : '✗'}
    </span>
  );

  const columns = [
    { key: 'id', label: t('admin.id') },
    { key: 'username', label: t('admin.username') },
    { key: 'display_name', label: t('admin.name'), render: (v) => v || '-' },
    { key: 'is_active', label: t('admin.active'), render: (v) => (
      <Badge variant={v ? 'success' : 'danger'}>{v ? t('admin.yes') : t('admin.no')}</Badge>
    )},
    { key: 'is_trading_enabled', label: t('admin.trading'), render: (v) => (
      <Badge variant={v ? 'success' : 'neutral'}>{v ? t('admin.enabled') : t('admin.disabled')}</Badge>
    )},
    { key: 'config_status', label: t('admin.config'), render: (_, row) => (
      <div className="flex items-center gap-2">
        <span className="inline-flex items-center gap-0.5 text-xs" title="API Key">
          <Key size={12} className={row.has_api_key ? 'text-success' : 'text-text-tertiary'} />
          {row.api_key_verified && <ShieldCheck size={12} className="text-success" />}
        </span>
        <span title="Telegram">
          <MessageCircle size={12} className={row.has_telegram ? 'text-success' : 'text-text-tertiary'} />
        </span>
        <span title={`Bot: ${row.bot_status || 'stopped'}`}>
          <Bot size={12} className={row.bot_status === 'running' ? 'text-success' : 'text-text-tertiary'} />
        </span>
      </div>
    )},
    { key: 'profit_share_pct', label: t('admin.profitShare'), render: (v) => `${v}%` },
    { key: 'actions', label: '', render: (_, row) => (
      <div className="flex items-center gap-2">
        <button
          onClick={(e) => { e.stopPropagation(); toggleTrading(row.id); }}
          className={`text-xs px-2 py-1 rounded ${
            row.is_trading_enabled
              ? 'bg-danger/15 text-danger hover:bg-danger/25'
              : 'bg-success/15 text-success hover:bg-success/25'
          }`}
        >
          {row.is_trading_enabled ? t('common.disable') : t('common.enable')}
        </button>
        <button
          onClick={(e) => { e.stopPropagation(); toggleExpand(row.id); }}
          className="text-xs px-1 py-1 rounded text-text-secondary hover:text-text"
        >
          {expandedId === row.id ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
        </button>
      </div>
    )},
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">{t('admin.agentManagement')}</h2>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors"
        >
          <UserPlus size={16} />
          {t('admin.newAgent')}
        </button>
      </div>

      {showCreate && (
        <Card>
          <h3 className="text-sm font-semibold mb-3">{t('admin.createNewAgent')}</h3>
          <form onSubmit={handleCreate} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {['username', 'email', 'password', 'display_name'].map((field) => {
              const fieldLabels = { username: t('admin.username'), email: t('admin.email'), password: t('login.password'), display_name: t('admin.name') };
              return (
              <div key={field}>
                <label className="block text-xs text-text-secondary mb-1">{fieldLabels[field]}</label>
                <input
                  type={field === 'password' ? 'password' : field === 'email' ? 'email' : 'text'}
                  value={form[field]}
                  onChange={(e) => setForm({ ...form, [field]: e.target.value })}
                  required={field !== 'display_name'}
                  className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                />
              </div>
              );
            })}
            <div>
              <label className="block text-xs text-text-secondary mb-1">{t('admin.profitShare')}</label>
              <input
                type="number"
                min="0"
                max="100"
                value={form.profit_share_pct}
                onChange={(e) => setForm({ ...form, profit_share_pct: Number(e.target.value) })}
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
            </div>
            <div className="flex items-end">
              <button
                type="submit"
                className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors"
              >
                {t('common.create')}
              </button>
            </div>
            {error && <p className="text-danger text-xs col-span-2">{error}</p>}
          </form>
        </Card>
      )}

      <Card>
        <Table
          columns={columns}
          data={data?.agents || []}
          emptyText={loading ? t('common.loading') : t('common.noData')}
        />

        {expandedId && (
          <div className="border-t border-border mt-2 pt-4 px-2 pb-2">
            {(() => {
              const agent = (data?.agents || []).find(a => a.id === expandedId);
              if (!agent) return null;
              return (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                  <div>
                    <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">{t('admin.account')}</h4>
                    <div className="space-y-1">
                      <p><span className="text-text-secondary">{t('admin.email')}</span> {agent.email}</p>
                      <p><span className="text-text-secondary">{t('admin.created')}</span> {agent.created_at ? formatDate(agent.created_at) : '-'}</p>
                      <p><span className="text-text-secondary">{t('admin.lastLogin')}</span> {agent.last_login_at ? formatDateTime(agent.last_login_at) : t('common.never')}</p>
                    </div>
                  </div>

                  <div>
                    <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">{t('admin.configuration')}</h4>
                    <div className="space-y-1">
                      <p className="flex items-center gap-2">
                        <Key size={14} className={agent.has_api_key ? 'text-success' : 'text-danger'} />
                        <span>API Key: {agent.has_api_key ? t('common.configured') : t('common.notSet')}</span>
                        {agent.has_api_key && (
                          <Badge variant={agent.api_key_verified ? 'success' : 'warning'} className="text-[10px]">
                            {agent.api_key_verified ? t('common.verified') : t('common.unverified')}
                          </Badge>
                        )}
                      </p>
                      <p className="flex items-center gap-2">
                        <MessageCircle size={14} className={agent.has_telegram ? 'text-success' : 'text-text-tertiary'} />
                        <span>Telegram: {agent.has_telegram ? t('common.configured') : t('common.notSet')}</span>
                      </p>
                      <p className="flex items-center gap-2">
                        <Bot size={14} className={agent.bot_status === 'running' ? 'text-success' : 'text-text-tertiary'} />
                        <span>Bot: <Badge variant={agent.bot_status === 'running' ? 'success' : agent.bot_status === 'error' ? 'danger' : 'neutral'}>{agent.bot_status || 'stopped'}</Badge></span>
                      </p>
                    </div>
                  </div>

                  {detail?.trade_stats && (
                    <div>
                      <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">{t('admin.tradeStats')}</h4>
                      <div className="space-y-1">
                        <p><span className="text-text-secondary">{t('admin.totalTrades')}</span> {detail.trade_stats.total_trades}</p>
                        <p><span className="text-text-secondary">{t('admin.winTrades')}</span> {detail.trade_stats.win_trades}</p>
                        <p>
                          <span className="text-text-secondary">{t('admin.totalPnl')}</span>{' '}
                          <span className={detail.trade_stats.total_pnl >= 0 ? 'text-success' : 'text-danger'}>
                            {detail.trade_stats.total_pnl >= 0 ? '+' : ''}{Number(detail.trade_stats.total_pnl).toFixed(2)}U
                          </span>
                        </p>
                      </div>
                    </div>
                  )}

                  <div>
                    <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">{t('admin.resetPassword')}</h4>
                    {pwForm.agentId === agent.id ? (
                      <div className="space-y-2">
                        <input
                          type="password"
                          placeholder={t('admin.newPassword')}
                          value={pwForm.password}
                          onChange={(e) => setPwForm({ ...pwForm, password: e.target.value })}
                          className="w-full px-3 py-1.5 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                        />
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleResetPassword(agent.id)}
                            className="px-3 py-1 bg-warning/15 text-warning hover:bg-warning/25 text-xs rounded-lg"
                          >
                            {t('admin.resetPassword')}
                          </button>
                          <button
                            onClick={() => setPwForm({ agentId: null, password: '', msg: '' })}
                            className="px-3 py-1 text-text-secondary hover:text-text text-xs"
                          >
                            {t('common.cancel')}
                          </button>
                        </div>
                        {pwForm.msg && <p className="text-xs text-text-secondary">{pwForm.msg}</p>}
                      </div>
                    ) : (
                      <button
                        onClick={() => setPwForm({ agentId: agent.id, password: '', msg: '' })}
                        className="flex items-center gap-1 px-3 py-1.5 bg-warning/15 text-warning hover:bg-warning/25 text-xs rounded-lg"
                      >
                        <Lock size={12} /> {t('admin.resetPassword')}
                      </button>
                    )}
                    {pwForm.agentId !== agent.id && pwForm.msg && (
                      <p className="text-xs text-success mt-1">{pwForm.msg}</p>
                    )}
                  </div>
                </div>
              );
            })()}
          </div>
        )}
      </Card>
    </div>
  );
}
