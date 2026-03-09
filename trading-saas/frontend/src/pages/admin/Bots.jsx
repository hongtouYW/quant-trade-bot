import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import { StatusBadge } from '../../components/common/Badge';
import Table from '../../components/common/Table';
import api from '../../api/client';
import { Play, Square, RotateCcw } from 'lucide-react';
import { useLanguage } from '../../contexts/LanguageContext';
import { formatTime } from '../../utils/formatDate';

export default function Bots() {
  const { t } = useLanguage();
  const { data, loading, refetch } = useApi('/admin/bots/status', { interval: 5000 });

  const action = async (agentId, act) => {
    try {
      await api.post(`/admin/bots/${agentId}/${act}`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.error || t('admin.actionFailed'));
    }
  };

  const columns = [
    { key: 'agent_id', label: t('admin.id') },
    { key: 'username', label: t('admin.agent') },
    { key: 'status', label: t('dashboard.status'), render: (v) => <StatusBadge status={v} /> },
    { key: 'risk_score', label: t('admin.risk'), render: (v) => {
      const color = v >= 7 ? 'text-danger' : v >= 4 ? 'text-warning' : 'text-success';
      return <span className={`font-mono ${color}`}>{v}/10</span>;
    }},
    { key: 'open_positions', label: t('nav.positions'), render: (v) => v ?? '-' },
    { key: 'last_scan', label: t('admin.lastScan'), render: (v) =>
      v ? formatTime(v) : '-'
    },
    { key: 'actions', label: '', render: (_, row) => (
      <div className="flex items-center gap-1">
        {row.status !== 'running' && (
          <button
            onClick={() => action(row.agent_id, 'start')}
            className="p-1.5 rounded bg-success/15 text-success hover:bg-success/25"
            title={t('bot.startBot')}
          >
            <Play size={14} />
          </button>
        )}
        {row.status === 'running' && (
          <button
            onClick={() => action(row.agent_id, 'stop')}
            className="p-1.5 rounded bg-danger/15 text-danger hover:bg-danger/25"
            title={t('bot.stop')}
          >
            <Square size={14} />
          </button>
        )}
        <button
          onClick={() => action(row.agent_id, 'restart')}
          className="p-1.5 rounded bg-warning/15 text-warning hover:bg-warning/25"
          title={t('admin.restart')}
        >
          <RotateCcw size={14} />
        </button>
      </div>
    )},
  ];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">{t('admin.botManagement')}</h2>
      <Card>
        <Table
          columns={columns}
          data={data?.bots || []}
          emptyText={loading ? t('common.loading') : t('admin.noBots')}
        />
      </Card>
    </div>
  );
}
