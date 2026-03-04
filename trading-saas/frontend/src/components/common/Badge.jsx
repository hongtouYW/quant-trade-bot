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
  const map = {
    running: { label: 'Running', variant: 'success' },
    stopped: { label: 'Stopped', variant: 'neutral' },
    paused: { label: 'Paused', variant: 'warning' },
    error: { label: 'Error', variant: 'danger' },
    OPEN: { label: 'Open', variant: 'info' },
    CLOSED: { label: 'Closed', variant: 'neutral' },
    open: { label: 'Open', variant: 'info' },
    pending: { label: 'Pending', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    paid: { label: 'Paid', variant: 'success' },
  };
  const { label, variant } = map[status] || { label: status, variant: 'neutral' };
  return <Badge variant={variant}>{label}</Badge>;
}
