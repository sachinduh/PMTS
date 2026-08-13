import { useState } from 'react';
import Layout from '../../components/Layout';
import { submitHelpTicket } from '../../api/api';
import Icon from '../../components/Icon';

export default function HelpSupport() {
  const [form, setForm] = useState({ subject: '', message: '' });
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(''); setSuccess('');
    setLoading(true);
    try {
      await submitHelpTicket(form);
      setSuccess('Your support ticket has been submitted. Our team will respond shortly.');
      setForm({ subject: '', message: '' });
    } catch {
      setError('Failed to submit ticket. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Layout>
      <div className="page-wrapper">
        <div className="page-title page-title-with-icon">
          <span className="page-title-icon"><Icon name="help" size={22} /></span>
          <span>Help & Support</span>
        </div>
        <div className="page-subtitle">Submit a support ticket and our team will help you</div>

        {success && <div className="alert alert-success">{success}</div>}
        {error && <div className="alert alert-danger">{error}</div>}

        <div className="form-card">
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label">Subject</label>
              <input
                className="form-input"
                type="text"
                placeholder="Brief description of your issue"
                value={form.subject}
                onChange={(e) => setForm({ ...form, subject: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label className="form-label">Message</label>
              <textarea
                className="form-textarea"
                placeholder="Describe your issue in detail..."
                rows={6}
                value={form.message}
                onChange={(e) => setForm({ ...form, message: e.target.value })}
                required
              />
            </div>
            <div className="form-footer">
              <button type="submit" className="btn btn-primary" disabled={loading}>
                {loading ? 'Submitting…' : 'Submit Ticket'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Layout>
  );
}
