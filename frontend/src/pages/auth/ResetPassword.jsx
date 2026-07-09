import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { resetPassword } from '../../api/api';
import forgotIllustration from '../../assets/auth-forgot-illustration.svg';
import '../../styles/auth.css';

export default function ResetPassword() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';

  const [form, setForm] = useState({ password: '', confirm_password: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (form.password !== form.confirm_password) {
      setError('Passwords do not match.');
      return;
    }
    setLoading(true);
    try {
      await resetPassword({ token, password: form.password, new_password: form.password });
      navigate('/login?reset=success');
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to reset password. Link may have expired.');
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
          alt="Password reset illustration"
        />
        <h1>Reset Access</h1>
        <p>Create a strong new password to secure your PMTS account.</p>
      </div>

      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Reset Password</h2>
            <p>Enter your new password below</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="reset-password">New Password</label>
              <input
                id="reset-password"
                className="form-input"
                type="password"
                name="password"
                placeholder="Min. 8 characters"
                value={form.password}
                onChange={handleChange}
                required
                minLength={8}
              />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="reset-confirm">Confirm New Password</label>
              <input
                id="reset-confirm"
                className="form-input"
                type="password"
                name="confirm_password"
                placeholder="Re-enter new password"
                value={form.confirm_password}
                onChange={handleChange}
                required
              />
            </div>

            <button
              id="reset-submit-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading}
            >
              {loading ? 'Updating…' : 'Update Password'}
            </button>
          </form>

          <div className="auth-links">
            <Link to="/login" className="professional-auth-back">Back to Login</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
