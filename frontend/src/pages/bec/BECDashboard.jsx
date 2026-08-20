import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import { useState, useEffect } from 'react';
import { getBECEvaluations } from '../../api/api';
import '../../styles/dashboard.css';
import '../../styles/tables.css';

export default function BECDashboard() {
  const [evaluations, setEvaluations] = useState([]);
  const [stats, setStats] = useState({ total: 0, pending: 0, completed: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getBECEvaluations()
      .then(res => {
        const rows = res.data?.evaluations || [];
        setEvaluations(rows);
        setStats({
          total: rows.length,
          pending: rows.filter(e => (e.status || 'pending') === 'pending').length,
          completed: rows.filter(e => e.status === 'completed').length,
        });
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text"><h2>BEC Dashboard </h2><p>Only files currently under BEC schedule tasks are shown here.</p></div>
          <div className="welcome-emoji"></div>
        </div>
        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <>
            <div className="stats-grid">
              <StatCard icon="procurement" value={stats.total} label="Files Currently at BEC" color="blue" />
              <StatCard icon="pending" value={stats.pending} label="Pending BEC Evaluation" color="yellow" />
              <StatCard icon="success" value={stats.completed} label="Completed BEC Evaluation" color="green" />
            </div>

            <div className="card" style={{ marginTop: '20px' }}>
              <div className="section-title" style={{ marginBottom: '16px' }}>Current BEC Files</div>
              {evaluations.length === 0 ? (
                <div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No files are currently at BEC.</div></div>
              ) : (
                <div className="table-wrapper compact-table-wrapper">
                  <table className="data-table compact-table">
                    <thead>
                      <tr>
                        <th>File</th>
                        <th>Procurement</th>
                        <th>Current Task</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {evaluations.map((item) => (
                        <tr key={item.id}>
                          <td>
                            <strong>{item.file_name || item.title}</strong>
                            <div className="text-muted text-xs">{item.tender_number || item.procurement_id || '—'}</div>
                          </td>
                          <td>{item.title}</td>
                          <td>{item.current_task_label || item.current_task?.task_label || 'BEC'}</td>
                          <td><StatusBadge status={item.status || 'pending'} /></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </>
        )}
        <div className="card" style={{ marginTop: '20px' }}>
          <div className="section-title" style={{ marginBottom: '16px' }}>Quick Actions</div>
          <a href="/bec-evaluation" className="btn btn-primary"> Start Bid Evaluation</a>
        </div>
      </div>
    </Layout>
  );
}
