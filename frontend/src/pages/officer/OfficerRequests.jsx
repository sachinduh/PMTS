import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import { getAllProcurements } from '../../api/api';

export default function OfficerRequests() {
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAllProcurements()
      .then(res => setRequests(res.data?.procurements || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> My Requests</div>
        <div className="page-subtitle">Track the status of your submitted procurement requests</div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>Title</th><th>Tender No.</th><th>Type</th><th>Submitted</th><th>Status</th></tr>
                </thead>
                <tbody>
                  {requests.length === 0 ? (
                    <tr><td colSpan={6}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No requests submitted yet.</div></div></td></tr>
                  ) : requests.map((r, i) => (
                    <tr key={r.id || i}>
                      <td>{i + 1}</td>
                      <td>{r.title}</td>
                      <td>{r.tender_number}</td>
                      <td>{r.procurement_type}</td>
                      <td>{r.created_at}</td>
                      <td><StatusBadge status={r.status} /></td>
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
