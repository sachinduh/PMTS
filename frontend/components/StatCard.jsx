import Icon from './Icon';

export default function StatCard({ icon, value, label, color = 'blue' }) {
  return (
    <div className="stat-card">
      <div className={`stat-icon ${color}`}>
        {typeof icon === 'string' ? <Icon name={icon} size={24} /> : icon}
      </div>
      <div className="stat-info">
        <div className="stat-value">{value ?? '—'}</div>
        <div className="stat-label">{label}</div>
      </div>
    </div>
  );
}
