import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import { useState, useEffect, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getAllProcurements, getFinancialApprovals } from '../../api/api';
import '../../styles/dashboard.css';
import '../../styles/tables.css';

const ACTIVE_STATUSES = [
  'submitted',
  'under_review',
  'specification_approval',
  'tender_preparation',
  'advertised',
  'bid_received',
    'bid_evaluation',
  'financial_evaluation',
  'awarded',
  'purchase_order_issued',
  'contract_signed',
  'on_hold',
];

function StatLink({ to, children }) {
  return <Link to={to} className="stat-link">{children}</Link>;
}

export default function AccountantDashboard() {
  const navigate = useNavigate();
  const [procurements, setProcurements] = useState([]);
  const [approvals, setApprovals] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const [procRes, approvalRes] = await Promise.allSettled([
          getAllProcurements({ limit: 100 }),
          getFinancialApprovals(),
        ]);

        if (procRes.status === 'fulfilled') {
          setProcurements(procRes.value.data?.procurements || []);
        }
        if (approvalRes.status === 'fulfilled') {
          setApprovals(approvalRes.value.data?.approvals || []);
        }
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const stats = useMemo(() => {
    const total = procurements.length;
    const financialReview = procurements.filter((p) => p.status === 'financial_evaluation').length;
    const active = procurements.filter((p) => ACTIVE_STATUSES.includes(p.status)).length;
    const completed = procurements.filter((p) => p.status === 'completed').length;
    const pendingFinance = approvals.filter((a) => a.status === 'pending' || a.financial_status === 'pending').length;
    const approvedFinance = approvals.filter((a) => a.status === 'approved' || a.financial_status === 'approved').length;

    return { total, financialReview, active, completed, pendingFinance, approvedFinance };
  }, [procurements, approvals]);

  const financialQueue = useMemo(
    () => approvals.filter((a) => a.status === 'pending' || a.current_status === 'financial_evaluation').slice(0, 5),
    [approvals]
  );

  const stageCounts = useMemo(() => {
    return procurements.reduce((map, item) => {
      const label = item.current_stage_label || item.current_location || 'Procurement Officer';
      map[label] = (map[label] || 0) + 1;
      return map;
    }, {});
  }, [procurements]);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text">
            <h2>Accountant Dashboard </h2>
            <p>View procurement tracking and handle financial review when the procurement reaches Accountant stage.</p>
          </div>
          <div className="welcome-emoji"></div>
        </div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <>
            <div className="stats-grid officer-stats-grid">
              <StatLink to="/accountant-procurements">
                <StatCard icon="procurement" value={stats.total} label="Total Procurements" color="blue" />
              </StatLink>
              <StatLink to="/accountant-procurements">
                <StatCard icon="progress" value={stats.active} label="Active Procurements" color="teal" />
              </StatLink>
              <StatLink to="/accountant-approvals">
                <StatCard icon="finance" value={stats.financialReview} label="At Financial Review" color="purple" />
              </StatLink>
              <StatLink to="/accountant-approvals">
                <StatCard icon="pending" value={stats.pendingFinance} label="Pending Finance" color="yellow" />
              </StatLink>
              <StatLink to="/accountant-approvals">
                <StatCard icon="success" value={stats.approvedFinance} label="Finance Approved" color="green" />
              </StatLink>
              <StatLink to="/accountant-procurements">
                <StatCard icon="completed" value={stats.completed} label="Completed" color="green" />
              </StatLink>
            </div>

            <div className="card" style={{ marginBottom: '20px' }}>
              <div className="section-title" style={{ marginBottom: '14px' }}>Current Procurement Locations</div>
              <div className="tracking-summary-grid">
                {Object.entries(stageCounts).length === 0 ? (
                  <div className="text-muted">No procurement tracking data available.</div>
                ) : Object.entries(stageCounts).map(([label, count]) => (
                  <button
                    key={label}
                    className="tracking-summary-card"
                    onClick={() => navigate(`/accountant-procurements?stage=${encodeURIComponent(label)}`)}
                  >
                    <span className="tracking-summary-count">{count}</span>
                    <span className="tracking-summary-label">{label}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="dashboard-grid">
              <div className="card">
                <div className="section-header dashboard-card-header">
                  <div>
                    <div className="section-title">Financial Review Queue</div>
                    <p className="text-muted text-sm">Procurements currently waiting for accountant financial checking.</p>
                  </div>
                  <button className="btn btn-outline btn-sm" onClick={() => navigate('/accountant-approvals')}>View All</button>
                </div>
                <div className="table-wrapper compact-table-wrapper">
                  <table className="data-table compact-table">
                    <thead><tr><th>Procurement</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                      {financialQueue.length === 0 ? (
                        <tr><td colSpan="4"><div className="empty-state small-empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No pending financial review items.</div></div></td></tr>
                      ) : financialQueue.map((item) => (
                        <tr key={item.id}>
                          <td><div className="font-semibold">{item.title}</div><div className="text-muted text-xs">{item.procurement_id}</div></td>
                          <td>Rs. {Number(item.estimated_amount || 0).toLocaleString()}</td>
                          <td><StatusBadge status={item.status || item.financial_status || 'pending'} /></td>
                          <td><button className="action-btn view" onClick={() => navigate(`/accountant-procurement/${item.id}`)}>Track</button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="card">
                <div className="section-header"><span className="section-title">Quick Actions</span></div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                  <Link to="/accountant-procurements" className="quick-action-card">
                    <span className="quick-action-icon"></span>
                    <span className="quick-action-title">Track Procurements</span>
                    <span className="quick-action-text">View where each procurement is now.</span>
                  </Link>
                  <Link to="/accountant-approvals" className="quick-action-card">
                    <span className="quick-action-icon"></span>
                    <span className="quick-action-title">Financial Approvals</span>
                    <span className="quick-action-text">Review financial approval items.</span>
                  </Link>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </Layout>
  );
}
