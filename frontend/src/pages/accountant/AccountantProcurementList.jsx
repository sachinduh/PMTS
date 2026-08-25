import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../../components/Layout';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import { getAllProcurements } from '../../api/api';
import '../../styles/tables.css';
import '../../styles/dashboard.css';
import Icon from '../../components/Icon';

function priorityFileClass(priority) {
  return `priority-file-name priority-${String(priority || 'medium').toLowerCase()}`;
}

export default function AccountantProcurementList() {
  const [procurements, setProcurements] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const stageFilter = searchParams.get('stage') || '';

  useEffect(() => {
    getAllProcurements({ limit: 100 })
      .then((res) => setProcurements(res.data?.procurements || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const filteredProcurements = stageFilter
    ? procurements.filter((item) => (item.current_stage_label || item.current_location) === stageFilter)
    : procurements;

  const columns = [
    { key: 'id', label: '#' },
    {
      key: 'file_name',
      label: 'File Name',
      render: (val, row) => <span className={priorityFileClass(row.priority)}>{val || row.title || '—'}</span>,
    },
    { key: 'title', label: 'Title' },
    { key: 'procurement_id', label: 'Procurement ID' },
    { key: 'procurement_type', label: 'Type', render: (val) => <span className="badge badge-info">{val}</span> },
    { key: 'estimated_amount', label: 'Amount (Rs.)', render: (val) => `Rs. ${Number(val || 0).toLocaleString()}` },
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
      key: 'actions',
      label: 'Actions',
      render: (_, row) => (
        <button className="action-btn view" onClick={() => navigate(`/accountant-procurement/${row.id}`)}>
          Track
        </button>
      ),
    },
  ];

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> {stageFilter ? `${stageFilter} Procurements` : 'Procurement Tracking'}</div>
        <div className="page-subtitle">Accountant can view procurements, current stage, schedule tracking, and financial review status.</div>
        {stageFilter && <div className="alert alert-info">Showing files currently at: <strong>{stageFilter}</strong></div>}
        <div className="card" style={{ padding: '20px' }}>
          <DataTable columns={columns} data={filteredProcurements} loading={loading} emptyMessage="No procurement records found." />
        </div>
      </div>
    </Layout>
  );
}
