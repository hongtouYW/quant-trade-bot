import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { UserPlus, ChevronDown, ChevronUp, Key, MessageCircle, Bot, ShieldCheck } from 'lucide-react';

export default function Agents() {
  const { data, loading, refetch } = useApi('/admin/agents');
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState({
    username: '', email: '', password: '', display_name: '', profit_share_pct: 20,
  });
  const [error, setError] = useState('');
  const [expandedId, setExpandedId] = useState(null);
  const [detail, setDetail] = useState(null);

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
      setError(err.response?.data?.error || 'Failed to create agent');
    }
  };

  const toggleTrading = async (agentId) => {
    await api.post(`/admin/agents/${agentId}/toggle-trading`);
    refetch();
  };

  const StatusIcon = ({ ok, label }) => (
    <span className={`inline-flex items-center gap-1 text-xs ${ok ? 'text-success' : 'text-text-tertiary'}`} title={label}>
      {ok ? '✓' : '✗'}
    </span>
  );

  const columns = [
    { key: 'id', label: 'ID' },
    { key: 'username', label: 'Username' },
    { key: 'display_name', label: 'Name', render: (v) => v || '-' },
    { key: 'is_active', label: 'Active', render: (v) => (
      <Badge variant={v ? 'success' : 'danger'}>{v ? 'Yes' : 'No'}</Badge>
    )},
    { key: 'is_trading_enabled', label: 'Trading', render: (v) => (
      <Badge variant={v ? 'success' : 'neutral'}>{v ? 'Enabled' : 'Disabled'}</Badge>
    )},
    { key: 'config_status', label: 'Config', render: (_, row) => (
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
    { key: 'profit_share_pct', label: 'Share %', render: (v) => `${v}%` },
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
          {row.is_trading_enabled ? 'Disable' : 'Enable'}
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
        <h2 className="text-xl font-bold">Agent Management</h2>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors"
        >
          <UserPlus size={16} />
          New Agent
        </button>
      </div>

      {showCreate && (
        <Card>
          <h3 className="text-sm font-semibold mb-3">Create New Agent</h3>
          <form onSubmit={handleCreate} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {['username', 'email', 'password', 'display_name'].map((field) => (
              <div key={field}>
                <label className="block text-xs text-text-secondary mb-1">{field.replace('_', ' ')}</label>
                <input
                  type={field === 'password' ? 'password' : field === 'email' ? 'email' : 'text'}
                  value={form[field]}
                  onChange={(e) => setForm({ ...form, [field]: e.target.value })}
                  required={field !== 'display_name'}
                  className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                />
              </div>
            ))}
            <div>
              <label className="block text-xs text-text-secondary mb-1">Profit Share %</label>
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
                Create
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
          emptyText={loading ? 'Loading...' : 'No agents yet'}
        />

        {expandedId && (
          <div className="border-t border-border mt-2 pt-4 px-2 pb-2">
            {(() => {
              const agent = (data?.agents || []).find(a => a.id === expandedId);
              if (!agent) return null;
              return (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                  <div>
                    <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">Account</h4>
                    <div className="space-y-1">
                      <p><span className="text-text-secondary">Email:</span> {agent.email}</p>
                      <p><span className="text-text-secondary">Created:</span> {agent.created_at ? new Date(agent.created_at).toLocaleDateString() : '-'}</p>
                      <p><span className="text-text-secondary">Last Login:</span> {agent.last_login_at ? new Date(agent.last_login_at).toLocaleString() : 'Never'}</p>
                    </div>
                  </div>

                  <div>
                    <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">Configuration</h4>
                    <div className="space-y-1">
                      <p className="flex items-center gap-2">
                        <Key size={14} className={agent.has_api_key ? 'text-success' : 'text-danger'} />
                        <span>API Key: {agent.has_api_key ? 'Configured' : 'Not set'}</span>
                        {agent.has_api_key && (
                          <Badge variant={agent.api_key_verified ? 'success' : 'warning'} className="text-[10px]">
                            {agent.api_key_verified ? 'Verified' : 'Unverified'}
                          </Badge>
                        )}
                      </p>
                      <p className="flex items-center gap-2">
                        <MessageCircle size={14} className={agent.has_telegram ? 'text-success' : 'text-text-tertiary'} />
                        <span>Telegram: {agent.has_telegram ? 'Configured' : 'Not set'}</span>
                      </p>
                      <p className="flex items-center gap-2">
                        <Bot size={14} className={agent.bot_status === 'running' ? 'text-success' : 'text-text-tertiary'} />
                        <span>Bot: <Badge variant={agent.bot_status === 'running' ? 'success' : agent.bot_status === 'error' ? 'danger' : 'neutral'}>{agent.bot_status || 'stopped'}</Badge></span>
                      </p>
                    </div>
                  </div>

                  {detail?.trade_stats && (
                    <div>
                      <h4 className="text-xs font-semibold text-text-secondary mb-2 uppercase">Trade Stats</h4>
                      <div className="space-y-1">
                        <p><span className="text-text-secondary">Total Trades:</span> {detail.trade_stats.total_trades}</p>
                        <p><span className="text-text-secondary">Win Trades:</span> {detail.trade_stats.win_trades}</p>
                        <p>
                          <span className="text-text-secondary">Total PnL:</span>{' '}
                          <span className={detail.trade_stats.total_pnl >= 0 ? 'text-success' : 'text-danger'}>
                            {detail.trade_stats.total_pnl >= 0 ? '+' : ''}{Number(detail.trade_stats.total_pnl).toFixed(2)}U
                          </span>
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              );
            })()}
          </div>
        )}
      </Card>
    </div>
  );
}
