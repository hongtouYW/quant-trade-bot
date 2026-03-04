import { useState } from 'react';
import { useApi } from '../../hooks/useApi';
import { Card } from '../../components/common/Card';
import Badge from '../../components/common/Badge';
import { ScrollText } from 'lucide-react';

export default function AuditLog() {
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ action: '', user_type: '' });
  const params = new URLSearchParams({ page, per_page: 30 });
  if (filters.action) params.set('action', filters.action);
  if (filters.user_type) params.set('user_type', filters.user_type);

  const { data, loading } = useApi(`/admin/audit-log?${params}`, { interval: 30000 });

  const logs = data?.logs || [];
  const actions = data?.actions || [];

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-bold">Audit Log</h2>

      <Card className="flex flex-wrap items-center gap-3">
        <select
          value={filters.action}
          onChange={(e) => { setFilters({ ...filters, action: e.target.value }); setPage(1); }}
          className="px-3 py-1.5 bg-bg-input rounded-lg border border-border text-text text-sm"
        >
          <option value="">All Actions</option>
          {actions.map((a) => <option key={a} value={a}>{a}</option>)}
        </select>
        <select
          value={filters.user_type}
          onChange={(e) => { setFilters({ ...filters, user_type: e.target.value }); setPage(1); }}
          className="px-3 py-1.5 bg-bg-input rounded-lg border border-border text-text text-sm"
        >
          <option value="">All Users</option>
          <option value="admin">Admin</option>
          <option value="agent">Agent</option>
        </select>
        <span className="text-xs text-text-secondary ml-auto">
          {data?.total || 0} entries
        </span>
      </Card>

      <Card>
        {!loading && logs.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-text-secondary">
            <ScrollText size={40} className="mb-3 opacity-40" />
            <p className="text-sm">No audit log entries yet</p>
          </div>
        ) : (
          <div className="divide-y divide-border/30">
            {logs.map((log) => (
              <div key={log.id} className="py-3 flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <Badge variant={log.user_type === 'admin' ? 'warning' : 'info'}>
                      {log.user_type}
                    </Badge>
                    <span className="text-sm font-medium">{log.username}</span>
                    <span className="text-sm text-primary font-mono">{log.action}</span>
                    {log.resource && (
                      <span className="text-xs text-text-secondary">{log.resource}</span>
                    )}
                  </div>
                  {log.details && (
                    <pre className="text-xs text-text-secondary bg-bg/50 rounded px-2 py-1 mt-1 overflow-x-auto">
                      {JSON.stringify(log.details, null, 2)}
                    </pre>
                  )}
                </div>
                <div className="text-right shrink-0">
                  <div className="text-xs text-text-secondary">
                    {log.created_at ? new Date(log.created_at).toLocaleString() : '-'}
                  </div>
                  {log.ip_address && (
                    <div className="text-xs text-text-secondary/60">{log.ip_address}</div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

        {data && data.pages > 1 && (
          <div className="flex items-center justify-center gap-2 mt-4 pt-4 border-t border-border/50">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page <= 1}
              className="px-3 py-1 text-sm rounded bg-bg-input text-text disabled:opacity-30"
            >
              Prev
            </button>
            <span className="text-sm text-text-secondary">
              {page} / {data.pages}
            </span>
            <button
              onClick={() => setPage(Math.min(data.pages, page + 1))}
              disabled={page >= data.pages}
              className="px-3 py-1 text-sm rounded bg-bg-input text-text disabled:opacity-30"
            >
              Next
            </button>
          </div>
        )}
      </Card>
    </div>
  );
}
