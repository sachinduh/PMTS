import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import ProcurementStageTracker from '../../components/ProcurementStageTracker';
import Icon from '../../components/Icon';
import { getProcurementById } from '../../api/api';
import { useParams, useNavigate } from 'react-router-dom';
import '../../styles/tables.css';
import '../../styles/dashboard.css';

function DelayBadge({ task }) {
  const delay = task.delay_info;
  if (!delay) return <span className="badge badge-gray">No Delay</span>;
  return (
    <span className={`badge ${delay.alert_color === 'red' ? 'badge-danger' : 'badge-warning'}`}>
      {delay.alert_color === 'red' ? 'Red' : 'Yellow'} - {delay.days_late} day(s)
    </span>
  );
}


function FileTrackingCell({ task }) {
  const summary = task.file_tracking_summary || {};
  const total = Number(summary.total_files || 0);
  const done = Number(summary.completed_files || 0);
  const pending = Number(summary.pending_files || 0);
  const types = Number(summary.type_count || 0);
  if (!total && !types) return <span className="badge badge-gray">No files</span>;
  return (
    <div className="text-sm">
      <strong>{total} file(s)</strong>
      <div className="text-muted text-xs">{done} done / {pending} pending / {types} type(s)</div>
    </div>
  );
}

export default function DirectorProcurementDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [details, setDetails] = useState({ procurement: null, schedule: [], status_history: [] });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) { setLoading(false); return; }
    getProcurementById(id)
      .then(res => setDetails(res.data?.details || { procurement: res.data?.procurement || null, schedule: [], status_history: [] }))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [id]);

  const proc = details.procurement;
  const schedule = details.schedule || [];
  const history = details.status_history || [];
  const trackingStage = details.tracking_stage || proc?.tracking_stage;
  const workflowSteps = details.workflow_steps || [];

  if (loading) return <Layout><div className="loading-page"><div className="spinner" /></div></Layout>;
  if (!proc) return <Layout><div className="page-wrapper"><div className="alert alert-danger">Procurement not found.</div></div></Layout>;

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="procurement-solid-header sticky-procurement-header">
          <button className="btn btn-outline btn-sm" onClick={() => navigate(-1)}><Icon name="back" size={16} /> Back</button>
          <div className="procurement-solid-header-text">
            <div className="page-title" style={{ marginBottom: 0 }}>{proc.title}</div>
            <div className="page-subtitle" style={{ marginBottom: 0 }}>Tender: {proc.tender_number || '—'} | Procurement ID: {proc.procurement_id}</div>
          </div>
        </div>

        <div className="card">
          <div className="section-title" style={{ marginBottom: '16px' }}>Procurement Details</div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '24px' }}>
            {[
              ['File Name', proc.file_name],
              ['Procurement Type', proc.procurement_type],
              ['Category', proc.category],
              ['Estimated Amount', `Rs. ${Number(proc.estimated_amount || 0).toLocaleString()}`],
              ['Priority', proc.priority],
              ['Received Date', proc.received_date],
              ['Payment Date', proc.payment_date],
              ['Created By', proc.created_by_name],
              ['Current Location', proc.current_stage_label || proc.current_location],
              ['Status', ''],
            ].map(([label, value], i) => (
              <div key={i}>
                <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', marginBottom: '4px' }}>{label}</div>
                {label === 'Status' ? <StatusBadge status={proc.status} /> : <div style={{ fontWeight: 500 }}>{value || '—'}</div>}
              </div>
            ))}
          </div>
          {proc.description && (
            <>
              <hr className="divider" />
              <div>
                <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', marginBottom: '8px' }}>Description</div>
                <p style={{ color: 'var(--text-dark)', lineHeight: 1.7 }}>{proc.description}</p>
              </div>
            </>
          )}
        </div>

        <div className="card" style={{ marginTop: '20px' }}>
          <div className="section-title" style={{ marginBottom: '16px' }}>Procurement Current Tracking Location</div>
          <ProcurementStageTracker currentStage={trackingStage} workflowSteps={workflowSteps} />
        </div>

        <div className="card" style={{ marginTop: '20px' }}>
          <div className="section-title" style={{ marginBottom: '16px' }}>Status Tracking History</div>
          <div className="table-wrapper">
            <table className="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Old Status</th>
                  <th>New Status</th>
                  <th>Changed By</th>
                  <th>Date / Time</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                {history.length === 0 ? (
                  <tr><td colSpan={6}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No status history available.</div></div></td></tr>
                ) : history.map((h, i) => (
                  <tr key={h.id || i}>
                    <td>{i + 1}</td>
                    <td>{h.old_status ? <StatusBadge status={h.old_status} /> : '—'}</td>
                    <td><StatusBadge status={h.new_status} /></td>
                    <td>{h.changed_by_name || '—'}</td>
                    <td>{h.changed_at ? new Date(h.changed_at).toLocaleString('en-LK') : '—'}</td>
                    <td>{h.remarks || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card" style={{ marginTop: '20px' }}>
            <div className="section-title" style={{ marginBottom: '16px' }}>Schedule Tracking</div>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Responsible</th>
                    <th>Planned Date</th>
                    <th>Actual Date</th>
                    <th>Status</th>
                    <th>Delay</th>
                    <th>File Tracking</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  {schedule.length === 0 ? (
                    <tr><td colSpan={9}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No schedule records available.</div></div></td></tr>
                  ) : schedule.map((task, i) => (
                    <tr key={task.id || i}>
                      <td>{i + 1}</td>
                      <td>{task.task_name}</td>
                      <td>{task.responsible_role || '—'}</td>
                      <td>{task.planned_date || '—'}</td>
                      <td>{task.actual_date || '—'}</td>
                      <td><StatusBadge status={task.status || 'pending'} /></td>
                      <td><DelayBadge task={task} /></td>
                      <td><FileTrackingCell task={task} /></td>
                      <td>{task.remarks || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
      </div>
    </Layout>
  );
}
