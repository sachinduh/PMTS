import { Link } from 'react-router-dom';
import { clearAuth } from '../../utils/auth';
import '../../styles/auth.css';

export default function PendingApproval() {
  return (
    <div className="pending-screen">
      <div className="pending-card animate-fade-in">
        <div className="pending-icon"></div>
        <h2>Account Pending Approval</h2>
        <p>
          Your account has been registered successfully. Please wait for the IT Admin
          to review and approve your account. You will be able to log in once approved.
        </p>
        <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '24px' }}>
          If you have any questions, please contact the system administrator.
        </p>
        <button
          className="btn btn-outline"
          onClick={() => {
            clearAuth();
            window.location.href = '/login';
          }}
        >
          Sign Out
        </button>
      </div>
    </div>
  );
}
