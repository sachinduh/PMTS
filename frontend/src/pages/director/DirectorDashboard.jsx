import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Layout from "../../components/Layout";
import StatCard from "../../components/StatCard";
import { getAllProcurements } from "../../api/api";
import { getUser } from "../../utils/auth";
import "../../styles/dashboard.css";
import "../../styles/tables.css";

export default function DirectorDashboard() {
  const user = getUser();

  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    approved: 0,
    delayed: 0,
  });

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      setLoading(true);

      try {
        const procRes = await getAllProcurements();

        console.log("DIRECTOR PROCUREMENTS RESPONSE:", procRes.data);

        const procurements =
          procRes.data?.procurements || procRes.data?.data || [];

        setStats({
          total: procurements.length,
          pending: procurements.filter((p) => p.status === "pending").length,
          approved: procurements.filter((p) => p.status === "approved").length,
          delayed: 0,
        });
      } catch (error) {
        console.error("DIRECTOR DASHBOARD ERROR:", error);
        console.error("BACKEND RESPONSE:", error.response?.data);
      } finally {
        setLoading(false);
      }
    };

    fetchStats();
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text">
            <h2>
              Welcome back, {user?.full_name?.split(" ")[0] || "Director"} 👋
            </h2>
            <p>Here&apos;s an overview of the procurement activity</p>
          </div>

          <div className="welcome-emoji">📊</div>
        </div>

        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : (
          <div className="stats-grid">
            <StatCard
              icon="📋"
              value={stats.total}
              label="Total Procurements"
              color="blue"
            />

            <StatCard
              icon="⏳"
              value={stats.pending}
              label="Pending Approvals"
              color="yellow"
            />

            <StatCard
              icon="✅"
              value={stats.approved}
              label="Approved"
              color="green"
            />

            <StatCard
              icon="⚠️"
              value={stats.delayed}
              label="Delay Alerts"
              color="red"
            />
          </div>
        )}

        <div className="dashboard-grid">
          <div className="card">
            <div className="section-header">
              <span className="section-title">Quick Actions</span>
            </div>

            <div
              style={{
                display: "flex",
                flexDirection: "column",
                gap: "10px",
              }}
            >
              {[
                {
                  label: "📋 View All Procurements",
                  href: "/director-procurements",
                },
                {
                  label: "✅ Review Approvals",
                  href: "/director-approvals",
                },
                {
                  label: "⚠️ Check Delay Alerts",
                  href: "/director-delay-alerts",
                },
                {
                  label: "📈 View Reports",
                  href: "/director-reports",
                },
                {
                  label: "🔒 Audit Logs",
                  href: "/director-audit-logs",
                },
              ].map((item) => (
                <Link
                  key={item.href}
                  to={item.href}
                  style={{
                    display: "block",
                    padding: "12px 16px",
                    background: "var(--bg)",
                    borderRadius: "8px",
                    fontSize: "0.9rem",
                    fontWeight: 500,
                    color: "var(--text-dark)",
                    transition: "background .2s",
                  }}
                  onMouseEnter={(e) =>
                    (e.currentTarget.style.background = "var(--primary-light)")
                  }
                  onMouseLeave={(e) =>
                    (e.currentTarget.style.background = "var(--bg)")
                  }
                >
                  {item.label}
                </Link>
              ))}
            </div>
          </div>

          <div className="card">
            <div className="section-header">
              <span className="section-title">System Overview</span>
            </div>

            <div
              style={{
                color: "var(--text-muted)",
                fontSize: "0.9rem",
                lineHeight: "1.8",
              }}
            >
              <p>📅 Current Date: {new Date().toLocaleDateString("en-LK")}</p>
              <p>🏥 Hospital Procurement Management</p>
              <p>🔄 Multi-level Approval Workflow</p>
              <p>📊 Real-time Tracking Active</p>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}