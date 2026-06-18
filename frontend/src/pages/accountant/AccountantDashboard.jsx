import Layout from '../../components/Layout';
import StatCard from '../../components/StatCard';
import { useState, useEffect } from 'react';
import { getFinancialApprovals } from '../../api/api';
import '../../styles/dashboard.css';

export default function AccountantDashboard() {
  const [stats, setStats] = useState({ total: 0, pending: 0, approved: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getFinancialApprovals()
      .then(res => {
        const apps = res.data?.approvals || [];
        setStats({ total: apps.length, pending: apps.filter(a => a.status === 'pending').length, approved: apps.filter(a => a.status === 'approved').length });
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text"><h2>Accountant Dashboard 💰</h2><p>Financial Approvals Portal</p></div>
          <div className="welcome-emoji">💰</div>
        </div>
        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="stats-grid">
            <StatCard icon="📋" value={stats.total} label="Total Requests" color="blue" />
            <StatCard icon="⏳" value={stats.pending} label="Pending Approval" color="yellow" />
            <StatCard icon="✅" value={stats.approved} label="Approved" color="green" />
          </div>
        )}
        <div className="card">
          <a href="/accountant-approvals" className="btn btn-primary">💰 Review Financial Approvals</a>
        </div>
      </div>
    </Layout>
  );
}
