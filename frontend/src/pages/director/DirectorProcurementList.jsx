import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import { getAllProcurements } from '../../api/api';
import { useNavigate, useSearchParams } from 'react-router-dom';
import '../../styles/tables.css';
import '../../styles/dashboard.css';
import Icon from '../../components/Icon';

function priorityFileClass(priority) {
  return `priority-file-name priority-${String(priority || 'medium').toLowerCase()}`;
}

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
  const stageFilter = searchParams.get('stage') || '';
  const filteredProcurements = procurements.filter((item) => {
    if (stageFilter && (item.current_stage_label || item.current_location) !== stageFilter) return false;
    if (!statusFilter) return true;
    if (statusFilter === 'active') return !['draft', 'completed', 'cancelled'].includes(item.status || item.current_status);
    return (item.status || item.current_status) === statusFilter;
  });

  const columns = [
    { key: 'id', label: '#' },
    {
      key: 'file_name',
      label: 'File Name',
      render: (val, row) => <span className={priorityFileClass(row.priority)}>{val || row.title || '—'}</span>,
    },
    { key: 'title', label: 'Title' },
    { key: 'tender_number', label: 'Tender No.' },
    { key: 'procurement_type', label: 'Type', render: (val) => <span className="badge badge-info">{val}</span> },
    { key: 'category', label: 'Category' },
    { key: 'estimated_amount', label: 'Amount (Rs.)', render: (val) => `Rs. ${Number(val || 0).toLocaleString()}` },
    { key: 'priority', label: 'Priority', render: (val) => <span className={priorityFileClass(val)}>{String(val || 'medium').toUpperCase()}</span> },
    { key: 'status', label: 'Status', render: (val) => <StatusBadge status={val} /> },
    {
      key: 'current_location',
      label: 'Now At',
      render: (_, row) => (
        <div>
          <span className="tracking-chip static">
            <span className="tracking-chip-icon"><Icon name={row.tracking_stage?.icon || 'location'} size={16} /></span>
            <span>{row.current_stage_label || row.current_location || 'Procurement Officer'}</span>
          </span>
          {row.current_task_label && (
            <div className="text-muted text-xs" style={{ marginTop: '4px', maxWidth: '220px' }}>
              {row.current_task_label}
            </div>
          )}
        </div>
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
        <div className="page-title quick-action-row"><Icon name="procurement" size={24} /> {stageFilter ? `${stageFilter} Procurements` : 'Procurement List'}</div>
        <div className="page-subtitle">All procurement records with current tracking location. Director can view and track only.</div>
        {stageFilter && <div className="alert alert-info">Showing files currently at: <strong>{stageFilter}</strong></div>}
        <div className="card" style={{ padding: '20px' }}>
          <DataTable columns={columns} data={filteredProcurements} loading={loading} emptyMessage="No procurement records found." />
        </div>
      </div>
    </Layout>
  );
}
