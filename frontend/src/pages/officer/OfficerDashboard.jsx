import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import Layout from "../../components/Layout";
import StatCard from "../../components/StatCard";
import { getAllProcurements } from "../../api/api";
import { getUser } from "../../utils/auth";
import "../../styles/dashboard.css";

export default function OfficerDashboard() {
  const user = getUser();

  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
  });

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAllProcurements()
      .then((res) => {
        console.log("PROCUREMENTS RESPONSE:", res.data);

        const allProcurements =
          res.data?.procurements || res.data?.data || [];

        // Show only procurements created by logged-in procurement officer
        const myProcurements = allProcurements.filter(
          (p) => Number(p.created_by) === Number(user?.id)
        );

        setStats({
          total: myProcurements.length,
          pending: myProcurements.filter((p) => p.status === "pending").length,
          approved: myProcurements.filter((p) => p.status === "approved").length,
          rejected: myProcurements.filter((p) => p.status === "rejected").length,
        });
      })
      .catch((error) => {
        console.error("OFFICER DASHBOARD ERROR:", error);
        console.error("BACKEND RESPONSE:", error.response?.data);
      })
      .finally(() => setLoading(false));
  }, [user?.id]);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="welcome-banner">
          <div className="welcome-text">
            <h2>Hello, {user?.full_name?.split(" ")[0] || "Officer"} 👋</h2>
            <p>Manage your procurement submissions</p>
          </div>

          <div className="welcome-emoji">📋</div>
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
              label="Pending"
              color="yellow"
            />

            <StatCard
              icon="✅"
              value={stats.approved}
              label="Approved"
              color="green"
            />

            <StatCard
              icon="❌"
              value={stats.rejected}
              label="Rejected"
              color="red"
            />
          </div>
        )}

        <div className="card">
          <div className="section-title" style={{ marginBottom: "16px" }}>
            Quick Actions
          </div>

          <div style={{ display: "flex", gap: "12px", flexWrap: "wrap" }}>
            <Link to="/officer-create" className="btn btn-primary">
              ➕ New Procurement
            </Link>

            <Link to="/officer-management" className="btn btn-outline">
              📋 Manage Procurements
            </Link>

            <Link to="/officer-purchase-orders" className="btn btn-outline">
              🛒 Purchase Orders
            </Link>
          </div>
        </div>
      </div>
    </Layout>
  );
}