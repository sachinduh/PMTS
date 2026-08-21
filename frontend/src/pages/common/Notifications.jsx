import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import Icon from '../../components/Icon';
import { getNotifications, markNotificationRead } from '../../api/api';

const TYPE_ICON = {
  info: 'notification',
  warning: 'alert',
  success: 'success',
  error: 'error',
  approval_request: 'pending',
  status_update: 'progress',
  alert: 'alert',
  system: 'settings',
};

export default function Notifications() {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);

  const fetchData = async () => {
    setLoading(true);

    try {
      const res = await getNotifications();
      const notificationData = res.data?.notifications || res.data?.data || [];
      setNotifications(notificationData);
    } catch (error) {
      console.error('Notification fetch error:', error);
      setNotifications([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const markRead = async (id) => {
    try {
      await markNotificationRead({ id });

      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, is_read: 1 } : n
        )
      );
    } catch (error) {
      console.error('Mark read error:', error);
    }
  };

  return (
    <Layout>
      <div className="page-wrapper">
        <div className="page-title quick-action-row">
          <Icon name="notification" size={24} /> Notifications ({notifications.length})
        </div>

        <div className="page-subtitle">
          Your role-based alerts and updates
        </div>

        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : notifications.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '48px' }}>
            <div className="empty-state-icon" style={{ margin: '0 auto 12px' }}>
              <Icon name="empty" size={42} />
            </div>
            <p style={{ color: 'var(--text-muted)' }}>No notifications yet.</p>
          </div>
        ) : (
          <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
            {notifications.map((n) => (
              <div
                key={n.id}
                style={{
                  padding: '16px 20px',
                  borderBottom: '1px solid var(--border)',
                  background: Number(n.is_read) ? 'var(--surface)' : 'var(--primary-light)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  gap: 16,
                }}
              >
                <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                  <span className="notification-list-icon">
                    <Icon name={TYPE_ICON[n.type] || 'notification'} size={22} />
                  </span>
                  <div>
                    <p style={{ fontWeight: Number(n.is_read) ? 500 : 700, fontSize: '0.92rem', marginBottom: '2px' }}>
                      {n.title || 'Notification'}
                    </p>
                    <p style={{ fontSize: '0.9rem', marginBottom: '4px' }}>
                      {n.message}
                    </p>
                    <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                      {n.created_at}
                    </span>
                  </div>
                </div>

                {!Number(n.is_read) && (
                  <button className="btn btn-outline btn-sm" onClick={() => markRead(n.id)}>
                    Mark Read
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </Layout>
  );
}
