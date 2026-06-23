import { Navigate } from 'react-router-dom';
import { isAuthenticated, getUserRole } from '../utils/auth';
import { getDefaultRoute } from '../utils/roleRoutes';

/**
 * ProtectedRoute
 * @param {string[]} allowedRoles – if empty, any authenticated user is allowed
 */
export default function ProtectedRoute({ children, allowedRoles = [] }) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }

  const role = getUserRole();

  // Pending users – redirect to pending screen
  if (role === 'pending') {
    return <Navigate to="/pending" replace />;
  }

  // Role restriction
  if (allowedRoles.length > 0 && !allowedRoles.includes(role)) {
    const defaultRoute = getDefaultRoute(role);
    return <Navigate to={defaultRoute} replace />;
  }

  return children;
}
