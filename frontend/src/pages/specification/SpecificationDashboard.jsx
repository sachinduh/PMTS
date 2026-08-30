import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import { useState, useEffect } from 'react';
import { getSpecifications } from '../../api/api';
import '../../styles/dashboard.css';
import '../../styles/tables.css';

export default function SpecificationDashboard() {
  const [specs, setSpecs] = useState([]);
  const [stats, setStats] = useState({ total: 0, pending: 0, reviewed: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getSpecifications()
      .then(res => {
        const rows = res.data?.specifications || [];
        setSpecs(rows);
        setStats({
          total: rows.length,
          pending: rows.filter(s => (s.status || 'pending') === 'pending').length,
          reviewed: rows.filter(s => ['reviewed', 'approved'].includes(s.status)).length,
        });
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text"><h2>Specification Dashboard </h2><p>Only files currently under Specification Committee schedule tasks are shown here.</p></div>
          <div className="welcome-emoji"></div>
        </div>
        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <>
            <div className="stats-grid">
              <StatCard icon="procurement" value={stats.total} label="Files Currently at Specification" color="blue" />
              <StatCard icon="pending" value={stats.pending} label="Pending Review" color="yellow" />
              <StatCard icon="success" value={stats.reviewed} label="Reviewed / Approved" color="green" />
            </div>

            <div className="card" style={{ marginTop: '20px' }}>
              <div className="section-title" style={{ marginBottom: '16px' }}>Current Specification Committee Files</div>
              {specs.length === 0 ? (
                <div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No files are currently at Specification Committee.</div></div>
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
                      {specs.map((item) => (
                        <tr key={item.id}>
                          <td>
                            <strong>{item.file_name || item.title}</strong>
                            <div className="text-muted text-xs">{item.tender_number || item.procurement_id || '—'}</div>
                          </td>
                          <td>{item.title}</td>
                          <td>{item.current_task_label || item.current_task?.task_label || 'Specification Committee'}</td>
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
          <a href="/specification-review" className="btn btn-primary"> Review Specifications</a>
        </div>
      </div>
    </Layout>
  );
}
