import { NavLink, useNavigate } from 'react-router-dom';
import { getUser, clearAuth } from '../utils/auth';
import Icon from './Icon';

const NAV_ITEMS = {
  director: [
    { to: '/director-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/director-overview', icon: 'search', label: 'Overview' },
    { to: '/director-procurements', icon: 'procurement', label: 'Procurements' },
    { to: '/director-purchase-orders', icon: 'purchase', label: 'Purchase Orders' },
    { to: '/director-delay-alerts', icon: 'alert', label: 'Delay Alerts' },
    { to: '/director-reports', icon: 'reports', label: 'Reports' },
    { to: '/director-audit-logs', icon: 'audit', label: 'Audit Logs' },
  ],
  procurement_officer: [
    { to: '/officer-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/officer-create', icon: 'plus', label: 'New Procurement' },
    { to: '/officer-management', icon: 'procurement', label: 'Manage Procurements' },
    { to: '/officer-requests', icon: 'message', label: 'My Requests' },
    { to: '/officer-purchase-orders', icon: 'purchase', label: 'Purchase Orders' },
  ],
  bec_member: [
    { to: '/bec-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/bec-evaluation', icon: 'briefcase', label: 'Bid Evaluation' },
  ],
  specification_committee: [
    { to: '/specification-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/specification-review', icon: 'document', label: 'Specification Review' },
  ],
  accountant: [
    { to: '/accountant-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/accountant-procurements', icon: 'procurement', label: 'Procurement Tracking' },
    { to: '/accountant-approvals', icon: 'finance', label: 'Financial Approvals' },
  ],
  it_admin: [
    { to: '/it-admin-dashboard', icon: 'dashboard', label: 'Dashboard' },
    { to: '/admin-users', icon: 'users', label: 'User Management' },
    { to: '/admin-roles', icon: 'lock', label: 'Roles & Permissions' },
    { to: '/admin-backup', icon: 'backup', label: 'Backup Management' },
    { to: '/admin-settings', icon: 'settings', label: 'System Settings' },
  ],
};

const COMMON_ITEMS = [
  { to: '/profile', icon: 'user', label: 'Profile Settings' },
  { to: '/notifications', icon: 'notification', label: 'Notifications' },
  { to: '/help', icon: 'help', label: 'Help & Support' },
  { to: '/about', icon: 'document', label: 'About PMTS' },
];

export default function Sidebar() {
  const user = getUser();
  const navigate = useNavigate();
  const role = user?.role || '';
  const items = NAV_ITEMS[role] || [];

  const handleLogout = () => {
    clearAuth();
    navigate('/login');
  };

  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <div className="sidebar-logo"><Icon name="hospital" size={26} /></div>
        <div className="sidebar-brand-text">
          <span className="sidebar-brand-name">PMTS</span>
          <span className="sidebar-brand-sub">Procurement System</span>
        </div>
      </div>

      <div className="sidebar-role-badge">
        {role.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
      </div>

      <nav className="sidebar-nav">
        <div className="sidebar-nav-section">
          <span className="sidebar-nav-section-title">Main Menu</span>
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `sidebar-nav-item ${isActive ? 'active' : ''}`
              }
            >
              <span className="nav-icon"><Icon name={item.icon} size={18} /></span>
              <span className="nav-label">{item.label}</span>
            </NavLink>
          ))}
        </div>

        <div className="sidebar-nav-section">
          <span className="sidebar-nav-section-title">Account</span>
          {COMMON_ITEMS.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `sidebar-nav-item ${isActive ? 'active' : ''}`
              }
            >
              <span className="nav-icon"><Icon name={item.icon} size={18} /></span>
              <span className="nav-label">{item.label}</span>
            </NavLink>
          ))}
        </div>
      </nav>

      <div className="sidebar-footer">
        <button className="sidebar-logout-btn" onClick={handleLogout}>
          <span className="logout-icon-wrap"><Icon name="logout" size={18} /></span>
          <span>Logout</span>
        </button>
      </div>
    </aside>
  );
}
