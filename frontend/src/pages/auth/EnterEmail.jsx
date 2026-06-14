import { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../../api/api';
import '../../styles/auth.css';

export default function EnterEmail() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    setLoading(true);
    try {
      await forgotPassword({ email });
      setMessage('A verification code has been sent to your email.');
    } catch {
      setError('Unable to process request. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-brand">
        <div className="auth-brand-logo">🏥</div>
        <h1>PMTS Portal</h1>
        <p>Password recovery made easy and secure.</p>
      </div>

      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Enter Your Email</h2>
            <p>We&apos;ll send you a verification code</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}
          {message && <div className="alert alert-success">{message}</div>}

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

          <div className="auth-links">
            <Link to="/login">← Back to Login</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
