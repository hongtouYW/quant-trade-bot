import { useLanguage } from '../../contexts/LanguageContext';

const variants = {
  success: 'bg-success/15 text-success',
  danger: 'bg-danger/15 text-danger',
  warning: 'bg-warning/15 text-warning',
  info: 'bg-primary/15 text-primary',
  neutral: 'bg-white/10 text-text-secondary',
};

export default function Badge({ children, variant = 'neutral' }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${variants[variant]}`}>
      {children}
    </span>
  );
}

export function StatusBadge({ status }) {
  const { t } = useLanguage();
  const map = {
    running: { label: t('status.running'), variant: 'success' },
    stopped: { label: t('status.stopped'), variant: 'neutral' },
    paused: { label: t('status.paused'), variant: 'warning' },
    error: { label: t('status.error'), variant: 'danger' },
    OPEN: { label: t('status.open'), variant: 'info' },
    CLOSED: { label: t('status.closed'), variant: 'neutral' },
    open: { label: t('status.open'), variant: 'info' },
    pending: { label: t('status.pending'), variant: 'warning' },
    approved: { label: t('status.approved'), variant: 'success' },
    paid: { label: t('status.paid'), variant: 'success' },
  };
  const { label, variant } = map[status] || { label: status, variant: 'neutral' };
  return <Badge variant={variant}>{label}</Badge>;
}
