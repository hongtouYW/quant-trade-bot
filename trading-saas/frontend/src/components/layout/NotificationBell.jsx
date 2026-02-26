import { useState, useRef, useEffect } from 'react';
import { Bell, X, Check, CheckCheck, Trash2 } from 'lucide-react';
import { useApi } from '../../hooks/useApi';
import api from '../../api/client';

const typeIcons = {
  trade: 'text-primary',
  risk: 'text-danger',
  bot: 'text-blue-400',
  billing: 'text-warning',
  system: 'text-text-secondary',
};

export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const panelRef = useRef(null);

  // Poll unread count every 10s
  const { data: countData, refetch: refetchCount } = useApi(
    '/agent/notifications/unread-count',
    { interval: 10000 }
  );

  // Load full list only when panel is open
  const { data: listData, refetch: refetchList } = useApi(
    '/agent/notifications/?limit=30',
    { interval: 15000, enabled: open }
  );

  const unread = countData?.unread_count || 0;
  const notifications = listData?.notifications || [];

  // Close panel on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const togglePanel = () => {
    setOpen(!open);
    if (!open) refetchList();
  };

  const markRead = async (id) => {
    await api.post(`/agent/notifications/${id}/read`);
    refetchList();
    refetchCount();
  };

  const markAllRead = async () => {
    await api.post('/agent/notifications/read-all');
    refetchList();
    refetchCount();
  };

  const deleteNotif = async (id) => {
    await api.delete(`/agent/notifications/${id}`);
    refetchList();
    refetchCount();
  };

  const timeAgo = (iso) => {
    if (!iso) return '';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  };

  return (
    <div className="relative" ref={panelRef}>
      <button
        onClick={togglePanel}
        className="relative p-1.5 rounded-lg hover:bg-white/5 transition-colors"
      >
        <Bell size={18} className="text-text-secondary" />
        {unread > 0 && (
          <span className="absolute -top-0.5 -right-0.5 w-4 h-4 bg-danger text-white text-[10px] font-bold rounded-full flex items-center justify-center">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-80 max-h-[28rem] bg-bg-card border border-border rounded-xl shadow-xl z-50 flex flex-col overflow-hidden">
          {/* Header */}
          <div className="flex items-center justify-between px-3 py-2 border-b border-border">
            <span className="text-sm font-semibold">Notifications</span>
            <div className="flex items-center gap-1">
              {unread > 0 && (
                <button
                  onClick={markAllRead}
                  className="text-xs text-primary hover:text-primary/80 flex items-center gap-1 px-1.5 py-0.5 rounded hover:bg-white/5"
                  title="Mark all as read"
                >
                  <CheckCheck size={12} /> Read all
                </button>
              )}
              <button onClick={() => setOpen(false)} className="p-0.5 hover:bg-white/5 rounded">
                <X size={14} className="text-text-secondary" />
              </button>
            </div>
          </div>

          {/* List */}
          <div className="flex-1 overflow-y-auto">
            {notifications.length > 0 ? (
              notifications.map((n) => (
                <div
                  key={n.id}
                  className={`flex items-start gap-2 px-3 py-2 border-b border-border/30 hover:bg-white/3 transition-colors ${
                    !n.is_read ? 'bg-primary/5' : ''
                  }`}
                >
                  <div className={`w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 ${
                    !n.is_read ? 'bg-primary' : 'bg-transparent'
                  }`} />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                      <span className={`text-[10px] uppercase font-bold ${typeIcons[n.type] || 'text-text-secondary'}`}>
                        {n.type}
                      </span>
                      <span className="text-[10px] text-text-secondary ml-auto">{timeAgo(n.created_at)}</span>
                    </div>
                    <p className="text-xs font-medium text-text truncate">{n.title}</p>
                    {n.message && (
                      <p className="text-[11px] text-text-secondary truncate">{n.message}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-0.5 shrink-0">
                    {!n.is_read && (
                      <button onClick={() => markRead(n.id)} className="p-0.5 rounded hover:bg-white/10" title="Mark read">
                        <Check size={12} className="text-text-secondary" />
                      </button>
                    )}
                    <button onClick={() => deleteNotif(n.id)} className="p-0.5 rounded hover:bg-danger/15" title="Delete">
                      <Trash2 size={12} className="text-text-secondary hover:text-danger" />
                    </button>
                  </div>
                </div>
              ))
            ) : (
              <div className="py-8 text-center text-xs text-text-secondary">
                No notifications yet
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
