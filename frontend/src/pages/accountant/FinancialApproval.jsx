import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import { getFinancialApprovals, submitFinancialApproval } from '../../api/api';
import '../../styles/tables.css';
import Icon from '../../components/Icon';

export default function FinancialApproval() {
  const navigate = useNavigate();
  const [approvals, setApprovals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(null);
  const [message, setMessage] = useState('');

  const fetchData = async () => {
    setLoading(true);
    try {
      const res = await getFinancialApprovals();
      setApprovals(res.data?.approvals || []);
    } catch {
      setMessage('Failed to load financial review items.');
    }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, []);

  const handleAction = async (id, action) => {
    setActionLoading(`${id}-${action}`);
    setMessage('');
    try {
      await submitFinancialApproval({ procurement_id: id, action });
      setMessage(`Financial ${action === 'approve' ? 'approval' : 'rejection'} processed successfully.`);
      fetchData();
    } catch (err) {
      setMessage(err.response?.data?.message || 'Action failed.');
    }
    setActionLoading(null);
  };

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Financial Review / Approvals</div>
        <div className="page-subtitle">Accountant can review procurement amount and track current stage before financial decision.</div>

        {message && <div className="alert alert-info">{message}</div>}

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Procurement</th>
                    <th>Amount (Rs.)</th>
                    <th>Requested By</th>
                    <th>Now At</th>
                    <th>Financial Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {approvals.length === 0 ? (
                    <tr><td colSpan={7}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No financial review items.</div></div></td></tr>
                  ) : approvals.map((a, i) => {
                    const status = a.financial_status || a.status || 'pending';
                    return (
                      <tr key={a.id || i}>
                        <td>{i + 1}</td>
                        <td>
                          <div className="font-semibold">{a.title}</div>
                          <div className="text-muted text-xs">{a.procurement_id || a.tender_number || '—'}</div>
                        </td>
                        <td>Rs. {Number(a.estimated_amount || 0).toLocaleString()}</td>
                        <td>{a.requested_by || a.created_by_name || '—'}</td>
                        <td>
                          <span className="tracking-chip static compact">
                            <span className="tracking-chip-icon"><Icon name={a.tracking_stage?.icon || 'location'} size={16} /></span>
                            <span>{a.current_stage_label || a.current_location || '—'}</span>
                          </span>
                        </td>
                        <td><StatusBadge status={status} /></td>
                        <td>
                          <button className="action-btn view" onClick={() => navigate(`/accountant-procurement/${a.id}`)}>Track</button>
                          {status === 'pending' && a.current_status === 'financial_evaluation' && (
                            <>
                              <button className="action-btn approve" disabled={actionLoading === `${a.id}-approve`} onClick={() => handleAction(a.id, 'approve')}>Approve</button>
                              <button className="action-btn reject" disabled={actionLoading === `${a.id}-reject`} onClick={() => handleAction(a.id, 'reject')}>Reject</button>
                            </>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}
