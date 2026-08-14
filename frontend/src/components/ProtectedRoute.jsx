import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { validateSession } from '../api/api';
import { clearAuth, getToken, setAuth, isTokenExpired } from '../utils/auth';
import { getDefaultRoute } from '../utils/roleRoutes';

/**
 * ProtectedRoute
 * Validates the JWT with backend /auth/me.php.
 * Browser localStorage user data is never trusted for dashboard access.
 * @param {string[]} allowedRoles – if empty, any authenticated user is allowed
 */
export default function ProtectedRoute({ children, allowedRoles = [] }) {
  const [checking, setChecking] = useState(true);
  const [serverUser, setServerUser] = useState(null);

  useEffect(() => {
    let mounted = true;

    const checkSession = async () => {
      const token = getToken();

      if (!token || isTokenExpired(token)) {
        clearAuth();
        if (mounted) {
          setServerUser(null);
          setChecking(false);
        }
        return;
      }

      try {
        const res = await validateSession();
        const user = res.data?.user;

        if (res.data?.success && user) {
          setAuth(token, user);
          if (mounted) setServerUser(user);
        } else {
          clearAuth();
          if (mounted) setServerUser(null);
        }
      } catch (error) {
        clearAuth();
        if (mounted) setServerUser(null);
      } finally {
        if (mounted) setChecking(false);
      }
    };

    checkSession();

    return () => {
      mounted = false;
    };
  }, []);

  if (checking) {
    return (
      <div className="loading-page">
        <div className="spinner" />
      </div>
    );
  }

  if (!serverUser) {
    return <Navigate to="/login" replace />;
  }

  const role = serverUser.role;

  // Pending users – redirect to pending screen
  if (role === 'pending') {
    return <Navigate to="/pending" replace />;
  }

  // Role restriction uses server-confirmed role, not browser-edited user data
  if (allowedRoles.length > 0 && !allowedRoles.includes(role)) {
    const defaultRoute = getDefaultRoute(role);
    return <Navigate to={defaultRoute} replace />;
  }

  return children;
}
