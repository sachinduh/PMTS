import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { login } from "../../api/api";
import { setAuth } from "../../utils/auth";
import loginIllustration from "../../assets/auth-login-illustration.svg";
import { getDefaultRoute } from "../../utils/roleRoutes";
import "../../styles/auth.css";

export default function Login() {
  const navigate = useNavigate();

  const [form, setForm] = useState({
    email: "",
    password: "",
  });

  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const timeoutMessage = sessionStorage.getItem("sessionTimeoutMessage");

    if (timeoutMessage) {
      setError(timeoutMessage);
      sessionStorage.removeItem("sessionTimeoutMessage");
    }
  }, []);

  const handleChange = (e) => {
    setForm({
      ...form,
      [e.target.name]: e.target.value,
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const res = await login(form);

      console.log("LOGIN RESPONSE:", res.data);

      const { success, token, user, message } = res.data;

      if (!success || !token || !user) {
        setError(message || "Login failed. Invalid server response.");
        return;
      }

      // Save login data in browser
      localStorage.setItem("token", token);
      localStorage.setItem("user", JSON.stringify(user));

      // Also save using your auth helper
      setAuth(token, user);

      const route = getDefaultRoute(user.role);
      navigate(route);
    } catch (err) {
      console.log("LOGIN ERROR:", err);
      console.log("BACKEND RESPONSE:", err.response?.data);

      setError(
        err.response?.data?.message ||
          err.message ||
          "Login failed. Please check your credentials."
      );
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
          src={loginIllustration}
          alt="Hospital procurement tracking illustration"
        />

        <h1>PMTS Portal</h1>

        <p>
          Procurement Management & Tracking System for streamlined hospital
          procurement workflows.
        </p>

        <div className="auth-brand-features">
          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">
              End-to-end procurement tracking
            </span>
          </div>

          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">
              Role-based workflow
            </span>
          </div>

          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">
              Real-time reports & analytics
            </span>
          </div>
        </div>
      </div>

      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Welcome back</h2>
            <p>Sign in to your PMTS account</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}

          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="login-email">
                Email Address
              </label>

              <input
                id="login-email"
                className="form-input"
                type="email"
                name="email"
                placeholder="you@hospital.gov"
                value={form.email}
                onChange={handleChange}
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="login-password">
                Password
              </label>

              <input
                id="login-password"
                className="form-input"
                type="password"
                name="password"
                placeholder="Enter your password"
                value={form.password}
                onChange={handleChange}
                required
              />
            </div>

            <div style={{ textAlign: "right", marginBottom: "16px" }}>
              <Link
                to="/forgot-password"
                style={{ fontSize: "0.875rem", color: "var(--primary)" }}
              >
                Forgot password?
              </Link>
            </div>

            <button
              id="login-submit-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading}
            >
              {loading ? "Signing in…" : "Sign In"}
            </button>
          </form>

          <div className="auth-links">
            Don&apos;t have an account? <Link to="/register">Register here</Link>
          </div>
        </div>
      </div>
    </div>
  );
}