import { Link } from 'react-router-dom';
import { getUser } from '../utils/auth';
import Icon from './Icon';

export default function Topbar() {
  const user = getUser();
  const profilePicture = user?.profile_picture || user?.profilePicture || (user?.email ? localStorage.getItem(`pmts_profile_picture_${user.email}`) : "");

  return (
    <header className="topbar">
      <div className="topbar-left">
        <div className="topbar-search">
          <span className="topbar-search-icon"><Icon name="search" size={17} /></span>
          <input
            type="text"
            placeholder="Search..."
            className="topbar-search-input"
          />
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

        <Link to="/help" className="topbar-icon-btn" title="Help">
          <Icon name="help" size={19} />
        </Link>

        <div className="topbar-divider" />

        <Link to="/profile" className="topbar-user">
          <div className="topbar-avatar">
            {profilePicture ? (
              <img src={profilePicture} alt="Profile" className="topbar-avatar-img" />
            ) : (
              user?.full_name
                ? user.full_name.charAt(0).toUpperCase()
                : user?.name
                  ? user.name.charAt(0).toUpperCase()
                  : 'U'
            )}
          </div>

          <div className="topbar-user-info">
            <span className="topbar-user-name">
              {user?.full_name || user?.name || 'User'}
            </span>

            <span className="topbar-user-role">
              {user?.role?.replace(/_/g, ' ') || ''}
            </span>
          </div>
        </Link>
      </div>
    </header>
  );
}
