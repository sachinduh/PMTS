import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import Icon from '../../components/Icon';
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  getAllUsers,
  getPendingUsers,
  approveUser,
  rejectUser,
  deleteUser,
} from '../../api/api';
import { getUser } from '../../utils/auth';
import '../../styles/dashboard.css';
import '../../styles/tables.css';

const ROLE_LABELS = {
  pending: 'Pending',
  director: 'Director',
  accountant: 'Accountant',
  procurement_officer: 'Procurement Officer',
  bec_member: 'BEC Committee Member',
  specification_committee: 'Specification Committee Member',
  it_admin: 'IT Admin',
};

const APPROVABLE_ROLES = [
  { value: 'director', label: 'Director' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'procurement_officer', label: 'Procurement Officer' },
  { value: 'bec_member', label: 'BEC Committee Member' },
  { value: 'specification_committee', label: 'Specification Committee Member' },
];
const APPROVABLE_ROLE_VALUES = APPROVABLE_ROLES.map((role) => role.value);

const roleLabel = (role) => ROLE_LABELS[role] || role || 'Pending';

function StatLink({ to, children }) {
  return <Link to={to} className="stat-link">{children}</Link>;
}

export default function ITAdminDashboard() {
  const navigate = useNavigate();
  const currentUser = getUser();
  const [stats, setStats] = useState({ total: 0, pending: 0, active: 0, removed: 0 });
  const [users, setUsers] = useState([]);
  const [pendingUsers, setPendingUsers] = useState([]);
  const [roleSelections, setRoleSelections] = useState({});
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(null);
  const [message, setMessage] = useState('');

  const loadDashboard = async () => {
    setLoading(true);
    setMessage('');

    try {
      const [allRes, pendingRes] = await Promise.all([getAllUsers(), getPendingUsers()]);
      const all = allRes.data?.data || [];
      const pending = pendingRes.data?.data || [];

      setUsers(all);
      setPendingUsers(pending);
      setStats({
        total: all.length,
        pending: pending.length,
        active: all.filter((u) => u.status === 'active').length,
        removed: all.filter((u) => u.status === 'rejected').length,
      });

      const selections = {};
      [...all, ...pending].forEach((user) => {
        const preferredRole = user.role && user.role !== 'pending' ? user.role : (user.requested_role || '');
        selections[user.id] = APPROVABLE_ROLE_VALUES.includes(preferredRole) ? preferredRole : '';
      });
      setRoleSelections(selections);
    } catch (error) {
      console.error('Admin dashboard load error:', error);
      setMessage('Failed to load admin dashboard data. Please check backend connection.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDashboard();
  }, []);

  const isCurrentUser = (userId) => Number(currentUser?.id) === Number(userId);

  const approveFromDashboard = async (userId) => {
    const role = roleSelections[userId];

    if (!role) {
      alert('Please select a role before approving this user.');
      return;
    }

    if (!APPROVABLE_ROLE_VALUES.includes(role)) {
      alert('IT Admin cannot be assigned through registration approval. Select a normal hospital work role.');
      return;
    }

    setActionLoading(`approve-${userId}`);
    try {
      await approveUser({ user_id: userId, role });
      setMessage('User approved successfully. Role is saved, status is active, and the user can login.');
      await loadDashboard();
    } catch (error) {
      console.error('Approve user error:', error);
      setMessage(error.response?.data?.message || 'User approval failed.');
    }
    setActionLoading(null);
  };

  const rejectFromDashboard = async (userId) => {
    if (!window.confirm('Reject this user registration?')) return;

    setActionLoading(`reject-${userId}`);
    try {
      await rejectUser({ user_id: userId });
      setMessage('User rejected successfully. This user cannot login.');
      await loadDashboard();
    } catch (error) {
      console.error('Reject user error:', error);
      setMessage(error.response?.data?.message || 'User rejection failed.');
    }
    setActionLoading(null);
  };

  const removeFromDashboard = async (user) => {
    if (isCurrentUser(user.id)) {
      alert('You cannot remove your own IT Admin account.');
      return;
    }

    if (!window.confirm(`Remove ${user.full_name || user.email}? This will deactivate the account and block login.`)) return;

    setActionLoading(`remove-${user.id}`);
    try {
      await deleteUser({ user_id: user.id, hard_delete: false });
      setMessage('User removed successfully. This account is deactivated and cannot login.');
      await loadDashboard();
    } catch (error) {
      console.error('Remove user error:', error);
      setMessage(error.response?.data?.message || 'User removal failed.');
    }
    setActionLoading(null);
  };

  const recentUsers = users.slice(0, 5);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text">
            <h2>IT Admin Dashboard</h2>
            <p>Approve requested roles securely. IT Admin cannot be requested or granted from public registration.</p>
          </div>
          <div className="welcome-icon"><Icon name="settings" size={34} /></div>
        </div>

        {message && <div className="alert alert-info">{message}</div>}

        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : (
          <>
            <div className="stats-grid">
              <StatLink to="/admin-users">
                <StatCard icon="users" value={stats.total} label="Total Users" color="blue" />
              </StatLink>
              <StatLink to="/admin-users?tab=pending">
                <StatCard icon="pending" value={stats.pending} label="Pending Approval" color="yellow" />
              </StatLink>
              <StatLink to="/admin-users?tab=active">
                <StatCard icon="success" value={stats.active} label="Active Users" color="green" />
              </StatLink>
              <StatLink to="/admin-users?tab=removed">
                <StatCard icon="trash" value={stats.removed} label="Removed Users" color="red" />
              </StatLink>
            </div>

            <div className="card" style={{ marginTop: 20 }}>
              <div className="section-title" style={{ marginBottom: 12 }}>
                Pending Users - Assign Role & Activate Login
              </div>

              {pendingUsers.length === 0 ? (
                <div className="empty-state">
                  <div className="empty-state-icon"><Icon name="success" size={34} /></div>
                  <div className="empty-state-text">No pending users to approve.</div>
                </div>
              ) : (
                <div className="table-wrapper">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Requested Role</th>
                        <th>Approval Role</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pendingUsers.map((user) => (
                        <tr key={user.id}>
                          <td>{user.full_name || '—'}</td>
                          <td>{user.email || '—'}</td>
                          <td>{user.department || user.organization || '—'}</td>
                          <td>
                            {user.requested_role ? (
                              <span className="badge badge-pending">{roleLabel(user.requested_role)}</span>
                            ) : (
                              <span style={{ color: 'var(--text-muted)' }}>—</span>
                            )}
                          </td>
                          <td>
                            <select
                              value={roleSelections[user.id] || ''}
                              onChange={(e) =>
                                setRoleSelections({ ...roleSelections, [user.id]: e.target.value })
                              }
                              style={{
                                padding: '6px 10px',
                                border: '1px solid var(--border)',
                                borderRadius: '6px',
                                minWidth: 180,
                              }}
                            >
                              <option value="">Select Role</option>
                              {APPROVABLE_ROLES.map((role) => (
                                <option key={role.value} value={role.value}>{role.label}</option>
                              ))}
                            </select>
                          </td>
                          <td>
                            <button
                              className="action-btn approve"
                              disabled={actionLoading === `approve-${user.id}`}
                              onClick={() => approveFromDashboard(user.id)}
                            >
                              {actionLoading === `approve-${user.id}` ? '...' : 'Approve & Activate'}
                            </button>
                            <button
                              className="action-btn reject"
                              disabled={actionLoading === `reject-${user.id}`}
                              onClick={() => rejectFromDashboard(user.id)}
                            >
                              {actionLoading === `reject-${user.id}` ? '...' : 'Reject'}
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="card" style={{ marginTop: 20 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                <div className="section-title">Recent Users</div>
                <button className="btn btn-outline" onClick={() => navigate('/admin-users')}>Open User Management</button>
              </div>

              <div className="table-wrapper">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentUsers.map((user) => {
                      const self = isCurrentUser(user.id);
                      const removed = user.status === 'rejected';

                      return (
                        <tr key={user.id}>
                          <td>{user.full_name || '—'} {self && <span className="badge badge-success">You</span>}</td>
                          <td>{user.email || '—'}</td>
                          <td>{roleLabel(user.role)}</td>
                          <td>
                            <span className={`badge ${user.status === 'active' ? 'badge-success' : user.status === 'rejected' ? 'badge-danger' : 'badge-pending'}`}>
                              {removed ? 'removed' : user.status || 'pending'}
                            </span>
                          </td>
                          <td>
                            {!self && !removed ? (
                              <button
                                className="action-btn reject"
                                disabled={actionLoading === `remove-${user.id}`}
                                onClick={() => removeFromDashboard(user)}
                              >
                                {actionLoading === `remove-${user.id}` ? '...' : 'Remove'}
                              </button>
                            ) : (
                              <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                                {self ? 'Current IT Admin' : 'Cannot login'}
                              </span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}

        <div className="card" style={{ marginTop: 20 }}>
          <div className="section-title" style={{ marginBottom: '16px' }}>
            Quick Actions
          </div>
          <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
            <button onClick={() => navigate('/admin-users')} className="btn btn-primary quick-action-row">
              <Icon name="users" size={18} /> User Management
            </button>
            <button onClick={() => navigate('/admin-roles')} className="btn btn-outline quick-action-row">
              <Icon name="lock" size={18} /> Roles & Permissions
            </button>
            <button onClick={() => navigate('/admin-backup')} className="btn btn-outline quick-action-row">
              <Icon name="backup" size={18} /> Backup
            </button>
            <button onClick={() => navigate('/admin-settings')} className="btn btn-outline quick-action-row">
              <Icon name="settings" size={18} /> Settings
            </button>
          </div>
        </div>
      </div>
    </Layout>
  );
}
