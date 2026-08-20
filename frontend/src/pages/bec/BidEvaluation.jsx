import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import { getBECEvaluations, submitBECEvaluation } from '../../api/api';

export default function BidEvaluation() {
  const [evaluations, setEvaluations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState({ procurement_id: '', bid_amount: '', compliance: 'compliant', remarks: '' });
  const [message, setMessage] = useState('');

  useEffect(() => {
    getBECEvaluations()
      .then(res => setEvaluations(res.data?.evaluations || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await submitBECEvaluation(form);
      setMessage('BEC evaluation submitted successfully.');
      setForm({ procurement_id: '', bid_amount: '', compliance: 'compliant', remarks: '' });
    } catch {
      setMessage('Failed to submit. Please try again.');
    }
  };

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Bid Evaluation</div>
        <div className="page-subtitle">Evaluate bids for procurement items</div>

        {message && <div className="alert alert-info">{message}</div>}

        <div className="dashboard-grid">
          <div className="card">
            <div className="section-title" style={{ marginBottom: '16px' }}>Submit Bid Evaluation</div>
            <form onSubmit={handleSubmit}>
              <div className="form-group">
                <label className="form-label">Procurement</label>
                <select className="form-select" value={form.procurement_id} onChange={e => setForm({ ...form, procurement_id: e.target.value })} required>
                  <option value="">— Select procurement —</option>
                  {evaluations.map(ev => <option key={ev.id} value={ev.id}>{ev.file_name || ev.title} — {ev.current_task_label || 'BEC'}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label className="form-label">Bid Amount (Rs.)</label>
                <input className="form-input" type="number" min="0" value={form.bid_amount} onChange={e => setForm({ ...form, bid_amount: e.target.value })} required />
              </div>
              <div className="form-group">
                <label className="form-label">Compliance</label>
                <select className="form-select" value={form.compliance} onChange={e => setForm({ ...form, compliance: e.target.value })}>
                  <option value="compliant">Compliant</option>
                  <option value="non_compliant">Non-Compliant</option>
                  <option value="conditional">Conditional</option>
                </select>
              </div>
              <div className="form-group">
                <label className="form-label">Remarks</label>
                <textarea className="form-textarea" rows={3} value={form.remarks} onChange={e => setForm({ ...form, remarks: e.target.value })} />
              </div>
              <button type="submit" className="btn btn-primary w-full">Submit</button>
            </form>
          </div>

          <div className="card">
            <div className="section-title" style={{ marginBottom: '16px' }}>Pending Bid Evaluations</div>
            {loading ? <div className="spinner" /> : evaluations.length === 0 ? (
              <div className="empty-state"><div className="empty-state-icon"></div><div className="empty-state-text">No bid evaluations assigned.</div></div>
            ) : evaluations.map((ev, i) => (
              <div key={ev.id || i} style={{ padding: '12px 0', borderBottom: '1px solid var(--border)' }}>
                <div style={{ fontWeight: 600, marginBottom: '4px' }}>{ev.file_name || ev.title}</div>
                <div className="text-muted text-xs" style={{ marginBottom: '6px' }}>{ev.current_task_label || 'BEC'}</div>
                <StatusBadge status={ev.status || 'pending'} />
              </div>
            ))}
          </div>
        </div>
      </div>
    </Layout>
  );
}
