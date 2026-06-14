import { useState, useEffect } from "react";
import Layout from "../../components/Layout";
import {
  getAllProcurements,
  approveProcurement,
  rejectProcurement,
} from "../../api/api";
import "../../styles/tables.css";

export default function DirectorApprovals() {
  const [procurements, setProcurements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");

  const fetchData = async () => {
    setLoading(true);
    setMessage("");

    try {
      const res = await getAllProcurements();

      const allProcurements = res.data?.procurements || res.data?.data || [];

      const pendingProcurements = allProcurements.filter(
        (p) => p.status === "pending"
      );

      setProcurements(pendingProcurements);
    } catch (error) {
      console.error("Director approvals error:", error);
      setMessage("Failed to load pending approvals.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleApprove = async (id) => {
    try {
      await approveProcurement({ id });
      setMessage("Procurement approved successfully.");
      fetchData();
    } catch (error) {
      console.error("Approve error:", error);
      setMessage("Approval failed.");
    }
  };

  const handleReject = async (id) => {
    if (!window.confirm("Are you sure you want to reject this procurement?")) {
      return;
    }

    try {
      await rejectProcurement({ id });
      setMessage("Procurement rejected successfully.");
      fetchData();
    } catch (error) {
      console.error("Reject error:", error);
      setMessage("Rejection failed.");
    }
  };

  return (
    <Layout>
      <div className="page-wrapper">
        <div className="page-title">✅ Director Approvals</div>
        <div className="page-subtitle">
          Review pending procurement approvals
        </div>

        {message && <div className="alert alert-info">{message}</div>}

        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Tender No</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  {procurements.length === 0 ? (
                    <tr>
                      <td colSpan="9">
                        <div className="empty-state">
                          <div className="empty-state-icon">📭</div>
                          <div className="empty-state-text">
                            No pending approvals.
                          </div>
                        </div>
                      </td>
                    </tr>
                  ) : (
                    procurements.map((p, index) => (
                      <tr key={p.id}>
                        <td>{index + 1}</td>
                        <td>{p.procurement_code}</td>
                        <td>{p.title}</td>
                        <td>{p.tender_number}</td>
                        <td>{p.procurement_type}</td>
                        <td>{p.category}</td>
                        <td>Rs. {Number(p.estimated_amount).toLocaleString()}</td>
                        <td>
                          <span className="badge badge-pending">
                            {p.status}
                          </span>
                        </td>
                        <td>
                          <button
                            className="action-btn approve"
                            onClick={() => handleApprove(p.id)}
                          >
                            Approve
                          </button>

                          <button
                            className="action-btn reject"
                            onClick={() => handleReject(p.id)}
                            style={{ marginLeft: "6px" }}
                          >
                            Reject
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}