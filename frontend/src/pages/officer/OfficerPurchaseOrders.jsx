import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import { getPurchaseOrders } from '../../api/api';
import StatusBadge from '../../components/StatusBadge';

export default function OfficerPurchaseOrders() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getPurchaseOrders()
      .then(res => setOrders(res.data?.purchase_orders || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title">🛒 Purchase Orders</div>
        <div className="page-subtitle">Purchase orders linked to your procurements</div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>PO Number</th><th>Vendor</th><th>Amount (Rs.)</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                  {orders.length === 0 ? (
                    <tr><td colSpan={6}><div className="empty-state"><div className="empty-state-icon">📭</div><div className="empty-state-text">No purchase orders found.</div></div></td></tr>
                  ) : orders.map((o, i) => (
                    <tr key={o.id || i}>
                      <td>{i + 1}</td>
                      <td>{o.po_number}</td>
                      <td>{o.vendor_name}</td>
                      <td>Rs. {Number(o.amount || 0).toLocaleString()}</td>
                      <td>{o.created_at}</td>
                      <td><StatusBadge status={o.status} /></td>
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
