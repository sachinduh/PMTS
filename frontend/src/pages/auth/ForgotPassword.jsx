import { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../../api/api';
import forgotIllustration from '../../assets/auth-forgot-illustration.svg';
import '../../styles/auth.css';

export default function ForgotPassword() {
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

      // Do not show local SMTP/email configuration notices in the interface.
      // A success alert is shown only when an email was actually sent.
      if (data.email_sent === true) {
        setMessage(data.message || 'Password reset link has been sent to your email.');
      }

      if (data.dev_reset_link) {
        setDevResetLink(data.dev_reset_link);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to process reset request. Please try again.');
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
          alt="Secure password reset illustration"
        />
        <h1>Account Recovery</h1>
        <p>Enter your registered email address and PMTS will generate a secure reset link.</p>
        <div className="auth-brand-features">
          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">Secure one-time reset token</span>
          </div>
          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">Reset links expire automatically</span>
          </div>
        </div>
      </div>

      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Forgot Password</h2>
            <p>Enter your email to receive a password reset link</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}
          {message && <div className="alert alert-success">{message}</div>}

          {devResetLink && (
            <div className="auth-dev-reset-box">
              <a className="auth-reset-link-button" href={devResetLink}>Open Reset Password Page</a>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="forgot-email">Email Address</label>
              <input
                id="forgot-email"
                className="form-input"
                type="email"
                placeholder="you@hospital.gov"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>

            <button
              id="forgot-submit-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading}
            >
              {loading ? 'Sending…' : 'Send Reset Link'}
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
