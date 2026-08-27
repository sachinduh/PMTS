import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { resetPassword } from '../../api/api';
import forgotIllustration from '../../assets/auth-forgot-illustration.svg';
import PasswordInput from '../../components/PasswordInput';
import '../../styles/auth.css';

const PASSWORD_HELP_TEXT = 'Use at least 8 characters with letters, numbers, and a special character. Example: Admin@123';

const getPasswordValidationError = (password) => {
  const hasLetter = /[A-Za-z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSpecial = /[^A-Za-z0-9]/.test(password);

  if (password.length < 8) {
    return 'Password must contain letters, numbers, and at least one special character. Minimum length is 8 characters. Example: Admin@123';
  }

  if (!hasLetter && hasNumber && !hasSpecial) {
    return 'Password cannot contain numbers only. Use letters, numbers, and at least one special character. Example: Admin@123';
  }

  if (!hasLetter || !hasNumber || !hasSpecial) {
    return 'Password must contain letters, numbers, and at least one special character. Minimum length is 8 characters. Example: Admin@123';
  }

  return '';
};

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
    const passwordError = getPasswordValidationError(form.password);
    if (passwordError) {
      setError(passwordError);
      return;
    }
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

          {error && (
            <div className="alert alert-danger">
              {error}{' '}
              <Link to="/forgot-password">Request a new reset link</Link>
            </div>
          )}
          {!token && (
            <div className="alert alert-danger">This reset link is missing its security token. Request a new password reset link.</div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="reset-password">New Password</label>
              <PasswordInput
                id="reset-password"
                name="password"
                placeholder="Example: Admin@123"
                value={form.password}
                onChange={handleChange}
                autoComplete="new-password"
                required
                minLength={8}
                title={PASSWORD_HELP_TEXT}
              />
              <small className="form-help">{PASSWORD_HELP_TEXT}</small>
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="reset-confirm">Confirm New Password</label>
              <PasswordInput
                id="reset-confirm"
                name="confirm_password"
                placeholder="Re-enter new password"
                value={form.confirm_password}
                onChange={handleChange}
                autoComplete="new-password"
                required
              />
            </div>

            <button
              id="reset-submit-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading || !token}
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
