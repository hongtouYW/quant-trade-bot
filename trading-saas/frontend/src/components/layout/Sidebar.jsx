import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import NotificationBell from './NotificationBell';
import {
  LayoutDashboard, Users, Bot, Receipt, Settings,
  BarChart3, History, Briefcase, LogOut, TrendingUp, ScrollText,
  Menu, X, MoreHorizontal, HelpCircle,
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
  { to: '/agent/faq', icon: HelpCircle, label: 'FAQ & Help' },
];

// Bottom tab: 5 core items for mobile (agent)
const agentBottomTabs = [
  { to: '/agent', icon: LayoutDashboard, label: 'Home' },
  { to: '/agent/positions', icon: TrendingUp, label: 'Positions' },
  { to: '/agent/bot', icon: Bot, label: 'Bot' },
  { to: '/agent/history', icon: History, label: 'History' },
  { to: '/agent/settings', icon: Settings, label: 'Settings' },
];

// Bottom tab: admin
const adminBottomTabs = [
  { to: '/admin', icon: LayoutDashboard, label: 'Home' },
  { to: '/admin/agents', icon: Users, label: 'Agents' },
  { to: '/admin/bots', icon: Bot, label: 'Bots' },
  { to: '/admin/billing', icon: Receipt, label: 'Revenue' },
  { to: '/admin/audit', icon: ScrollText, label: 'Audit' },
];

export default function Sidebar() {
  const [open, setOpen] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const nav = user?.role === 'admin' ? adminNav : agentNav;
  const bottomTabs = user?.role === 'admin' ? adminBottomTabs : agentBottomTabs;

  const handleLogout = () => {
    const loginPath = `/${user?.role || 'agent'}/login`;
    logout();
    navigate(loginPath);
  };

  return (
    <>
      {/* Mobile top bar */}
      <div className="lg:hidden fixed top-0 left-0 right-0 h-14 bg-bg-card border-b border-border flex items-center px-4 z-20">
        <h1 className="text-sm font-bold text-primary flex items-center gap-1.5">
          <Briefcase size={16} /> Trading SaaS
        </h1>
        <div className="flex items-center gap-2 ml-auto">
          {user?.role === 'agent' && <NotificationBell />}
          <span className="text-xs text-text-secondary">
            {user?.role === 'admin' ? 'Admin' : user?.username}
          </span>
        </div>
      </div>

      {/* Mobile bottom tab bar */}
      <nav className="lg:hidden fixed bottom-0 left-0 right-0 h-16 bg-bg-card border-t border-border flex items-center justify-around z-20 pb-safe">
        {bottomTabs.map(({ to, icon: Icon, label }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/admin' || to === '/agent'}
            className={({ isActive }) =>
              `flex flex-col items-center gap-0.5 px-2 py-1 text-[10px] transition-colors ${
                isActive
                  ? 'text-primary'
                  : 'text-text-secondary'
              }`
            }
          >
            <Icon size={20} />
            {label}
          </NavLink>
        ))}
      </nav>

      {/* Desktop Sidebar */}
      <aside className="hidden lg:flex fixed left-0 top-0 h-screen w-56 bg-bg-card border-r border-border flex-col z-40">
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
            {user?.role === 'agent' && <NotificationBell />}
          </div>
        </div>

        <nav className="flex-1 p-2 space-y-0.5 overflow-y-auto">
          {nav.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/admin' || to === '/agent'}
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
