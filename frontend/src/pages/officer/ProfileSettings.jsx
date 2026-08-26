import { useRef, useState } from "react";
import Layout from "../../components/Layout";
import Icon from "../../components/Icon";
import { updateProfile } from "../../api/api";
import { getUser, setAuth, getToken } from "../../utils/auth";
import profileIllustration from "../../assets/profile-avatar-illustration.svg";
import "../../styles/forms.css";

const NAME_PATTERN = /^[A-Za-z]+(?:\s+[A-Za-z]+)*$/;

const getStoredProfilePicture = (user) => {
  const emailKey = user?.email ? `pmts_profile_picture_${user.email}` : null;
  return user?.profile_picture || user?.profilePicture || (emailKey ? localStorage.getItem(emailKey) : "") || "";
};

export default function ProfileSettings() {
  const currentUser = getUser();
  const displayName = currentUser?.full_name || currentUser?.name || "PMTS User";
  const initials = displayName
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "U";

  const [form, setForm] = useState({
    full_name: displayName,
    email: currentUser?.email || "",
    phone: currentUser?.phone || "",
    department: currentUser?.department || "",
  });

  const [profilePicture, setProfilePicture] = useState(() => getStoredProfilePicture(currentUser));
  const [success, setSuccess] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const pictureInputRef = useRef(null);

  const handleChange = (e) => {
    setForm({
      ...form,
      [e.target.name]: e.target.value,
    });
  };

  const handleProfilePictureChange = (e) => {
    const file = e.target.files?.[0];
    setError("");
    setSuccess("");

    if (!file) return;

    if (!file.type.startsWith("image/")) {
      setError("Please choose a valid image file.");
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      setError("Profile picture must be smaller than 2MB.");
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      setProfilePicture(reader.result || "");
    };
    reader.onerror = () => setError("Could not load selected image.");
    reader.readAsDataURL(file);
  };

  const removeProfilePicture = () => {
    setProfilePicture("");
    if (form.email) {
      localStorage.removeItem(`pmts_profile_picture_${form.email}`);
    }
    setSuccess("Profile picture removed. Click Save Changes to update your profile card.");
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");
    if (!NAME_PATTERN.test(form.full_name.trim())) {
      setError("Full name must contain letters and spaces only. Numbers and special characters are not allowed.");
      return;
    }

    setLoading(true);

    try {
      const response = await updateProfile({
        id: currentUser?.id,
        full_name: form.full_name,
        email: form.email,
        phone: form.phone,
        department: form.department,
        profile_picture: profilePicture,
      });

      const data = response.data || {};

      if (data.success) {
        if (form.email && profilePicture) {
          localStorage.setItem(`pmts_profile_picture_${form.email}`, profilePicture);
        } else if (form.email) {
          localStorage.removeItem(`pmts_profile_picture_${form.email}`);
        }

        const updatedUser = {
          ...currentUser,
          full_name: form.full_name,
          name: form.full_name,
          email: form.email,
          phone: form.phone,
          department: form.department,
          profile_picture: profilePicture,
        };

        setAuth(getToken(), updatedUser);
        setSuccess("Profile updated successfully.");
      } else {
        setError(data.message || "Failed to update profile.");
      }
    } catch (err) {
      console.error("PROFILE UPDATE ERROR:", err);
      setError(err.response?.data?.message || "Failed to update profile.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Layout>
      <div className="page-wrapper">
        <div className="page-title-with-icon">
          <span className="page-title-icon"><Icon name="user" size={20} /></span>
          <div>
            <div className="page-title">Profile Settings</div>
            <div className="page-subtitle">Update your profile picture and account details</div>
          </div>
        </div>

        {success && <div className="alert alert-success">{success}</div>}
        {error && <div className="alert alert-danger">{error}</div>}

        <input
          ref={pictureInputRef}
          type="file"
          accept="image/*"
          onChange={handleProfilePictureChange}
          hidden
        />

        <div className="profile-settings-grid">
          <div className="profile-summary-card">
            <img
              className="profile-summary-image"
              src={profileIllustration}
              alt="User profile illustration"
            />

            <button
              type="button"
              className="profile-picture-frame profile-picture-clickable"
              title="Click to add or change profile image"
              onClick={() => pictureInputRef.current?.click()}
            >
              {profilePicture ? (
                <img src={profilePicture} alt="Profile" className="profile-picture-preview" />
              ) : (
                <div className="profile-avatar-circle">{initials}</div>
              )}
              <span className="profile-picture-edit-badge"><Icon name="upload" size={13} /></span>
            </button>

            <h3>{form.full_name || displayName}</h3>
            <p>{currentUser?.role?.replaceAll("_", " ") || "PMTS User"}</p>
            <div className="profile-summary-meta">
              <span><Icon name="message" size={15} /> {form.email || "No email"}</span>
              <span><Icon name="building" size={15} /> {form.department || "No department"}</span>
            </div>
          </div>

          <div className="form-card profile-form-card">
            <form onSubmit={handleSubmit}>
              <div className="form-section-title">Profile Picture</div>
              <div className="profile-picture-upload-card">
                <button
                  type="button"
                  className="profile-upload-preview profile-upload-preview-button"
                  title="Click to add or change profile image"
                  onClick={() => pictureInputRef.current?.click()}
                >
                  {profilePicture ? (
                    <img src={profilePicture} alt="Selected profile" />
                  ) : (
                    <span>{initials}</span>
                  )}
                </button>

                <div className="profile-upload-content">
                  <label className="form-label">Upload profile picture</label>
                  <p>Use a clear image. Maximum file size is 2MB.</p>
                  <div className="profile-upload-actions">
                    <button
                      type="button"
                      className="btn btn-secondary profile-upload-btn"
                      onClick={() => pictureInputRef.current?.click()}
                    >
                      <Icon name="upload" size={16} />
                      Choose Image
                    </button>
                    {profilePicture && (
                      <button type="button" className="btn btn-light" onClick={removeProfilePicture}>
                        Remove
                      </button>
                    )}
                  </div>
                </div>
              </div>

              <div className="form-section-title profile-section-gap">Personal Information</div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Full Name</label>
                  <input
                    className="form-input"
                    name="full_name"
                    value={form.full_name}
                    onChange={handleChange}
                    required
                    pattern="[A-Za-z]+( [A-Za-z]+)*"
                    title="Use letters and spaces only. Numbers and special characters are not allowed."
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Email</label>
                  <input
                    className="form-input"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    required
                  />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Phone</label>
                  <input
                    className="form-input"
                    name="phone"
                    value={form.phone}
                    onChange={handleChange}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Department</label>
                  <input
                    className="form-input"
                    name="department"
                    value={form.department}
                    onChange={handleChange}
                  />
                </div>
              </div>

              <div className="form-footer">
                <button type="submit" className="btn btn-primary" disabled={loading}>
                  <Icon name="success" size={17} />
                  {loading ? "Saving…" : "Save Changes"}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Layout>
  );
}
