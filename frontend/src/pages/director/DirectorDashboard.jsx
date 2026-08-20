import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import Icon from '../../components/Icon';
import { getAllProcurements, getDelayAlerts, runScheduleDelayCheck } from '../../api/api';
import { getUser } from '../../utils/auth';
import '../../styles/dashboard.css';
import '../../styles/tables.css';

function StatLink({ to, children }) {
  return <Link to={to} className="stat-link">{children}</Link>;
}

export default function DirectorDashboard() {
  const user = getUser();
  const navigate = useNavigate();
  const [stats, setStats] = useState({ total: 0, draft: 0, active: 0, completed: 0, delayed: 0 });
  const [stageCounts, setStageCounts] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        await runScheduleDelayCheck().catch(() => null);
        const [procRes, delayRes] = await Promise.all([
          getAllProcurements(),
          getDelayAlerts({ status: 'active' }),
        ]);
        const procs = procRes.data?.procurements || [];
        const activeStatuses = ['submitted', 'under_review', 'specification_approval', 'tender_preparation', 'advertised', 'bid_received', 'bid_evaluation', 'financial_evaluation', 'awarded', 'purchase_order_issued', 'contract_signed', 'on_hold'];
        setStageCounts(procs.reduce((map, item) => {
          const label = item.current_stage_label || item.current_location || 'Procurement Officer';
          map[label] = (map[label] || 0) + 1;
          return map;
        }, {}));
        setStats({
          total: procs.length,
          draft: procs.filter((p) => p.status === 'draft').length,
          active: procs.filter((p) => activeStatuses.includes(p.status)).length,
          completed: procs.filter((p) => p.status === 'completed').length,
          delayed: (delayRes.data?.alerts || []).length,
        });
      } catch {
        // keep dashboard available even if one summary endpoint fails
      }
      setLoading(false);
    };
    fetchStats();
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text">
            <h2>Welcome back, {user?.name?.split(' ')[0] || 'Director'}</h2>
            <p>Here&apos;s an overview of the procurement activity</p>
          </div>
          <div className="welcome-icon"><Icon name="dashboard" size={34} /></div>
        </div>

        {loading ? (
          <div className="loading-page"><div className="spinner" /></div>
        ) : (
          <div className="stats-grid">
            <StatLink to="/director-procurements">
              <StatCard icon="procurement" value={stats.total} label="Total Procurements" color="blue" />
            </StatLink>
            <StatLink to="/director-procurements?status=draft">
              <StatCard icon="document" value={stats.draft} label="Draft Procurements" color="yellow" />
            </StatLink>
            <StatLink to="/director-procurements?status=active">
              <StatCard icon="progress" value={stats.active} label="Active / Tracking" color="green" />
            </StatLink>
            <StatLink to="/director-delay-alerts">
              <StatCard icon="alert" value={stats.delayed} label="Delay Alerts" color="red" />
            </StatLink>
          </div>
        )}

        <div className="card" style={{ marginBottom: '20px' }}>
          <div className="section-title" style={{ marginBottom: '14px' }}>Current Procurement Locations</div>
          <div className="tracking-summary-grid">
            {Object.entries(stageCounts).length === 0 ? (
              <div className="text-muted">No procurement tracking data available.</div>
            ) : Object.entries(stageCounts).map(([label, count]) => (
              <button
                key={label}
                className="tracking-summary-card"
                onClick={() => navigate(`/director-procurements?stage=${encodeURIComponent(label)}`)}
              >
                <span className="tracking-summary-count">{count}</span>
                <span className="tracking-summary-label">{label}</span>
              </button>
            ))}
          </div>
        </div>

        <div className="dashboard-grid">
          <div className="card">
            <div className="section-header">
              <span className="section-title">Quick Actions</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              {[
                { label: 'View All Procurements', icon: 'procurement', href: '/director-procurements' },
                { label: 'Track Procurement Progress', icon: 'progress', href: '/director-procurements' },
                { label: 'Check Delay Alerts', icon: 'alert', href: '/director-delay-alerts' },
                { label: 'View Reports', icon: 'reports', href: '/director-reports' },
                { label: 'Audit Logs', icon: 'audit', href: '/director-audit-logs' },
              ].map((item) => (
                <Link
                  key={item.label}
                  to={item.href}
                  className="quick-action-card"
                  style={{ flexDirection: 'row', alignItems: 'center' }}
                >
                  <span className="quick-action-icon"><Icon name={item.icon} size={20} /></span>
                  <span className="quick-action-title">{item.label}</span>
                </Link>
              ))}
            </div>
          </div>

          <div className="card">
            <div className="section-header">
              <span className="section-title">System Overview</span>
            </div>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: '1.8' }}>
              <p className="quick-action-row"><Icon name="calendar" size={17} /> Current Date: {new Date().toLocaleDateString('en-LK')}</p>
              <p className="quick-action-row"><Icon name="hospital" size={17} /> Hospital Procurement Management</p>
              <p className="quick-action-row"><Icon name="view" size={17} /> Director view-only procurement tracking</p>
              <p className="quick-action-row"><Icon name="dashboard" size={17} /> Real-time Tracking Active</p>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}
