import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import { getAllProcurements } from '../../api/api';
import { useNavigate, useSearchParams } from 'react-router-dom';
import '../../styles/tables.css';
import '../../styles/dashboard.css';
import Icon from '../../components/Icon';

export default function DirectorProcurementList() {
  const [procurements, setProcurements] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    getAllProcurements()
      .then(res => setProcurements(res.data?.procurements || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const statusFilter = searchParams.get('status') || '';
  const filteredProcurements = procurements.filter((item) => {
    if (!statusFilter) return true;
    if (statusFilter === 'active') return !['draft', 'completed', 'cancelled'].includes(item.status || item.current_status);
    return (item.status || item.current_status) === statusFilter;
  });

  const columns = [
    { key: 'id', label: '#' },
    { key: 'title', label: 'Title' },
    { key: 'tender_number', label: 'Tender No.' },
    { key: 'procurement_type', label: 'Type', render: (val) => <span className="badge badge-info">{val}</span> },
    { key: 'category', label: 'Category' },
    { key: 'estimated_amount', label: 'Amount (Rs.)', render: (val) => `Rs. ${Number(val || 0).toLocaleString()}` },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status', render: (val) => <StatusBadge status={val} /> },
    {
      key: 'current_location',
      label: 'Now At',
      render: (_, row) => (
        <span className="tracking-chip static">
          <span className="tracking-chip-icon"><Icon name={row.tracking_stage?.icon || 'location'} size={16} /></span>
          <span>{row.current_stage_label || row.current_location || 'Procurement Officer'}</span>
        </span>
      ),
    },
    {
      key: 'actions', label: 'Actions',
      render: (_, row) => (
        <button className="action-btn view" onClick={() => navigate(`/director-procurement/${row.id}`)}>
          View
        </button>
      ),
    },
  ];

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title quick-action-row"><Icon name="procurement" size={24} /> Procurement List</div>
        <div className="page-subtitle">All procurement records with current tracking location. Director can view and track only.</div>
        <div className="card" style={{ padding: '20px' }}>
          <DataTable columns={columns} data={filteredProcurements} loading={loading} emptyMessage="No procurement records found." />
        </div>
      </div>
    </Layout>
  );
}
