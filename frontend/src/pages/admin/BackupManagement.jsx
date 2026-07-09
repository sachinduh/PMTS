import { useState } from 'react';
import Layout from '../../components/Layout';
import { triggerBackup } from '../../api/api';

export default function BackupManagement() {
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const handleBackup = async () => {
    setLoading(true);
    setMessage('');
    try {
      await triggerBackup();
      setMessage(' Backup initiated successfully! The system will create a full database backup.');
    } catch {
      setMessage(' Backup failed. Please try again or check server logs.');
    }
    setLoading(false);
  };

  const backupHistory = [
    { date: '2025-06-06 02:00', type: 'Automatic', size: '24.5 MB', status: 'Success' },
    { date: '2025-06-05 02:00', type: 'Automatic', size: '23.8 MB', status: 'Success' },
    { date: '2025-06-04 14:30', type: 'Manual', size: '23.1 MB', status: 'Success' },
  ];

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Backup Management</div>
        <div className="page-subtitle">Create and manage system database backups</div>

        {message && <div className={`alert ${message.startsWith('') ? 'alert-success' : 'alert-danger'}`}>{message}</div>}

        <div className="dashboard-grid">
          <div className="card">
            <div className="section-title" style={{ marginBottom: '16px' }}>Create Backup</div>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '20px' }}>
              Manually trigger a full database backup. This may take a few minutes.
            </p>
            <button
              className="btn btn-primary"
              onClick={handleBackup}
              disabled={loading}
            >
              {loading ? ' Creating Backup…' : ' Create Backup Now'}
            </button>
          </div>

          <div className="card">
            <div className="section-title" style={{ marginBottom: '16px' }}>Backup History</div>
            {backupHistory.map((b, i) => (
              <div key={i} style={{ padding: '10px 0', borderBottom: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <div style={{ fontWeight: 500, fontSize: '0.875rem' }}>{b.date}</div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{b.type} | {b.size}</div>
                </div>
                <span className="badge badge-success">{b.status}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </Layout>
  );
}
