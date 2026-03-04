export function Card({ children, className = '' }) {
  return (
    <div className={`bg-bg-card rounded-xl border border-border p-5 ${className}`}>
      {children}
    </div>
  );
}

export function StatCard({ label, value, sub, icon: Icon, color = 'text-primary' }) {
  return (
    <Card>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs text-text-secondary uppercase tracking-wider">{label}</p>
          <p className={`text-2xl font-bold mt-1 ${color}`}>{value}</p>
          {sub && <p className="text-xs text-text-secondary mt-1">{sub}</p>}
        </div>
        {Icon && (
          <div className={`p-2 rounded-lg bg-white/5 ${color}`}>
            <Icon size={20} />
          </div>
        )}
      </div>
    </Card>
  );
}
