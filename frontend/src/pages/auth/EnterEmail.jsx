import { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../../api/api';
import forgotIllustration from '../../assets/auth-forgot-illustration.svg';
import '../../styles/auth.css';

export default function EnterEmail() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [devResetLink, setDevResetLink] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    setDevResetLink('');
    setLoading(true);
    try {
      const res = await forgotPassword({ email });
      const data = res.data || {};
      setMessage(data.message || 'If this email is registered, a reset link has been generated.');
      if (data.dev_reset_link) setDevResetLink(data.dev_reset_link);
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to process request. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-brand">
        <div className="auth-brand-logo">PMTS</div>
        <img
          className="auth-brand-image"
          src={forgotIllustration}
          alt="Secure email verification illustration"
        />
        <h1>Secure Recovery</h1>
        <p>Password recovery made easy and secure for PMTS users.</p>
      </div>

      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Enter Your Email</h2>
            <p>We will send your reset instructions to the registered email</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}
          {message && <div className="alert alert-success">{message}</div>}
          {devResetLink && (
            <div className="auth-dev-reset-box">
              <strong>Development reset link</strong>
              <p>Email sending is not configured in local XAMPP. Use this link for testing.</p>
              <a className="auth-reset-link-button" href={devResetLink}>Open Reset Password Page</a>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="enter-email">Email Address</label>
              <input
                id="enter-email"
                className="form-input"
                type="email"
                placeholder="you@hospital.gov"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <button
              id="enter-email-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading}
            >
              {loading ? 'Sending…' : 'Continue'}
            </button>
          </form>

          <div className="auth-links auth-links-row">
            <Link to="/login" className="professional-auth-back">Back to Login</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
