import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import { getDelayAlerts, runScheduleDelayCheck } from '../../api/api';
import '../../styles/tables.css';
import '../../styles/dashboard.css';

function formatDate(value) {
  if (!value) return '—';
  return String(value).slice(0, 10);
}

function levelBadge(alert) {
  const isRed = alert.alert_color === 'red';
  return (
    <span className={`badge ${isRed ? 'badge-danger' : 'badge-warning'}`}>
      {isRed ? ' Red' : ' Yellow'}
    </span>
  );
}

export default function DirectorDelayAlerts() {
  const [alerts, setAlerts] = useState([]);
  const [summary, setSummary] = useState({});
  const [scanResult, setScanResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);

  const loadAlerts = async (runCheck = false) => {
    setLoading(true);
    try {
      if (runCheck) {
        const scan = await runScheduleDelayCheck();
        setScanResult(scan.data || null);
      }
      const res = await getDelayAlerts({ status: 'active' });
      setAlerts(res.data?.alerts || []);
      setSummary(res.data?.summary || {});
    } catch {
      // Keep page usable even if email sending is not configured.
    } finally {
      setLoading(false);
      setRunning(false);
    }
  };

  useEffect(() => {
    loadAlerts(true);
  }, []);

  const manualRun = async () => {
    setRunning(true);
    await loadAlerts(true);
  };

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Schedule Delay Alerts</div>
        <div className="page-subtitle">
          Director receives daily notification/email when a planned schedule date is missed. Yellow = warning, Red = critical delay.
        </div>

        <div className="stats-grid" style={{ marginBottom: '20px' }}>
          <div className="card mini-stat"><div className="mini-stat-value">{summary.active || 0}</div><div className="mini-stat-label">Active Alerts</div></div>
          <div className="card mini-stat"><div className="mini-stat-value">{summary.yellow || 0}</div><div className="mini-stat-label">Yellow Alerts</div></div>
          <div className="card mini-stat"><div className="mini-stat-value">{summary.red || 0}</div><div className="mini-stat-label">Red Alerts</div></div>
          <div className="card mini-stat"><div className="mini-stat-value">{summary.emails_sent || 0}</div><div className="mini-stat-label">Emails Sent</div></div>
        </div>

        {scanResult && (
          <div className="alert alert-info">
            Daily check completed: {scanResult.alerts_created || 0} new alert(s), {scanResult.notifications_created || 0} notification(s), {scanResult.emails_sent || 0} email(s) sent.
          </div>
        )}

        <div className="card" style={{ marginBottom: '20px' }}>
          <div className="section-title" style={{ marginBottom: '8px' }}>Delay Rule</div>
          <p className="text-muted" style={{ marginBottom: 0 }}>
            If planned date is missed, Director is alerted. Days 1–14 are tracked as missed planned date, days 15–21 are Yellow, and day 22 onward is Red. A daily alert is created until the task is completed or skipped.
          </p>
          <button className="btn btn-outline" style={{ marginTop: '12px' }} onClick={manualRun} disabled={running}>
            {running ? 'Checking...' : 'Run Delay Check Now'}
          </button>
        </div>

        {loading ? <div className="loading-page"><div className="spinner" /></div> : (
          <div className="card" style={{ padding: 0 }}>
            <div className="table-wrapper">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Level</th>
                    <th>Procurement</th>
                    <th>Task</th>
                    <th>Planned Date</th>
                    <th>Delayed Days</th>
                    <th>Now At</th>
                    <th>Email</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {alerts.length === 0 ? (
                    <tr><td colSpan={9}><div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No active delay alerts at this time.</div></div></td></tr>
                  ) : alerts.map((a, i) => (
                    <tr key={a.id || i}>
                      <td>{i + 1}</td>
                      <td>{levelBadge(a)}</td>
                      <td>
                        <div className="font-semibold">{a.title || a.procurement_title}</div>
                        <div className="text-muted text-xs">{a.proc_ref_id || a.procurement_code} | {a.tender_number || 'No tender no.'}</div>
                      </td>
                      <td>{a.task_name || '—'}</td>
                      <td>{formatDate(a.expected_date || a.planned_date)}</td>
                      <td><span className="badge badge-danger">{a.delayed_days || 0} day(s)</span></td>
                      <td>{a.current_stage_label || a.current_stage || '—'}</td>
                      <td>
                        <span className={`badge ${a.email_status === 'sent' ? 'badge-success' : a.email_status === 'failed' ? 'badge-danger' : 'badge-gray'}`}>
                          {a.email_status || 'not_sent'}
                        </span>
                      </td>
                      <td><StatusBadge status={a.status || 'active'} /></td>
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
