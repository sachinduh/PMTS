import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import ProcurementStageTracker from '../../components/ProcurementStageTracker';
import {
  getAllProcurements,
  deleteProcurement,
  getNCBScheduleSummary,
  updateProcurementStatus,
} from '../../api/api';
import { useNavigate, useSearchParams } from 'react-router-dom';
import '../../styles/tables.css';
import '../../styles/dashboard.css';
import Icon from '../../components/Icon';

const CLOSED_STATUSES = ['completed', 'cancelled'];

const TRACKING_OPTIONS = [
  { value: 'draft', label: 'Procurement Officer / Draft' },
  { value: 'specification_approval', label: 'Specification Committee' },
  { value: 'tender_preparation', label: 'Tender Preparation / Calling' },
  { value: 'technical_evaluation', label: 'TEC Evaluation' },
  { value: 'bid_evaluation', label: 'BEC Evaluation' },
  { value: 'financial_evaluation', label: 'Accountant / Financial Review' },
  { value: 'purchase_order_issued', label: 'Purchase Order / Award' },
  { value: 'completed', label: 'Completed' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'cancelled', label: 'Cancelled' },
];

function normalizeStatus(procurement) {
  return procurement?.status || procurement?.current_status || 'draft';
}

function isActiveProcurement(procurement) {
  const status = normalizeStatus(procurement);
  return status !== 'draft' && !CLOSED_STATUSES.includes(status);
}

function formatDate(value) {
  if (!value) return '—';
  return String(value).slice(0, 10);
}

function getFilterTitle({ status, type, view, taskFilter, stage }) {
  if (stage) return `${stage} Procurements`;
  if (taskFilter === 'overdue') return 'Procurements with Overdue Schedule Tasks';
  if (taskFilter === 'upcoming') return 'Procurements with Upcoming Schedule Tasks';
  if (view === 'active') return 'Active / In Progress Procurements';
  if (view === 'recent') return 'Recent Procurements';
  if (status === 'draft') return 'Draft Procurements';
  if (status === 'completed') return 'Completed Procurements';
  if (type) return `${type} Procurements`;
  return 'Procurement Management';
}

export default function ProcurementManagement() {
  const [procurements, setProcurements] = useState([]);
  const [scheduleSummary, setScheduleSummary] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stageDrafts, setStageDrafts] = useState({});
  const [updatingId, setUpdatingId] = useState(null);
  const [selectedProcurement, setSelectedProcurement] = useState(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const statusFilter = searchParams.get('status') || '';
  const typeFilter = searchParams.get('type') || '';
  const viewFilter = searchParams.get('view') || '';
  const taskFilter = searchParams.get('task_filter') || '';
  const stageFilter = searchParams.get('stage') || '';
  const hasFilter = Boolean(statusFilter || typeFilter || viewFilter || taskFilter || stageFilter);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [procurementRes, scheduleRes] = await Promise.allSettled([
        getAllProcurements({ limit: 100 }),
        getNCBScheduleSummary(),
      ]);

      if (procurementRes.status === 'fulfilled') {
        const rows = procurementRes.value.data?.procurements || [];
        setProcurements(rows);
        setStageDrafts(rows.reduce((map, row) => ({ ...map, [row.id]: normalizeStatus(row) }), {}));
      }

      if (scheduleRes.status === 'fulfilled') {
        setScheduleSummary(scheduleRes.value.data?.summary || []);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  const scheduleMap = useMemo(() => {
    return scheduleSummary.reduce((map, item) => {
      map[item.id] = item;
      return map;
    }, {});
  }, [scheduleSummary]);

  const stageCounts = useMemo(() => {
    return procurements.reduce((map, item) => {
      const label = item.current_stage_label || item.current_location || 'Procurement Officer';
      map[label] = (map[label] || 0) + 1;
      return map;
    }, {});
  }, [procurements]);

  const filteredProcurements = useMemo(() => {
    let list = [...procurements];

    if (typeFilter) list = list.filter((procurement) => procurement.procurement_type === typeFilter);
    if (statusFilter) list = list.filter((procurement) => normalizeStatus(procurement) === statusFilter);
    if (stageFilter) list = list.filter((procurement) => (procurement.current_stage_label || procurement.current_location) === stageFilter);
    if (viewFilter === 'active') list = list.filter(isActiveProcurement);
    if (taskFilter === 'overdue') list = list.filter((procurement) => (scheduleMap[procurement.id]?.overdue_count || 0) > 0);
    if (taskFilter === 'upcoming') list = list.filter((procurement) => (scheduleMap[procurement.id]?.upcoming_count || 0) > 0);
    if (viewFilter === 'recent') list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));

    return list;
  }, [procurements, scheduleMap, stageFilter, statusFilter, taskFilter, typeFilter, viewFilter]);

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this procurement?')) return;
    try {
      await deleteProcurement(id);
      fetchData();
    } catch {
      alert('Failed to delete.');
    }
  };

  const handleStageChange = async (row) => {
    const nextStatus = stageDrafts[row.id] || normalizeStatus(row);
    if (nextStatus === normalizeStatus(row)) {
      setError('Please select a different tracking stage first.');
      setMessage('');
      return;
    }

    const selected = TRACKING_OPTIONS.find((item) => item.value === nextStatus);
    const ok = window.confirm(`Move ${row.procurement_id || row.title} to ${selected?.label || nextStatus}?`);
    if (!ok) return;

    setUpdatingId(row.id);
    setError('');
    setMessage('');
    try {
      await updateProcurementStatus({
        id: row.id,
        new_status: nextStatus,
        remarks: `Tracking updated to ${selected?.label || nextStatus}.`,
      });
      setMessage('Procurement tracking stage updated successfully.');
      await fetchData();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update tracking stage.');
    } finally {
      setUpdatingId(null);
    }
  };

  const columns = [
    { key: 'id', label: '#' },
    { key: 'title', label: 'Title' },
    { key: 'tender_number', label: 'Tender No.' },
    { key: 'procurement_type', label: 'Type', render: (val) => <span className="badge badge-info">{val}</span> },
    { key: 'estimated_amount', label: 'Amount', render: (val) => `Rs. ${Number(val || 0).toLocaleString()}` },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status', render: (val, row) => <StatusBadge status={val || normalizeStatus(row)} /> },
    {
      key: 'current_location',
      label: 'Now At',
      render: (_, row) => (
        <button className="tracking-chip" onClick={() => setSelectedProcurement(row)} title="View tracking path">
          <span className="tracking-chip-icon"><Icon name={row.tracking_stage?.icon || 'location'} size={16} /></span>
          <span>{row.current_stage_label || row.current_location || 'Procurement Officer'}</span>
        </button>
      ),
    },
    {
      key: 'schedule',
      label: 'Schedule',
      render: (_, row) => {
        const summary = scheduleMap[row.id] || {};
        return (
          <div>
            <div className="text-xs text-muted">Progress: {summary.progress ?? 0}%</div>
            <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap', marginTop: '4px' }}>
              <span className="badge badge-danger">Overdue {summary.overdue_count || 0}</span>
              <span className="badge badge-info">Upcoming {summary.upcoming_count || 0}</span>
              <span className="badge badge-gray">Skipped {summary.skipped_count || 0}</span>
            </div>
            {summary.next_task?.planned_date && (
              <div className="text-xs text-muted" style={{ marginTop: '4px' }}>
                Next: {formatDate(summary.next_task.planned_date)}
              </div>
            )}
          </div>
        );
      },
    },
    {
      key: 'move_stage',
      label: 'Move Tracking',
      render: (_, row) => (
        <div className="stage-update-control">
          <select
            value={stageDrafts[row.id] || normalizeStatus(row)}
            onChange={(e) => setStageDrafts((prev) => ({ ...prev, [row.id]: e.target.value }))}
          >
            {TRACKING_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <button
            className="action-btn edit"
            onClick={() => handleStageChange(row)}
            disabled={updatingId === row.id}
          >
            {updatingId === row.id ? 'Saving...' : 'Update'}
          </button>
        </div>
      ),
    },
    {
      key: 'actions', label: 'Actions',
      render: (_, row) => (
        <>
          <button className="action-btn view" onClick={() => setSelectedProcurement(row)}>Track</button>
          <button className="action-btn edit" onClick={() => navigate(`/officer-ncb-schedule/${row.id}`)}>Schedule</button>
          <button className="action-btn reject" onClick={() => handleDelete(row.id)}>Delete</button>
        </>
      ),
    },
  ];

  const pageTitle = getFilterTitle({ status: statusFilter, type: typeFilter, view: viewFilter, taskFilter, stage: stageFilter });

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="section-header">
          <div>
            <div className="page-title"> {pageTitle}</div>
            <div className="page-subtitle">
              Manage procurements and track where each one is now. Director approval is removed; Director can view and track only.
            </div>
          </div>
          <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
            {hasFilter && (
              <button className="btn btn-outline" onClick={() => navigate('/officer-management')}>
                Clear Filter
              </button>
            )}
            <button className="btn btn-primary" onClick={() => navigate('/officer-create')}>
               New Procurement
            </button>
          </div>
        </div>

        {message && <div className="alert alert-success">{message}</div>}
        {error && <div className="alert alert-danger">{error}</div>}

        <div className="card" style={{ marginBottom: '20px' }}>
          <div className="section-title" style={{ marginBottom: '14px' }}>Current Location Summary</div>
          <div className="tracking-summary-grid">
            {Object.entries(stageCounts).length === 0 ? (
              <div className="text-muted">No procurement tracking data available.</div>
            ) : Object.entries(stageCounts).map(([label, count]) => (
              <button
                key={label}
                className="tracking-summary-card"
                onClick={() => navigate(`/officer-management?stage=${encodeURIComponent(label)}`)}
              >
                <span className="tracking-summary-count">{count}</span>
                <span className="tracking-summary-label">{label}</span>
              </button>
            ))}
          </div>
        </div>

        {hasFilter && (
          <div className="alert alert-info">
            Showing {filteredProcurements.length} record(s) for: <strong>{pageTitle}</strong>
          </div>
        )}

        {selectedProcurement && (
          <div className="card" style={{ marginBottom: '20px' }}>
            <div className="section-header dashboard-card-header">
              <div>
                <div className="section-title">Tracking Path: {selectedProcurement.title}</div>
                <p className="text-muted text-sm">Current location: {selectedProcurement.current_stage_label || selectedProcurement.current_location}</p>
              </div>
              <button className="btn btn-outline btn-sm" onClick={() => setSelectedProcurement(null)}>Close</button>
            </div>
            <ProcurementStageTracker currentStage={selectedProcurement.tracking_stage} />
          </div>
        )}

        <div className="card" style={{ padding: '20px' }}>
          <DataTable
            columns={columns}
            data={filteredProcurements}
            loading={loading}
            emptyMessage="No procurements found for this selection."
          />
        </div>
      </div>
    </Layout>
  );
}