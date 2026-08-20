import { useEffect, useMemo, useRef, useState } from "react";
import Layout from "../../components/Layout";
import Icon from "../../components/Icon";
import hospitalImage from "../../assets/badulla-hospital-login.jpg";
import sajeewaPhoto from "../../assets/about-users/sajeewa-bandara-accountant.jpg";
import himaliPhoto from "../../assets/about-users/himali-wijegunasekara-director.jpg";
import jananeePhoto from "../../assets/about-users/jananee-rasanjana.jpg";
import sachinduPhoto from "../../assets/about-users/sachindu-himsara.jpg";
import udariPhoto from "../../assets/about-users/rashmini-udari.jpg";
import sandunPhoto from "../../assets/about-users/sandun-bandara.jpg";
import {
  addAboutGalleryImage,
  deleteAboutGalleryImage,
  getAboutGalleryImages,
  getAboutUsers,
  updateAboutUserPhoto,
} from "../../api/api";
import { getToken, getUser, setAuth } from "../../utils/auth";
import "../../styles/dashboard.css";

const VERSION = "1.0 (2026)";

const ROLE_LABELS = {
  it_admin: "IT Admin",
  director: "Director",
  accountant: "Accountant",
  procurement_officer: "Procurement Officer",
  bec_member: "BEC Committee Member",
  specification_committee: "Specification Committee Member",
  pending: "Pending User",
  developer: "Developer",
  Accountant: "Accountant",
  "IT Specialist": "IT Specialist",
};

const STATIC_PHOTOS_BY_EMAIL = {
  "sandunbandara382@gmail.com": sandunPhoto,
  "director@thbadulla.health.gov.lk": himaliPhoto,
  "himali@badullahospital.lk": himaliPhoto,
  "jananeerasanjana20030906@gmail.com": jananeePhoto,
  "sachinduhimsara06@gmail.com": sachinduPhoto,
  "rashminiudari@gmail.com": udariPhoto,
  "accountantbadulla@gmail.com": sajeewaPhoto,
  "itspecialistbadulla@badullahospital.lk": sajeewaPhoto,
};

const STATIC_PHOTOS_BY_NAME = {
  "sandun bandara": sandunPhoto,
  "himali wijegunasekara": himaliPhoto,
  "jananee rasanjana": jananeePhoto,
  "sachindu himsara": sachinduPhoto,
  "rashmini udari": udariPhoto,
  "sajeewa bandara": sajeewaPhoto,
};

const FIXED_ABOUT_USERS = [
  {
    full_name: "Sandun Bandara",
    role: "developer",
    email: "sandunbandara382@gmail.com",
    phone: "0740126917",
    university: "Uva Wellassa University of Sri Lanka",
    profile_picture: sandunPhoto,
  },
  {
    full_name: "Himali Wijegunasekara",
    role: "director",
    email: "director@thbadulla.health.gov.lk",
    phone: "0711099594",
    department: "Administration",
    profile_picture: himaliPhoto,
  },
  {
    full_name: "Sachindu Himsara",
    role: "developer",
    email: "sachinduhimsara06@gmail.com",
    phone: "0704368842",
    university: "Uva Wellassa University of Sri Lanka",
    profile_picture: sachinduPhoto,
  },
  {
    full_name: "Rashmini Udari",
    role: "developer",
    email: "rashminiudari@gmail.com",
    phone: "0763581951",
    university: "Uva Wellassa University of Sri Lanka",
    profile_picture: udariPhoto,
  },
  {
    full_name: "Jananee Rasanjana",
    role: "developer",
    email: "jananeerasanjana20030906@gmail.com",
    phone: "0768119373",
    university: "Uva Wellassa University of Sri Lanka",
    profile_picture: jananeePhoto,
  },
  {
    full_name: "Sajeewa Bandara",
    role: "Accountant",
    email: "accountantbadulla@gmail.com",
    phone: "0715946542",
    department: "Administration",
    profile_picture: sajeewaPhoto,
  },
  {
    full_name: "Sanjeewa Jayasekara",
    role: "IT Specialist",
    email: "itspecialistbadulla@badullahospital.lk",
    phone: "0787354495",
    department: "IT Support",
  },
];

const TECHNOLOGIES = [
  "React",
  "Vite",
  "JavaScript",
  "HTML5",
  "CSS3",
  "PHP 8.x",
  "PDO",
  "MySQL / MariaDB",
  "JWT Authentication",
  "RBAC Security",
  "REST API",
  "XAMPP",
];

const SYSTEM_FEATURES = [
  "Role-based dashboard access",
  "NCB time schedule tracking",
  "Task location and file tracking",
  "Delay alerts with allowed delay days",
  "Committee appointment support",
  "Reports, audit logs, and notifications",
  "Account lock after failed login attempts",
  "Single IT Admin registration control",
];

function roleLabel(role) {
  return ROLE_LABELS[role] || role || "PMTS User";
}

function getStaticMemberPhoto(member = {}) {
  const email = String(member.email || "")
    .trim()
    .toLowerCase();
  const name = String(member.full_name || member.name || "")
    .trim()
    .toLowerCase();
  return STATIC_PHOTOS_BY_EMAIL[email] || STATIC_PHOTOS_BY_NAME[name] || "";
}

function normalizeMember(member = {}, index = 0) {
  const normalized = {
    id: member.id || `fallback-${index}`,
    full_name: member.full_name || member.name || "PMTS User",
    role: member.role || member.display_role || "pending",
    email: member.email || "—",
    phone: member.phone || "—",
    department: member.department || "",
    university: member.university || member.University || "",
    organization: member.organization || "",
    profile_picture: member.profile_picture || member.profilePicture || "",
  };

  normalized.profile_picture =
    normalized.profile_picture || getStaticMemberPhoto(normalized);
  return normalized;
}

function mergeDatabaseUsersIntoFixedList(rows = []) {
  const dbRows = Array.isArray(rows) ? rows : [];

  return FIXED_ABOUT_USERS.map((fixedMember, index) => {
    const fixedEmail = String(fixedMember.email || "")
      .trim()
      .toLowerCase();
    const fixedName = String(fixedMember.full_name || "")
      .trim()
      .toLowerCase();
    const matchedDbUser = dbRows.find((row = {}) => {
      const rowEmail = String(row.email || "")
        .trim()
        .toLowerCase();
      const rowName = String(row.full_name || row.name || "")
        .trim()
        .toLowerCase();
      return (
        (fixedEmail && rowEmail === fixedEmail) ||
        (fixedName && rowName === fixedName)
      );
    });

    return normalizeMember(
      {
        ...fixedMember,
        id: matchedDbUser?.id || `fallback-${index}`,
        profile_picture:
          matchedDbUser?.profile_picture ||
          fixedMember.profile_picture ||
          getStaticMemberPhoto(fixedMember),
      },
      index,
    );
  });
}

function makeAvatar(name, role) {
  const initials = String(name || "PMTS User")
    .split(" ")
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  const label = roleLabel(role).slice(0, 18);
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">
      <defs>
        <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
          <stop offset="0" stop-color="#1d4ed8"/>
          <stop offset="1" stop-color="#0f766e"/>
        </linearGradient>
      </defs>
      <rect width="160" height="160" rx="80" fill="#eff6ff"/>
      <circle cx="80" cy="62" r="34" fill="url(#g)" opacity="0.92"/>
      <path d="M30 148c9-31 28-48 50-48s41 17 50 48" fill="url(#g)" opacity="0.92"/>
      <text x="80" y="89" text-anchor="middle" font-family="Arial, sans-serif" font-size="34" font-weight="700" fill="#ffffff">${initials}</text>
      <text x="80" y="136" text-anchor="middle" font-family="Arial, sans-serif" font-size="11" font-weight="700" fill="#ffffff">${label}</text>
    </svg>`;

  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

function resizeImageFileToDataUrl(file, maxSize = 720, quality = 0.86) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error("Could not read selected image."));
    reader.onload = () => {
      const img = new Image();
      img.onerror = () => reject(new Error("Could not load selected image."));
      img.onload = () => {
        const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
        const width = Math.max(1, Math.round(img.width * scale));
        const height = Math.max(1, Math.round(img.height * scale));
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);
        resolve(canvas.toDataURL("image/jpeg", quality));
      };
      img.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

export default function AboutSystem() {
  const currentUser = getUser();
  const isITAdmin = currentUser?.role === "it_admin";
  const photoInputRef = useRef(null);
  const galleryInputRef = useRef(null);
  const [teamMembers, setTeamMembers] = useState(
    mergeDatabaseUsersIntoFixedList([]),
  );
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [photoTarget, setPhotoTarget] = useState(null);
  const [galleryImages, setGalleryImages] = useState([]);
  const [galleryTitle, setGalleryTitle] = useState("");
  const [galleryDescription, setGalleryDescription] = useState("");
  const [loadingGallery, setLoadingGallery] = useState(false);
  const [uploadingGallery, setUploadingGallery] = useState(false);

  const displayMembers = useMemo(
    () => teamMembers.map((member, index) => normalizeMember(member, index)),
    [teamMembers],
  );

  const loadAboutUsers = async () => {
    setLoadingUsers(true);
    setError("");
    try {
      const response = await getAboutUsers();
      const rows = response.data?.users || response.data?.data || [];
      setTeamMembers(mergeDatabaseUsersIntoFixedList(rows));
    } catch (err) {
      setTeamMembers(mergeDatabaseUsersIntoFixedList([]));
      setError(
        err.response?.data?.message ||
          "Could not load database users. Showing fixed About page users.",
      );
    } finally {
      setLoadingUsers(false);
    }
  };

  const loadGalleryImages = async () => {
    setLoadingGallery(true);
    try {
      const response = await getAboutGalleryImages();
      const rows = response.data?.images || response.data?.data || [];
      setGalleryImages(Array.isArray(rows) ? rows : []);
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Could not load About page image gallery.",
      );
    } finally {
      setLoadingGallery(false);
    }
  };

  useEffect(() => {
    loadAboutUsers();
    loadGalleryImages();
  }, []);

  const openPhotoPicker = (member) => {
    if (!isITAdmin) return;
    setPhotoTarget(member);
    setMessage("");
    setError("");
    photoInputRef.current?.click();
  };

  const handlePhotoUpload = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";

    if (!file || !photoTarget) return;

    if (!String(photoTarget.id).match(/^\d+$/)) {
      setError(
        "This About page user is not connected to a database account. Import/update the sample users first, then upload the photo.",
      );
      return;
    }

    if (!file.type.startsWith("image/")) {
      setError("Please choose a valid image file.");
      return;
    }

    if (file.size > 8 * 1024 * 1024) {
      setError(
        "User photo must be smaller than 8MB. The system will compress normal JPG/PNG photos automatically.",
      );
      return;
    }

    try {
      const imageData = await resizeImageFileToDataUrl(file);
      await updateAboutUserPhoto({
        user_id: photoTarget.id,
        profile_picture: imageData,
      });

      setTeamMembers((prev) =>
        prev.map((member) =>
          String(member.id) === String(photoTarget.id)
            ? {
                ...member,
                profile_picture: imageData,
                profilePicture: imageData,
              }
            : member,
        ),
      );

      if (
        String(currentUser?.id || currentUser?.sub || "") ===
        String(photoTarget.id)
      ) {
        const updatedUser = {
          ...currentUser,
          profile_picture: imageData,
          profilePicture: imageData,
        };
        setAuth(getToken(), updatedUser);
        if (currentUser?.email) {
          localStorage.setItem(
            `pmts_profile_picture_${currentUser.email}`,
            imageData,
          );
        }
      }

      setMessage("About page user photo updated successfully.");
      setError("");
    } catch (err) {
      setError(
        err.response?.data?.message ||
          err.message ||
          "Failed to save user photo. Only IT Admin can add photos for all users.",
      );
    } finally {
      setPhotoTarget(null);
    }
  };

  const openGalleryPicker = () => {
    if (!isITAdmin) return;
    setMessage("");
    setError("");
    galleryInputRef.current?.click();
  };

  const handleGalleryUpload = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";

    if (!file) return;

    if (!isITAdmin) {
      setError("Only IT Admin can add images to the About page gallery.");
      return;
    }

    if (!file.type.startsWith("image/")) {
      setError("Please choose a valid image file.");
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      setError(
        "Gallery image must be smaller than 10MB. Please choose a smaller JPG/PNG image.",
      );
      return;
    }

    setUploadingGallery(true);
    try {
      const imageData = await resizeImageFileToDataUrl(file, 1200, 0.84);
      const response = await addAboutGalleryImage({
        title: galleryTitle.trim(),
        description: galleryDescription.trim(),
        image_data: imageData,
      });

      const savedImage = response.data?.image;
      if (savedImage) {
        setGalleryImages((prev) => [savedImage, ...prev]);
      } else {
        await loadGalleryImages();
      }

      setGalleryTitle("");
      setGalleryDescription("");
      setMessage("Gallery image added successfully.");
      setError("");
    } catch (err) {
      setError(
        err.response?.data?.message ||
          err.message ||
          "Failed to add gallery image. Only IT Admin can add images.",
      );
    } finally {
      setUploadingGallery(false);
    }
  };

  const handleDeleteGalleryImage = async (imageId) => {
    if (!isITAdmin || !imageId) return;

    const confirmed = window.confirm(
      "Remove this image from the About page gallery?",
    );
    if (!confirmed) return;

    try {
      await deleteAboutGalleryImage({ image_id: imageId });
      setGalleryImages((prev) =>
        prev.filter((image) => String(image.id) !== String(imageId)),
      );
      setMessage("Gallery image removed successfully.");
      setError("");
    } catch (err) {
      setError(
        err.response?.data?.message || "Failed to remove gallery image.",
      );
    }
  };

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <input
          ref={photoInputRef}
          type="file"
          accept="image/*"
          className="visually-hidden-file-input"
          onChange={handlePhotoUpload}
        />
        <input
          ref={galleryInputRef}
          type="file"
          accept="image/*"
          className="visually-hidden-file-input"
          onChange={handleGalleryUpload}
        />

        <div className="about-hero card solid-card">
          <div className="about-hero-text">
            <div className="about-system-icon">
              <Icon name="hospital" size={38} />
            </div>
            <div className="about-label">Badulla Hospital</div>
            <h1>Procurement Management Tracking System</h1>
            <p>
              A secure web-based system for monitoring hospital procurement
              files, NCB task schedules, committee work, delay alerts, and
              role-based workflow progress.
            </p>
            <div className="about-version-pill">Version {VERSION}</div>
          </div>
          <img
            className="about-hero-image"
            src={hospitalImage}
            alt="Badulla Hospital"
          />
        </div>

        {message && <div className="alert alert-success">{message}</div>}
        {error && <div className="alert alert-warning">{error}</div>}

        <div className="about-grid two-col">
          <section className="card solid-card about-info-card">
            <div className="section-title">System Ownership</div>
            <div className="about-person-highlight">
              <img
                className="about-mini-avatar image"
                src={sajeewaPhoto}
                alt="Sajeewa Bandara"
              />
              <div>
                <div className="about-small-label">Instructed & Guided By</div>
                <h3>Sajeewa Bandara</h3>
                <p>Accountant, Badulla Hospital</p>
              </div>
            </div>
            <div className="about-person-highlight">
              <div className="about-mini-avatar">SJ</div>
              <div>
                <div className="about-small-label">Instructed & Guided By</div>
                <h3>Sanjeewa Jayasekara</h3>
                <p>IT Specialist, Badulla Hospital</p>
              </div>
            </div>
            <div className="about-person-highlight blue">
              <div className="about-mini-avatar">DT</div>
              <div>
                <div className="about-small-label">
                  System Designed & Developed By
                </div>
                <h3>Development Team</h3>
                <p>PMTS Development Team</p>
              </div>
            </div>
          </section>

          <section className="card solid-card about-info-card">
            <div className="section-title">Core Features</div>
            <div className="about-feature-list">
              {SYSTEM_FEATURES.map((feature) => (
                <span key={feature} className="about-chip">
                  <Icon name="success" size={14} /> {feature}
                </span>
              ))}
            </div>
          </section>
        </div>

        <section className="card solid-card about-section-card">
          <div className="section-header">
            <div>
              <div className="section-title"><h3>System Users</h3></div>
            </div>
          </div>
          <div className="about-team-grid">
            {displayMembers.map((member) => (
              <article
                key={`${member.id}-${member.email}`}
                className="about-user-card"
              >
                <button
                  className={`about-user-photo-btn ${isITAdmin ? "is-editable" : ""}`}
                  type="button"
                  onClick={() => openPhotoPicker(member)}
                  disabled={!isITAdmin}
                  title={
                    isITAdmin
                      ? "Click to add/change this user photo"
                      : member.full_name
                  }
                >
                  <img
                    className="about-user-photo"
                    src={
                      member.profile_picture ||
                      makeAvatar(member.full_name, member.role)
                    }
                    alt={`${member.full_name} profile`}
                  />
                  {isITAdmin && (
                    <span className="about-photo-overlay">Add Photo</span>
                  )}
                </button>
                <div className="about-user-info">
                  <h3>{member.full_name}</h3>
                  <div className="about-user-role">
                    {roleLabel(member.role)}
                  </div>
                  <div className="about-user-detail">
                    <strong>Email:</strong> {member.email}
                  </div>
                  <div className="about-user-detail">
                    <strong>Phone:</strong> {member.phone || "—"}
                  </div>
                  {member.university ? (
                    <div className="about-user-detail">
                      <strong>University:</strong> {member.university}
                    </div>
                  ) : (
                    <div className="about-user-detail">
                      <strong>Department:</strong>{" "}
                      {member.department || member.organization || "—"}
                    </div>
                  )}
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="card solid-card about-section-card">
          <div className="section-header about-gallery-header">
            <div>
              <div className="section-title">Image Gallery</div>
              <p className="text-muted">
                {loadingGallery
                  ? "Loading About page gallery..."
                  : "Gallery images related to PMTS, Badulla Hospital, and project work."}
                {isITAdmin ? " IT Admin can add or remove gallery images." : ""}
              </p>
            </div>
          </div>

          {isITAdmin && (
            <div className="about-gallery-admin-panel">
              <div className="form-group">
                <label>Image Title</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Example: PMTS training session"
                  value={galleryTitle}
                  onChange={(event) => setGalleryTitle(event.target.value)}
                  maxLength={150}
                />
              </div>
              <div className="form-group">
                <label>Description</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Optional image description"
                  value={galleryDescription}
                  onChange={(event) =>
                    setGalleryDescription(event.target.value)
                  }
                  maxLength={1000}
                />
              </div>
              <button
                type="button"
                className="btn btn-primary about-gallery-add-btn"
                onClick={openGalleryPicker}
                disabled={uploadingGallery}
              >
                {uploadingGallery ? "Adding Image..." : "Add Gallery Image"}
              </button>
            </div>
          )}

          {galleryImages.length === 0 ? (
            <div className="about-gallery-empty">
              No gallery images added yet.{" "}
              {isITAdmin
                ? "Use the Add Gallery Image button to upload photos."
                : ""}
            </div>
          ) : (
            <div className="about-gallery-grid">
              {galleryImages.map((image) => (
                <article key={image.id} className="about-gallery-card">
                  <img
                    className="about-gallery-image"
                    src={image.image_data}
                    alt={image.title || "About gallery image"}
                  />
                  <div className="about-gallery-body">
                    <h3>{image.title || "PMTS Gallery Image"}</h3>
                    {image.description && <p>{image.description}</p>}
                    <div className="about-gallery-meta">
                      Added by {image.uploaded_by_name || "IT Admin"}
                    </div>
                    {isITAdmin && (
                      <button
                        type="button"
                        className="btn btn-outline-danger btn-sm about-gallery-delete"
                        onClick={() => handleDeleteGalleryImage(image.id)}
                      >
                        Remove
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="card solid-card about-section-card">
          <div className="section-title">
            Used Languages, Technologies & Frameworks
          </div>
          <div className="about-tech-grid">
            {TECHNOLOGIES.map((tech) => (
              <span key={tech} className="about-tech-badge">
                {tech}
              </span>
            ))}
          </div>
        </section>

        <footer className="about-footer card solid-card">
          <strong>© 2026 Teaching Hospital, Badulla.</strong>
          <span>
            Built for internal hospital procurement administration. All rights
            reserved.
          </span>
        </footer>
      </div>
    </Layout>
  );
}
