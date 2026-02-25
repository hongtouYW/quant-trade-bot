import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { Play, Square, RotateCcw } from 'lucide-react';

export default function Bots() {
  const { data, loading, refetch } = useApi('/admin/bots/status', { interval: 5000 });

  const action = async (agentId, act) => {
    try {
      await api.post(`/admin/bots/${agentId}/${act}`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.error || `Failed to ${act}`);
    }
  };

  const columns = [
    { key: 'agent_id', label: 'ID' },
    { key: 'username', label: 'Agent' },
    { key: 'status', label: 'Status', render: (v) => <StatusBadge status={v} /> },
    { key: 'risk_score', label: 'Risk', render: (v) => {
      const color = v >= 7 ? 'text-danger' : v >= 4 ? 'text-warning' : 'text-success';
      return <span className={`font-mono ${color}`}>{v}/10</span>;
    }},
    { key: 'open_positions', label: 'Positions', render: (v) => v ?? '-' },
    { key: 'last_scan', label: 'Last Scan', render: (v) =>
      v ? new Date(v).toLocaleTimeString() : '-'
    },
    { key: 'actions', label: '', render: (_, row) => (
      <div className="flex items-center gap-1">
        {row.status !== 'running' && (
          <button
            onClick={() => action(row.agent_id, 'start')}
            className="p-1.5 rounded bg-success/15 text-success hover:bg-success/25"
            title="Start"
          >
            <Play size={14} />
          </button>
        )}
        {row.status === 'running' && (
          <button
            onClick={() => action(row.agent_id, 'stop')}
            className="p-1.5 rounded bg-danger/15 text-danger hover:bg-danger/25"
            title="Stop"
          >
            <Square size={14} />
          </button>
        )}
        <button
          onClick={() => action(row.agent_id, 'restart')}
          className="p-1.5 rounded bg-warning/15 text-warning hover:bg-warning/25"
          title="Restart"
        >
          <RotateCcw size={14} />
        </button>
      </div>
    )},
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Bot Management</h2>
      <Card>
        <Table
          columns={columns}
          data={data?.bots || []}
          emptyText={loading ? 'Loading...' : 'No bots configured'}
        />
      </Card>
    </div>
  );
}
