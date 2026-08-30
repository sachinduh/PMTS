import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import { getReports } from '../../api/api';

export default function DirectorReports() {
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getReports()
      .then(res => setReports(res.data?.reports || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Reports</div>
        <div className="page-subtitle">Procurement summary and statistical reports</div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>Report Name</th><th>Type</th><th>Generated</th><th>Action</th></tr>
                </thead>
                <tbody>
                  {reports.length === 0 ? (
                    <tr><td colSpan={5}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No reports available.</div></div></td></tr>
                  ) : reports.map((r, i) => (
                    <tr key={r.id || i}>
                      <td>{i + 1}</td>
                      <td>{r.name}</td>
                      <td>{r.type}</td>
                      <td>{r.generated_at}</td>
                      <td><a href={r.url} className="action-btn view" target="_blank" rel="noreferrer">Download</a></td>
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
