import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Layout from '../../components/Layout';
import Icon from '../../components/Icon';
import {
  getAllUsers,
  getPendingUsers,
  approveUser,
  rejectUser,
  deleteUser,
  unlockUser,
} from '../../api/api';
import { getUser } from '../../utils/auth';
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

const isLockedUser = (user) => Number(user.account_locked) === 1;
const isPendingUser = (user) => (user.status === 'pending' || !user.status) && (!user.role || user.role === 'pending');

export default function UserManagement() {
  const currentUser = getUser();
  const [searchParams] = useSearchParams();
  const initialTab = ['all', 'active', 'pending', 'locked', 'removed'].includes(searchParams.get('tab')) ? searchParams.get('tab') : 'all';
  const [activeTab, setActiveTab] = useState(initialTab);
  const [allUsers, setAllUsers] = useState([]);
  const [pendingUsers, setPendingUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [roleSelections, setRoleSelections] = useState({});
  const [actionLoading, setActionLoading] = useState(null);
  const [message, setMessage] = useState('');

  const fetchData = async () => {
    setLoading(true);
    setMessage('');

    try {
      const [allRes, pendingRes] = await Promise.all([
        getAllUsers(),
        getPendingUsers(),
      ]);

      const allData = allRes.data?.data || [];
      const pendingData = pendingRes.data?.data || [];

      setAllUsers(allData);
      setPendingUsers(pendingData);

      const currentRoles = {};
      [...allData, ...pendingData].forEach((user) => {
        const preferredRole = user.role && user.role !== 'pending' ? user.role : (user.requested_role || '');
        currentRoles[user.id] = APPROVABLE_ROLE_VALUES.includes(preferredRole) ? preferredRole : '';
      });
      setRoleSelections(currentRoles);
    } catch (error) {
      console.error('User fetch error:', error);
      setMessage('Failed to load users. Please login as IT Admin and check backend connection.');
    }

    setLoading(false);
  };

  useEffect(() => {
    fetchData();
  }, []);

  useEffect(() => {
    const tab = searchParams.get('tab');
    if (['all', 'active', 'pending', 'locked', 'removed'].includes(tab)) {
      setActiveTab(tab);
    }
  }, [searchParams]);

  const isCurrentUser = (userId) => Number(currentUser?.id) === Number(userId);

  const handleApprove = async (userId) => {
    const role = roleSelections[userId];

    if (!role || role === 'pending') {
      alert('Please select a role before approving this user. After approval, this role will be fixed permanently.');
      return;
    }

    if (!APPROVABLE_ROLE_VALUES.includes(role)) {
      alert('IT Admin cannot be assigned through registration approval. Select a normal hospital work role.');
      return;
    }

    const ok = window.confirm(
      `Approve this user as ${roleLabel(role)}?\n\nThis role will be fixed permanently and cannot be changed or removed later.`
    );
    if (!ok) return;

    setActionLoading(`approve-${userId}`);

    try {
      await approveUser({ user_id: userId, role });
      setMessage('User approved successfully. The role is now fixed permanently and the account is active.');
      await fetchData();
    } catch (error) {
      console.error('Approval error:', error);
      setMessage(
        error.response?.data?.message ||
          error.response?.data?.error ||
          'Approval failed.'
      );
    }

    setActionLoading(null);
  };

  const handleReject = async (userId) => {
    if (!window.confirm('Are you sure you want to reject this user?')) return;

    setActionLoading(`reject-${userId}`);

    try {
      await rejectUser({ user_id: userId });
      setMessage('User rejected successfully. This user cannot login.');
      await fetchData();
    } catch (error) {
      console.error('Reject error:', error);
      setMessage(error.response?.data?.message || 'Rejection failed.');
    }

    setActionLoading(null);
  };

  const handleUnlockUser = async (user) => {
    const ok = window.confirm(
      `Unblock ${user.full_name || user.email}?\n\nFailed login attempts will reset to 0 and this user can login again.`
    );

    if (!ok) return;

    setActionLoading(`unlock-${user.id}`);

    try {
      await unlockUser({ user_id: user.id });
      setMessage('User account unblocked successfully. Failed login attempts were reset.');
      await fetchData();
    } catch (error) {
      console.error('Unlock user error:', error);
      setMessage(error.response?.data?.message || 'Account unblock failed.');
    }

    setActionLoading(null);
  };

  const handleRemoveUser = async (user) => {
    if (isCurrentUser(user.id)) {
      alert('You cannot remove your own IT Admin account.');
      return;
    }

    const ok = window.confirm(
      `Remove ${user.full_name || user.email} from the system?\n\nThis will deactivate the account and block login. The assigned role will remain fixed for audit/history.`
    );

    if (!ok) return;

    setActionLoading(`remove-${user.id}`);

    try {
      await deleteUser({ user_id: user.id, hard_delete: false });
      setMessage('User removed successfully. Account is deactivated. The fixed role was kept unchanged.');
      await fetchData();
    } catch (error) {
      console.error('Remove user error:', error);
      setMessage(error.response?.data?.message || 'User removal failed.');
    }

    setActionLoading(null);
  };

  const lockedUsers = allUsers.filter((user) => isLockedUser(user));
  const activeUsers = allUsers.filter((user) => user.status === 'active' && !isLockedUser(user));
  const removedUsers = allUsers.filter((user) => user.status === 'rejected');

  let displayUsers = allUsers;
  if (activeTab === 'pending') displayUsers = pendingUsers;
  if (activeTab === 'active') displayUsers = activeUsers;
  if (activeTab === 'locked') displayUsers = lockedUsers;
  if (activeTab === 'removed') displayUsers = removedUsers;

  const tabs = [
    { key: 'all', label: `All Users (${allUsers.length})` },
    { key: 'active', label: ` Active (${activeUsers.length})` },
    { key: 'pending', label: ` Pending (${pendingUsers.length})` },
    { key: 'locked', label: ` Locked (${lockedUsers.length})` },
    { key: 'removed', label: ` Removed (${removedUsers.length})` },
  ];

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title quick-action-row"><Icon name="users" size={24} /> User Management</div>
        <div className="page-subtitle">
          Manage system users. New registrations stay pending until approval; IT Admin access cannot be requested from registration. Accounts lock after 3 failed passwords.
        </div>

        {message && <div className="alert alert-info">{message}</div>}

        <div
          style={{
            display: 'flex',
            gap: '2px',
            marginBottom: '20px',
            background: 'var(--bg)',
            padding: '4px',
            borderRadius: '8px',
            width: 'fit-content',
            border: '1px solid var(--border)',
            flexWrap: 'wrap',
          }}
        >
          {tabs.map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              style={{
                padding: '8px 20px',
                borderRadius: '6px',
                border: 'none',
                fontWeight: 500,
                fontSize: '0.875rem',
                cursor: 'pointer',
                background: activeTab === tab.key ? 'var(--surface)' : 'transparent',
                color: activeTab === tab.key ? 'var(--primary)' : 'var(--text-muted)',
                boxShadow: activeTab === tab.key ? 'var(--shadow)' : 'none',
                transition: 'all .2s',
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department / Organization</th>
                    <th>Requested Role</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  {displayUsers.length === 0 ? (
                    <tr>
                      <td colSpan={9}>
                        <div className="empty-state">
                          <div className="empty-state-icon"><Icon name="empty" size={34} /></div>
                          <div className="empty-state-text">
                            {activeTab === 'pending'
                              ? 'No pending users.'
                              : activeTab === 'locked'
                              ? 'No locked users.'
                              : activeTab === 'removed'
                              ? 'No removed users.'
                              : 'No users found.'}
                          </div>
                        </div>
                      </td>
                    </tr>
                  ) : (
                    displayUsers.map((user, i) => {
                      const self = isCurrentUser(user.id);
                      const locked = isLockedUser(user);
                      const removed = user.status === 'rejected';
                      const pending = isPendingUser(user);

                      return (
                        <tr key={user.id || i}>
                          <td>{i + 1}</td>
                          <td style={{ fontWeight: 500 }}>
                            {user.full_name || '—'} {self && <span className="badge badge-success">You</span>}
                          </td>
                          <td>{user.email || '—'}</td>
                          <td>{user.phone || '—'}</td>
                          <td>{user.department || user.organization || '—'}</td>
                          <td>
                            {user.requested_role ? (
                              <span className="badge badge-pending">{roleLabel(user.requested_role)}</span>
                            ) : (
                              <span style={{ color: 'var(--text-muted)' }}>—</span>
                            )}
                          </td>

                          <td>
                            {pending ? (
                              <>
                                <select
                                  style={{
                                    padding: '6px 10px',
                                    border: '1px solid var(--border)',
                                    borderRadius: '6px',
                                    fontSize: '0.85rem',
                                    minWidth: '180px',
                                  }}
                                  value={roleSelections[user.id] || ''}
                                  onChange={(e) =>
                                    setRoleSelections({
                                      ...roleSelections,
                                      [user.id]: e.target.value,
                                    })
                                  }
                                >
                                  <option value="">Select Role</option>
                                  {APPROVABLE_ROLES.map((role) => (
                                    <option key={role.value} value={role.value}>
                                      {role.label}
                                    </option>
                                  ))}
                                </select>
                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
                                  Role will be fixed after approval{user.requested_role ? ` • Requested by user: ${roleLabel(user.requested_role)}` : ''}
                                </div>
                              </>
                            ) : (
                              <>
                                <span className="badge badge-success">{roleLabel(user.role)}</span>
                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
                                  Fixed role cannot be changed or removed
                                </div>
                              </>
                            )}
                          </td>

                          <td>
                            {locked ? (
                              <>
                                <span className="badge badge-danger">locked</span>
                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
                                  Failed attempts: {user.failed_login_attempts || 0}/3
                                  {user.locked_at ? ` • ${user.locked_at}` : ''}
                                </div>
                              </>
                            ) : (
                              <span
                                className={`badge ${
                                  user.status === 'active'
                                    ? 'badge-success'
                                    : user.status === 'rejected'
                                    ? 'badge-danger'
                                    : 'badge-pending'
                                }`}
                              >
                                {user.status === 'rejected' ? 'removed' : user.status || 'pending'}
                              </span>
                            )}
                          </td>

                          <td>
                            <div
                              style={{
                                display: 'flex',
                                gap: '6px',
                                flexWrap: 'wrap',
                                alignItems: 'center',
                              }}
                            >
                              {pending ? (
                                <>
                                  <button
                                    className="action-btn approve"
                                    disabled={actionLoading === `approve-${user.id}`}
                                    onClick={() => handleApprove(user.id)}
                                  >
                                    {actionLoading === `approve-${user.id}` ? '...' : 'Approve & Lock Role'}
                                  </button>

                                  <button
                                    className="action-btn reject"
                                    disabled={actionLoading === `reject-${user.id}`}
                                    onClick={() => handleReject(user.id)}
                                  >
                                    {actionLoading === `reject-${user.id}` ? '...' : 'Reject'}
                                  </button>
                                </>
                              ) : locked ? (
                                <button
                                  className="action-btn approve"
                                  disabled={actionLoading === `unlock-${user.id}`}
                                  onClick={() => handleUnlockUser(user)}
                                >
                                  {actionLoading === `unlock-${user.id}` ? '...' : 'Unblock Account'}
                                </button>
                              ) : removed ? (
                                <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                                  Removed user cannot login. Role kept fixed.
                                </span>
                              ) : (
                                <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                                  Role locked
                                </span>
                              )}

                              {!self && !removed && !pending && (
                                <button
                                  className="action-btn reject"
                                  disabled={actionLoading === `remove-${user.id}`}
                                  onClick={() => handleRemoveUser(user)}
                                >
                                  {actionLoading === `remove-${user.id}` ? '...' : 'Remove User'}
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}
