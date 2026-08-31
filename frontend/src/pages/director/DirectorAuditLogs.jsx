import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import { getAuditLogs } from '../../api/api';

export default function DirectorAuditLogs() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAuditLogs()
      .then(res => setLogs(res.data?.logs || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Audit Logs</div>
        <div className="page-subtitle">System activity and audit trail</div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>User</th><th>Action</th><th>Module</th><th>IP Address</th><th>Timestamp</th></tr>
                </thead>
                <tbody>
                  {logs.length === 0 ? (
                    <tr><td colSpan={6}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No audit logs found.</div></div></td></tr>
                  ) : logs.map((log, i) => (
                    <tr key={log.id || i}>
                      <td>{i + 1}</td>
                      <td>{log.user_name}</td>
                      <td>{log.action}</td>
                      <td>{log.module}</td>
                      <td>{log.ip_address}</td>
                      <td>{log.created_at}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}
