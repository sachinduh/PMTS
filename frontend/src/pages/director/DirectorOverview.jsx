import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import Icon from '../../components/Icon';
import { getAllProcurements } from '../../api/api';
import '../../styles/dashboard.css';

function StatLink({ to, children }) {
  return <Link to={to} className="stat-link">{children}</Link>;
}

export default function DirectorOverview() {
  const navigate = useNavigate();
  const [procurements, setProcurements] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAllProcurements()
      .then(res => setProcurements(res.data?.procurements || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const byStatus = (status) => procurements.filter(p => (p.status || p.current_status) === status).length;
  const activeCount = procurements.filter(p => !['draft', 'completed', 'cancelled'].includes(p.status || p.current_status)).length;

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title-with-icon">
          <span className="page-title-icon"><Icon name="dashboard" size={22} /></span>
          <div>
            <div className="page-title">Procurement Overview</div>
            <div className="page-subtitle">High-level summary of all procurement activities</div>
          </div>
        </div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <>
            <div className="stats-grid">
              <StatLink to="/director-procurements">
                <StatCard icon="procurement" value={procurements.length} label="Total Procurements" color="blue" />
              </StatLink>
              <StatLink to="/director-procurements?status=draft">
                <StatCard icon="document" value={byStatus('draft')} label="Draft Procurements" color="teal" />
              </StatLink>
              <StatLink to="/director-procurements?status=active">
                <StatCard icon="progress" value={activeCount} label="Active / Tracking" color="yellow" />
              </StatLink>
              <StatLink to="/director-procurements?status=under_review">
                <StatCard icon="search" value={byStatus('under_review')} label="Under Review" color="purple" />
              </StatLink>
              <StatLink to="/director-procurements?status=completed">
                <StatCard icon="success" value={byStatus('completed')} label="Completed" color="green" />
              </StatLink>
              <StatLink to="/director-procurements?status=cancelled">
                <StatCard icon="error" value={byStatus('cancelled')} label="Cancelled" color="red" />
              </StatLink>
            </div>

            <div className="card">
              <div className="section-header" style={{ marginBottom: '16px' }}>
                <span className="section-title">Recent Procurements</span>
                <Link to="/director-procurements" className="btn btn-secondary btn-sm">
                  <Icon name="view" size={16} /> View All
                </Link>
              </div>
              <div className="table-wrapper">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Title</th>
                      <th>Type</th>
                      <th>Category</th>
                      <th>Estimated Amount</th>
                      <th>Status</th>
                      <th>Now At</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {procurements.slice(0, 10).map((p, i) => (
                      <tr key={p.id} className="clickable-row" onClick={() => navigate(`/director-procurement/${p.id}`)}>
                        <td>{i + 1}</td>
                        <td>{p.title}</td>
                        <td><span className="badge badge-info">{p.procurement_type}</span></td>
                        <td>{p.category || '-'}</td>
                        <td>Rs. {Number(p.estimated_amount || 0).toLocaleString()}</td>
                        <td><StatusBadge status={p.status || p.current_status} /></td>
                        <td>{p.current_stage_label || p.current_location || 'Procurement Officer'}</td>
                        <td>
                          <button
                            type="button"
                            className="action-btn view"
                            onClick={(event) => {
                              event.stopPropagation();
                              navigate(`/director-procurement/${p.id}`);
                            }}
                          >
                            View
                          </button>
                        </td>
                      </tr>
                    ))}
                    {procurements.length === 0 && (
                      <tr><td colSpan={8}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No procurements found.</div></div></td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}
      </div>
    </Layout>
  );
}
