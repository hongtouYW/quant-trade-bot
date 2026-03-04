export default function PnlValue({ value, suffix = 'U', className = '' }) {
  if (value == null) return <span className="text-text-secondary">-</span>;
  const num = Number(value);
  const color = num > 0 ? 'text-long' : num < 0 ? 'text-short' : 'text-text-secondary';
  const sign = num > 0 ? '+' : '';
  return (
    <span className={`font-mono ${color} ${className}`}>
      {sign}{num.toFixed(2)}{suffix}
    </span>
  );
}
