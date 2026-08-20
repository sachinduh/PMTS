import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getToken, getUser, setAuth } from '../utils/auth';
import { getAllProcurements, updateProfile } from '../api/api';
import Icon from './Icon';

function getProcurementRoute(role, item) {
  const id = item?.id || item?.procurement_id;
  if (!id) return '#';

  switch (role) {
    case 'director':
      return `/director-procurement/${id}`;
    case 'accountant':
      return `/accountant-procurement/${id}`;
    case 'procurement_officer':
    case 'it_admin':
      return `/officer-ncb-schedule/${id}`;
    case 'bec_member':
      return `/bec-evaluation?procurement_id=${id}`;
    case 'specification_committee':
      return `/specification-review?procurement_id=${id}`;
    default:
      return '#';
  }
}

export default function Topbar() {
  const user = getUser();
  const navigate = useNavigate();
  const searchRef = useRef(null);
  const avatarInputRef = useRef(null);
  const getSavedProfilePicture = () => user?.profile_picture || user?.profilePicture || (user?.email ? localStorage.getItem(`pmts_profile_picture_${user.email}`) : '');
  const [profilePicture, setProfilePicture] = useState(getSavedProfilePicture);
  const [avatarError, setAvatarError] = useState('');

  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchLoading, setSearchLoading] = useState(false);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (searchRef.current && !searchRef.current.contains(event.target)) {
        setSearchOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    const query = searchTerm.trim();
    if (query.length < 2) {
      setSearchResults([]);
      setSearchLoading(false);
      return undefined;
    }

    let cancelled = false;
    setSearchLoading(true);

    const timeout = window.setTimeout(() => {
      getAllProcurements({ search: query, limit: 10 })
        .then((res) => {
          if (cancelled) return;
          const rows = res.data?.procurements || res.data?.data || [];
          setSearchResults(Array.isArray(rows) ? rows.slice(0, 8) : []);
        })
        .catch(() => {
          if (!cancelled) setSearchResults([]);
        })
        .finally(() => {
          if (!cancelled) setSearchLoading(false);
        });
    }, 250);

    return () => {
      cancelled = true;
      window.clearTimeout(timeout);
    };
  }, [searchTerm]);

  const openProcurement = (item) => {
    const route = getProcurementRoute(user?.role, item);
    if (route === '#') return;
    setSearchOpen(false);
    setSearchTerm('');
    navigate(route);
  };

  const saveProfilePicture = async (imageData) => {
    const updatedUser = {
      ...user,
      profile_picture: imageData,
      profilePicture: imageData,
    };

    setProfilePicture(imageData);
    if (user?.email) {
      localStorage.setItem(`pmts_profile_picture_${user.email}`, imageData);
    }
    setAuth(getToken(), updatedUser);

    try {
      await updateProfile({
        id: user?.id,
        full_name: user?.full_name || user?.name || 'PMTS User',
        email: user?.email || '',
        phone: user?.phone || '',
        department: user?.department || '',
        profile_picture: imageData,
      });
      setAvatarError('');
    } catch (err) {
      setAvatarError(err.response?.data?.message || 'Image saved locally. Open Profile Settings and save again.');
    }
  };

  const handleAvatarUpload = (event) => {
    const file = event.target.files?.[0];
    setAvatarError('');

    if (!file) return;

    if (!file.type.startsWith('image/')) {
      setAvatarError('Please choose a valid image file.');
      event.target.value = '';
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      setAvatarError('Profile picture must be smaller than 2MB.');
      event.target.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      const imageData = reader.result || '';
      if (imageData) {
        saveProfilePicture(imageData);
      }
    };
    reader.onerror = () => setAvatarError('Could not load selected image.');
    reader.readAsDataURL(file);
    event.target.value = '';
  };

  return (
    <header className="topbar">
      <div className="topbar-left">
        <div className="topbar-search" ref={searchRef}>
          <span className="topbar-search-icon"><Icon name="search" size={17} /></span>
          <input
            type="text"
            placeholder="Search procurement by ID or title..."
            className="topbar-search-input"
            value={searchTerm}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setSearchOpen(true);
            }}
            onFocus={() => setSearchOpen(true)}
          />

          {searchOpen && searchTerm.trim().length >= 2 && (
            <div className="topbar-search-results">
              {searchLoading ? (
                <div className="topbar-search-state">Searching procurements…</div>
              ) : searchResults.length > 0 ? (
                searchResults.map((item) => (
                  <button
                    key={item.id || item.procurement_id}
                    type="button"
                    className="topbar-search-result"
                    onClick={() => openProcurement(item)}
                  >
                    <span className="topbar-search-result-title">{item.title || item.file_name || 'Untitled procurement'}</span>
                    <span className="topbar-search-result-meta">
                      {item.procurement_id || 'No ID'}{item.tender_number ? ` · ${item.tender_number}` : ''}
                    </span>
                  </button>
                ))
              ) : (
                <div className="topbar-search-state">No procurement found for “{searchTerm.trim()}”.</div>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="topbar-right">
        <Link
          to="/notifications"
          className="topbar-icon-btn"
          title="Notifications"
        >
          <Icon name="notification" size={19} />
        </Link>

        <Link to="/about" className="topbar-icon-btn" title="About PMTS">
          <Icon name="document" size={19} />
        </Link>

        <Link to="/help" className="topbar-icon-btn" title="Help">
          <Icon name="help" size={19} />
        </Link>

        <div className="topbar-divider" />

        <div className="topbar-user">
          <button
            type="button"
            className="topbar-avatar topbar-avatar-button"
            title="Click to add or change profile image"
            onClick={() => avatarInputRef.current?.click()}
          >
            {profilePicture ? (
              <img src={profilePicture} alt="Profile" className="topbar-avatar-img" />
            ) : (
              user?.full_name
                ? user.full_name.charAt(0).toUpperCase()
                : user?.name
                  ? user.name.charAt(0).toUpperCase()
                  : 'U'
            )}
          </button>
          <input
            ref={avatarInputRef}
            type="file"
            accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
            onChange={handleAvatarUpload}
            style={{ display: 'none' }}
          />

          <Link to="/profile" className="topbar-user-info">
            <span className="topbar-user-name">
              {user?.full_name || user?.name || 'User'}
            </span>

            <span className="topbar-user-role">
              {avatarError || user?.role?.replace(/_/g, ' ') || ''}
            </span>
          </Link>
        </div>
      </div>
    </header>
  );
}
