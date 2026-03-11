import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../contexts/LanguageContext';
import NotificationBell from './NotificationBell';
import {
  LayoutDashboard, Users, Bot, Receipt, Settings,
  BarChart3, History, Briefcase, LogOut, TrendingUp, ScrollText,
  Menu, X, MoreHorizontal, HelpCircle, Globe,
} from 'lucide-react';

export default function Sidebar() {
  const [open, setOpen] = useState(false);
  const { user, logout } = useAuth();
  const { t, lang, setLang } = useLanguage();
  const navigate = useNavigate();

  const adminNav = [
    { to: '/admin', icon: LayoutDashboard, label: t('nav.dashboard') },
    { to: '/admin/agents', icon: Users, label: t('nav.agents') },
    { to: '/admin/bots', icon: Bot, label: t('nav.bots') },
    { to: '/admin/billing', icon: Receipt, label: t('nav.revenue') },
    { to: '/admin/audit', icon: ScrollText, label: t('nav.auditLog') },
  ];

  const agentNav = [
    { to: '/agent', icon: LayoutDashboard, label: t('nav.dashboard') },
    { to: '/agent/positions', icon: TrendingUp, label: t('nav.positions') },
    { to: '/agent/history', icon: History, label: t('nav.history') },
    { to: '/agent/stats', icon: BarChart3, label: t('nav.statistics') },
    { to: '/agent/bot', icon: Bot, label: t('nav.bot') },
    { to: '/agent/billing', icon: Receipt, label: t('nav.billing') },
    { to: '/agent/settings', icon: Settings, label: t('nav.settings') },
    { to: '/agent/faq', icon: HelpCircle, label: t('nav.faq') },
  ];

  const agentBottomTabs = [
    { to: '/agent', icon: LayoutDashboard, label: t('nav.home') },
    { to: '/agent/positions', icon: TrendingUp, label: t('nav.positions') },
    { to: '/agent/bot', icon: Bot, label: t('nav.bot') },
    { to: '/agent/history', icon: History, label: t('nav.history') },
    { to: '/agent/settings', icon: Settings, label: t('nav.settings') },
  ];

  const adminBottomTabs = [
    { to: '/admin', icon: LayoutDashboard, label: t('nav.home') },
    { to: '/admin/agents', icon: Users, label: t('nav.agents') },
    { to: '/admin/bots', icon: Bot, label: t('nav.bots') },
    { to: '/admin/billing', icon: Receipt, label: t('nav.revenue') },
    { to: '/admin/audit', icon: ScrollText, label: t('nav.audit') },
  ];

  const nav = user?.role === 'admin' ? adminNav : agentNav;
  const bottomTabs = user?.role === 'admin' ? adminBottomTabs : agentBottomTabs;

  const handleLogout = () => {
    const loginPath = `/${user?.role || 'agent'}/login`;
    // Clean up socket before clearing auth
    import('../../hooks/useSocket').then(m => m.disconnectSocket());
    logout();
    navigate(loginPath);
  };

  const toggleLang = () => setLang(lang === 'en' ? 'zh' : 'en');

  return (
    <>
      {/* Mobile top bar */}
      <div className="lg:hidden fixed top-0 left-0 right-0 h-14 bg-bg-card border-b border-border flex items-center px-4 z-20">
        <h1 className="text-sm font-bold text-primary flex items-center gap-1.5">
          <Briefcase size={16} /> {t('nav.brand')}
        </h1>
        <div className="flex items-center gap-2 ml-auto">
          <button onClick={toggleLang} className="px-1.5 py-0.5 text-xs rounded border border-border text-text-secondary hover:text-text transition-colors">
            {lang === 'en' ? '中' : 'EN'}
          </button>
          {user?.role === 'agent' && <NotificationBell />}
          <span className="text-xs text-text-secondary">
            {user?.role === 'admin' ? t('nav.adminPanel') : user?.username}
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
              {t('nav.brand')}
            </h1>
            <p className="text-xs text-text-secondary mt-1">
              {user?.role === 'admin' ? t('nav.adminPanel') : user?.username}
            </p>
          </div>
          <div className="flex items-center gap-1">
            <button
              onClick={toggleLang}
              className="px-1.5 py-0.5 text-xs rounded border border-border text-text-secondary hover:text-text hover:border-primary/50 transition-colors"
              title={lang === 'en' ? '切换中文' : 'Switch to English'}
            >
              {lang === 'en' ? '中' : 'EN'}
            </button>
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
            {t('common.logout')}
          </button>
        </div>
      </aside>
    </>
  );
}
