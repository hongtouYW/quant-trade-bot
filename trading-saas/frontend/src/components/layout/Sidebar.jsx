import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import NotificationBell from './NotificationBell';
import {
  LayoutDashboard, Users, Bot, Receipt, Settings,
  BarChart3, History, Briefcase, LogOut, TrendingUp, ScrollText,
  Menu, X,
} from 'lucide-react';

const adminNav = [
  { to: '/admin', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/admin/agents', icon: Users, label: 'Agents' },
  { to: '/admin/bots', icon: Bot, label: 'Bots' },
  { to: '/admin/billing', icon: Receipt, label: 'Revenue' },
  { to: '/admin/audit', icon: ScrollText, label: 'Audit Log' },
];

const agentNav = [
  { to: '/agent', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/agent/positions', icon: TrendingUp, label: 'Positions' },
  { to: '/agent/history', icon: History, label: 'History' },
  { to: '/agent/stats', icon: BarChart3, label: 'Statistics' },
  { to: '/agent/bot', icon: Bot, label: 'Bot' },
  { to: '/agent/billing', icon: Receipt, label: 'Billing' },
  { to: '/agent/settings', icon: Settings, label: 'Settings' },
];

export default function Sidebar() {
  const [open, setOpen] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const nav = user?.role === 'admin' ? adminNav : agentNav;

  const handleLogout = () => {
    const loginPath = `/${user?.role || 'agent'}/login`;
    logout();
    navigate(loginPath);
  };

  return (
    <>
      {/* Mobile top bar */}
      <div className="lg:hidden fixed top-0 left-0 right-0 h-14 bg-bg-card border-b border-border flex items-center px-4 z-20">
        <button onClick={() => setOpen(true)} className="text-text-secondary hover:text-text">
          <Menu size={22} />
        </button>
        <h1 className="text-sm font-bold text-primary ml-3 flex items-center gap-1.5">
          <Briefcase size={16} /> Trading SaaS
        </h1>
        <div className="flex items-center gap-2 ml-auto">
          {user?.role === 'agent' && <NotificationBell />}
          <span className="text-xs text-text-secondary">
            {user?.role === 'admin' ? 'Admin' : user?.username}
          </span>
        </div>
      </div>

      {/* Overlay */}
      {open && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-30"
          onClick={() => setOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside className={`
        fixed left-0 top-0 h-screen w-56 bg-bg-card border-r border-border flex flex-col z-40
        transition-transform duration-200
        lg:translate-x-0
        ${open ? 'translate-x-0' : '-translate-x-full'}
      `}>
        <div className="p-4 border-b border-border flex items-center justify-between">
          <div>
            <h1 className="text-lg font-bold text-primary flex items-center gap-2">
              <Briefcase size={20} />
              Trading SaaS
            </h1>
            <p className="text-xs text-text-secondary mt-1">
              {user?.role === 'admin' ? 'Admin Panel' : user?.username}
            </p>
          </div>
          <div className="flex items-center gap-1">
            {user?.role === 'agent' && <div className="hidden lg:block"><NotificationBell /></div>}
            <button onClick={() => setOpen(false)} className="lg:hidden text-text-secondary hover:text-text">
              <X size={18} />
            </button>
          </div>
        </div>

        <nav className="flex-1 p-2 space-y-0.5 overflow-y-auto">
          {nav.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/admin' || to === '/agent'}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                  isActive
                    ? 'bg-primary/15 text-primary font-medium'
                    : 'text-text-secondary hover:bg-white/5 hover:text-text'
                }`
              }
            >
              <Icon size={18} />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="p-2 border-t border-border">
          <button
            onClick={handleLogout}
            className="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-text-secondary hover:bg-danger/15 hover:text-danger w-full transition-colors"
          >
            <LogOut size={18} />
            Logout
          </button>
        </div>
      </aside>
    </>
  );
}
