import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import PnlValue from '../../components/common/PnlValue';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { UserPlus, ChevronDown, ChevronUp, Key, MessageCircle, Bot, ShieldCheck, Lock, History } from 'lucide-react';
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
  const [tradesData, setTradesData] = useState(null);
  const [tradesPage, setTradesPage] = useState(1);
  const [tradesStatus, setTradesStatus] = useState('CLOSED');
  const [tradesLoading, setTradesLoading] = useState(false);

  const fetchTrades = async (agentId, page = 1, status = 'CLOSED') => {
    setTradesLoading(true);
    try {
      const res = await api.get(`/admin/agents/${agentId}/trades?page=${page}&per_page=10&status=${status}`);
      setTradesData(res.data);
    } catch {
      setTradesData(null);
    }
    setTradesLoading(false);
  };

  const toggleExpand = async (agentId) => {
    if (expandedId === agentId) {
      setExpandedId(null);
      setDetail(null);
      setTradesData(null);
      return;
    }
    try {
      const res = await api.get(`/admin/agents/${agentId}`);
      setDetail(res.data);
      setExpandedId(agentId);
      setTradesPage(1);
      setTradesStatus('CLOSED');
      fetchTrades(agentId, 1, 'CLOSED');
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
                          <span className="text-text-secondary">{t('admin.botPnl')}</span>{' '}
                          <span className={detail.trade_stats.total_pnl >= 0 ? 'text-success' : 'text-danger'}>
                            {detail.trade_stats.total_pnl >= 0 ? '+' : ''}{Number(detail.trade_stats.total_pnl).toFixed(2)}U
                          </span>
                        </p>
                      </div>
                    </div>
                  )}

                  {detail?.wallet && (
                    <div>
                      <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">{t('admin.walletOverview')}</h4>
                      <div className="space-y-1">
                        <p><span className="text-text-secondary">{t('admin.walletTotal')}</span> {detail.wallet.total_balance.toFixed(2)}U</p>
                        <p><span className="text-text-secondary">{t('admin.walletFree')}</span> {detail.wallet.free.toFixed(2)}U</p>
                        <p><span className="text-text-secondary">{t('admin.walletUsed')}</span> {detail.wallet.used.toFixed(2)}U</p>
                        <p>
                          <span className="text-text-secondary">{t('admin.botPnl')}</span>{' '}
                          <PnlValue value={detail.wallet.bot_realized_pnl} />
                        </p>
                        <p><span className="text-text-secondary">{t('admin.botPositions')}</span> {detail.wallet.bot_open_positions}</p>
                        <p className="text-[10px] text-text-tertiary mt-1 italic">{t('admin.walletNote')}</p>
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

                  {/* Trade History */}
                  <div className="col-span-full mt-4 border-t border-border/50 pt-4">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="text-xs font-semibold text-text-secondary uppercase flex items-center gap-1">
                        <History size={14} /> {t('admin.tradeHistory')}
                      </h4>
                      <div className="flex gap-1">
                        {['CLOSED', 'OPEN'].map(s => (
                          <button
                            key={s}
                            onClick={() => { setTradesStatus(s); setTradesPage(1); fetchTrades(agent.id, 1, s); }}
                            className={`px-2 py-0.5 text-xs rounded ${tradesStatus === s ? 'bg-primary text-white' : 'bg-bg-input text-text-secondary hover:text-text'}`}
                          >
                            {s === 'CLOSED' ? t('admin.closedTrades') : t('admin.openTrades')}
                          </button>
                        ))}
                      </div>
                    </div>

                    {tradesLoading ? (
                      <p className="text-xs text-text-secondary">{t('common.loading')}</p>
                    ) : tradesData?.trades?.length > 0 ? (
                      <>
                        <div className="overflow-x-auto">
                          <table className="w-full text-xs">
                            <thead>
                              <tr className="text-text-secondary border-b border-border/30">
                                <th className="text-left py-1.5 px-2">{t('history.symbol')}</th>
                                <th className="text-left py-1.5 px-2">{t('history.dir')}</th>
                                <th className="text-right py-1.5 px-2">{t('history.entry')}</th>
                                {tradesStatus === 'CLOSED' && <th className="text-right py-1.5 px-2">{t('history.exit')}</th>}
                                <th className="text-right py-1.5 px-2">{t('history.amount')}</th>
                                <th className="text-center py-1.5 px-2">{t('history.lev')}</th>
                                {tradesStatus === 'CLOSED' && <th className="text-right py-1.5 px-2">{t('history.pnl')}</th>}
                                {tradesStatus === 'CLOSED' && <th className="text-right py-1.5 px-2">{t('history.roi')}</th>}
                                {tradesStatus === 'OPEN' && <th className="text-right py-1.5 px-2">{t('history.pnl')}</th>}
                                {tradesStatus === 'OPEN' && <th className="text-right py-1.5 px-2">{t('history.roi')}</th>}
                                <th className="text-right py-1.5 px-2">{tradesStatus === 'CLOSED' ? t('history.closed') : t('positions.opened')}</th>
                              </tr>
                            </thead>
                            <tbody>
                              {tradesData.trades.map((tr, i) => (
                                <tr key={i} className="border-b border-border/20 hover:bg-white/[0.02]">
                                  <td className="py-1.5 px-2 font-medium">{tr.symbol}</td>
                                  <td className="py-1.5 px-2">
                                    <Badge variant={tr.direction === 'LONG' ? 'success' : 'danger'}>{tr.direction}</Badge>
                                  </td>
                                  <td className="py-1.5 px-2 text-right">${Number(tr.entry_price).toFixed(4)}</td>
                                  {tradesStatus === 'CLOSED' && <td className="py-1.5 px-2 text-right">${Number(tr.exit_price).toFixed(4)}</td>}
                                  <td className="py-1.5 px-2 text-right">{tr.amount}U</td>
                                  <td className="py-1.5 px-2 text-center">{tr.leverage}x</td>
                                  {tradesStatus === 'CLOSED' && <td className="py-1.5 px-2 text-right"><PnlValue value={tr.pnl} /></td>}
                                  {tradesStatus === 'CLOSED' && <td className="py-1.5 px-2 text-right"><PnlValue value={tr.roi} suffix="%" /></td>}
                                  {tradesStatus === 'OPEN' && <td className="py-1.5 px-2 text-right"><PnlValue value={tr.unrealized_pnl} /></td>}
                                  {tradesStatus === 'OPEN' && <td className="py-1.5 px-2 text-right"><PnlValue value={tr.current_roi} suffix="%" /></td>}
                                  <td className="py-1.5 px-2 text-right text-text-secondary">
                                    {formatDateTime(tradesStatus === 'CLOSED' ? tr.exit_time : tr.entry_time)}
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                        {tradesData.pages > 1 && (
                          <div className="flex items-center justify-center gap-2 mt-2 pt-2 border-t border-border/30">
                            <button
                              onClick={() => { const p = Math.max(1, tradesPage - 1); setTradesPage(p); fetchTrades(agent.id, p, tradesStatus); }}
                              disabled={tradesPage <= 1}
                              className="px-2 py-0.5 text-xs rounded bg-bg-input text-text disabled:opacity-30"
                            >{t('common.prev')}</button>
                            <span className="text-xs text-text-secondary">{tradesPage} / {tradesData.pages} ({tradesData.total})</span>
                            <button
                              onClick={() => { const p = Math.min(tradesData.pages, tradesPage + 1); setTradesPage(p); fetchTrades(agent.id, p, tradesStatus); }}
                              disabled={tradesPage >= tradesData.pages}
                              className="px-2 py-0.5 text-xs rounded bg-bg-input text-text disabled:opacity-30"
                            >{t('common.next')}</button>
                          </div>
                        )}
                      </>
                    ) : (
                      <p className="text-xs text-text-secondary py-2">{t('common.noData')}</p>
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
