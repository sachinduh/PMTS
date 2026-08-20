import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { getRegistrationRoles, register } from "../../api/api";
import registerIllustration from "../../assets/auth-register-illustration.svg";
import PasswordInput from "../../components/PasswordInput";
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

const STAFF_REQUESTABLE_ROLES = [
  { value: "director", label: "Director" },
  { value: "accountant", label: "Accountant" },
  { value: "procurement_officer", label: "Procurement Officer" },
  { value: "bec_member", label: "BEC Committee Member" },
  { value: "specification_committee", label: "Specification Committee Member" },
];

const FIRST_SETUP_ROLE_OPTIONS = [
  ...STAFF_REQUESTABLE_ROLES,
  { value: "it_admin", label: "IT Admin" },
];

const NAME_PATTERN = /^[A-Za-z]+(?:\s+[A-Za-z]+)*$/;
const PASSWORD_HELP_TEXT = "Use at least 8 characters with letters, numbers, and a special character. Example: Admin@123";

const getPasswordValidationError = (password) => {
  const hasLetter = /[A-Za-z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSpecial = /[^A-Za-z0-9]/.test(password);

  if (password.length < 8) {
    return "Password must contain letters, numbers, and at least one special character. Minimum length is 8 characters. Example: Admin@123";
  }

  if (!hasLetter && hasNumber && !hasSpecial) {
    return "Password cannot contain numbers only. Use letters, numbers, and at least one special character. Example: Admin@123";
  }

  if (!hasLetter || !hasNumber || !hasSpecial) {
    return "Password must contain letters, numbers, and at least one special character. Minimum length is 8 characters. Example: Admin@123";
  }

  return "";
};

export default function Register() {
  const [form, setForm] = useState({
    full_name: "",
    email: "",
    phone: "",
    nic: "",
    user_type: "Hospital Staff",
    department: "",
    organization: "",
    requested_role: "",
    password: "",
    confirm_password: "",
  });
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);
  const [roleOptions, setRoleOptions] = useState(FIRST_SETUP_ROLE_OPTIONS);
  const [roleOptionsLoading, setRoleOptionsLoading] = useState(true);
  const [itAdminAvailable, setItAdminAvailable] = useState(null);
  const [roleLoadError, setRoleLoadError] = useState("");

  useEffect(() => {
    let isMounted = true;

    const loadRegistrationRoles = async () => {
      try {
        const res = await getRegistrationRoles();
        const roles = Array.isArray(res.data?.roles) && res.data.roles.length > 0
          ? res.data.roles
          : STAFF_REQUESTABLE_ROLES;

        if (!isMounted) return;

        setRoleOptions(roles);
        setItAdminAvailable(Boolean(res.data?.it_admin_available));
        setRoleLoadError("");

        if (!roles.some((role) => role.value === "it_admin")) {
          setForm((current) => (
            current.requested_role === "it_admin"
              ? { ...current, requested_role: "" }
              : current
          ));
        }
      } catch (err) {
        console.log("REGISTRATION ROLES ERROR:", err);
        if (!isMounted) return;
        // Do not interpret a network/PHP error as proof that an IT Admin exists.
        // Keep IT Admin visible; register.php performs the authoritative database check.
        setRoleOptions(FIRST_SETUP_ROLE_OPTIONS);
        setItAdminAvailable(null);
        setRoleLoadError(
          err.response?.data?.message ||
            "Could not verify the IT Admin status. IT Admin remains selectable and the server will verify it when you submit.",
        );
      } finally {
        if (isMounted) setRoleOptionsLoading(false);
      }
    };

    loadRegistrationRoles();

    return () => {
      isMounted = false;
    };
  }, []);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    const cleanName = form.full_name.trim();
    if (!NAME_PATTERN.test(cleanName)) {
      setError("Full name must contain letters and spaces only. Numbers and special characters are not allowed.");
      return;
    }
    const passwordError = getPasswordValidationError(form.password);
    if (passwordError) {
      setError(passwordError);
      return;
    }
    if (form.password !== form.confirm_password) {
      setError("Passwords do not match.");
      return;
    }
    if (!roleOptions.some((role) => role.value === form.requested_role)) {
      setError("Please select an available requested role. IT Admin is available only before the first IT Admin account is registered.");
      return;
    }
    setLoading(true);
    try {
      const res = await register(form);
      const registeredUser = res.data?.data;
      if (registeredUser?.role === "it_admin" && registeredUser?.status === "active") {
        setSuccess("First IT Admin registration successful. Your account is active. You can login now.");
      } else {
        setSuccess(res.data?.message || "Registration successful. Please wait for IT Admin approval.");
      }
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
              Secure admin setup workflow
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
                placeholder="Amal Perera"
                value={form.full_name}
                onChange={handleChange}
                required
                pattern="[A-Za-z]+( [A-Za-z]+)*"
                title="Use letters and spaces only. Numbers and special characters are not allowed."
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

            {/* Requested Role */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-requested-role">
                Requested Role
              </label>
              <select
                id="reg-requested-role"
                className="form-input"
                name="requested_role"
                value={form.requested_role}
                onChange={handleChange}
                required
                disabled={roleOptionsLoading}
              >
                <option value="">
                  {roleOptionsLoading ? "Loading roles…" : "— Select requested role —"}
                </option>
                {roleOptions.map((role) => (
                  <option key={role.value} value={role.value}>
                    {role.label}
                  </option>
                ))}
              </select>
              <small className="form-help">
                Normal staff roles wait for IT Admin approval. IT Admin appears here only when no IT Admin account exists yet.
              </small>
              {roleLoadError && (
                <div className="role-security-note">
                  {roleLoadError}
                </div>
              )}
              {itAdminAvailable === false && !roleLoadError && (
                <div className="role-security-note">
                  IT Admin registration is unavailable because the connected pmtss_db database contains an active IT Admin account.
                </div>
              )}
              {itAdminAvailable === true && (
                <div className="role-security-note">
                  First setup mode: IT Admin is available because no IT Admin account exists yet. After registration, this option disappears automatically.
                </div>
              )}
            </div>

            {/* Password */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-password">
                Password
              </label>
              <PasswordInput
                id="reg-password"
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

            {/* Confirm Password */}
            <div className="form-group">
              <label className="form-label" htmlFor="reg-confirm">
                Confirm Password
              </label>
              <PasswordInput
                id="reg-confirm"
                name="confirm_password"
                placeholder="Re-enter your password"
                value={form.confirm_password}
                onChange={handleChange}
                autoComplete="new-password"
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
