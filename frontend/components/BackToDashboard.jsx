import { useLocation, useNavigate } from 'react-router-dom';
import { getUser } from '../utils/auth';
import Icon from './Icon';

const DASHBOARD_ROUTES = {
  director: '/director-dashboard',
  procurement_officer: '/officer-dashboard',
  tec_member: '/tec-dashboard',
  bec_member: '/bec-dashboard',
  specification_committee: '/specification-dashboard',
  accountant: '/accountant-dashboard',
  it_admin: '/it-admin-dashboard',
};

export default function BackToDashboard() {
  const navigate = useNavigate();
  const location = useLocation();
  const role = getUser()?.role;
  const dashboardPath = DASHBOARD_ROUTES[role];

  if (!dashboardPath || location.pathname === dashboardPath) {
    return null;
  }

  return (
    <div className="back-dashboard-wrap">
      <button
        type="button"
        className="back-dashboard-btn back-dashboard-icon-only"
        onClick={() => navigate(dashboardPath)}
        title="Back to dashboard"
        aria-label="Back to dashboard"
      >
        <Icon name="back" size={21} />
      </button>
    </div>
  );
}
