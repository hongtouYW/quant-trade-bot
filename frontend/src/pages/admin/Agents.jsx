import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { UserPlus } from 'lucide-react';

export default function Agents() {
  const { data, loading, refetch } = useApi('/admin/agents');
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState({
    username: '', email: '', password: '', display_name: '', profit_share_pct: 20,
  });
  const [error, setError] = useState('');

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
    { key: 'profit_share_pct', label: 'Share %', render: (v) => `${v}%` },
    { key: 'actions', label: '', render: (_, row) => (
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
      </Card>
    </div>
  );
}
