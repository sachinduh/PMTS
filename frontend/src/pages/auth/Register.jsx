import { useState } from "react";
import { Link } from "react-router-dom";
import { register } from "../../api/api";
import registerIllustration from "../../assets/auth-register-illustration.svg";
import "../../styles/auth.css";

const DEPARTMENTS = [
  "Administration",
  "Cardiology",
  "Emergency",
  "Finance",
  "General Medicine",
  "ICU",
  "Laboratory",
  "OPD",
  "Orthopaedics",
  "Paediatrics",
  "Pharmacy",
  "Radiology",
  "Surgery",
  "Other",
];

export default function Register() {
  const [form, setForm] = useState({
    full_name: "",
    email: "",
    phone: "",
    nic: "",
    user_type: "Hospital Staff",
    department: "",
    organization: "",
    password: "",
    confirm_password: "",
  });
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    if (form.password !== form.confirm_password) {
      setError("Passwords do not match.");
      return;
    }
    setLoading(true);
    try {
      await register(form);
      setSuccess("Registration successful. Please wait for IT Admin approval.");
    } catch (err) {
      console.log("REGISTER ERROR:", err);
      console.log("BACKEND RESPONSE:", err.response?.data);

      setError(
        err.response?.data?.message ||
          err.message ||
          "Registration failed. Please try again.",
      );
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="auth-page">
        <div className="auth-brand">
          <div className="auth-brand-logo">PMTS</div>
          <img
            className="auth-brand-image"
            src={registerIllustration}
            alt="Hospital staff registration illustration"
          />
          <h1>PMTS Portal</h1>
          <p>Procurement Management & Tracking System</p>
        </div>
        <div className="auth-form-panel">
          <div className="auth-form-container animate-fade-in">
            <div style={{ textAlign: "center" }}>
              <div style={{ fontSize: "4rem", marginBottom: "16px" }}></div>
              <h2 style={{ color: "var(--text-dark)", marginBottom: "12px" }}>
                Registration Submitted!
              </h2>
              <p style={{ color: "var(--text-muted)", marginBottom: "24px" }}>
                {success}
              </p>
              <Link
                to="/login"
                className="auth-submit-btn"
                style={{ display: "inline-block", padding: "10px 32px" }}
              >
                Back to Login
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="auth-page">
      {/* Brand */}
      <div className="auth-brand">
        <div className="auth-brand-logo">PMTS</div>
        <img
          className="auth-brand-image"
          src={registerIllustration}
          alt="Hospital staff registration illustration"
        />
        <h1>PMTS Portal</h1>
        <p>Create your account to start managing procurement workflows.</p>
        <div className="auth-brand-features">
          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">
              Secure role-based access
            </span>
          </div>
          <div className="auth-brand-feature">
            <span className="auth-brand-feature-icon"></span>
            <span className="auth-brand-feature-text">
              Admin approval workflow
            </span>
          </div>
        </div>
      </div>

      {/* Form */}
      <div className="auth-form-panel">
        <div className="auth-form-container animate-fade-in">
          <div className="auth-form-header">
            <h2>Create Account</h2>
            <p>Fill in your details to register</p>
          </div>

          {error && <div className="alert alert-danger">{error}</div>}

          <form onSubmit={handleSubmit}>
            {/* Full Name */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-name">
                Full Name
              </label>
              <input
                id="reg-name"
                className="form-input"
                type="text"
                name="full_name"
                placeholder="Dr. Amal Perera"
                value={form.full_name}
                onChange={handleChange}
                required
              />
            </div>

            {/* Email */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-email">
                Email Address
              </label>
              <input
                id="reg-email"
                className="form-input"
                type="email"
                name="email"
                placeholder="amal@hospital.gov"
                value={form.email}
                onChange={handleChange}
                required
              />
            </div>

            {/* Phone */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-phone">
                Phone Number
              </label>
              <input
                id="reg-phone"
                className="form-input"
                type="tel"
                name="phone"
                placeholder="07X-XXXXXXX"
                value={form.phone}
                onChange={handleChange}
                required
              />
            </div>

            {/* NIC */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-nic">
                NIC Number
              </label>
              <input
                id="reg-nic"
                className="form-input"
                type="text"
                name="nic"
                placeholder="XXXXXXXXV or XXXXXXXXXXX"
                value={form.nic}
                onChange={handleChange}
                required
              />
            </div>

            {/* Department */}
            <div className="form-group">
                <label className="form-label" htmlFor="reg-department">
                  Department
                </label>
                <select
                  id="reg-department"
                  className="form-input"
                  name="department"
                  value={form.department}
                  onChange={handleChange}
                  required
                >
                  <option value="">— Select department —</option>
                  {DEPARTMENTS.map((d) => (
                    <option key={d} value={d}>
                      {d}
                    </option>
                  ))}
                </select>
            </div>

            {/* Password */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-password">
                Password
              </label>
              <input
                id="reg-password"
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

            {/* Confirm Password */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-confirm">
                Confirm Password
              </label>
              <input
                id="reg-confirm"
                className="form-input"
                type="password"
                name="confirm_password"
                placeholder="Re-enter your password"
                value={form.confirm_password}
                onChange={handleChange}
                required
              />
            </div>

            <button
              id="register-submit-btn"
              type="submit"
              className="auth-submit-btn"
              disabled={loading}
            >
              {loading ? "Registering…" : "Create Account"}
            </button>
          </form>

          <div className="auth-links">
            Already have an account? <Link to="/login">Sign in</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
