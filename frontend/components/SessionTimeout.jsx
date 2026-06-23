import { useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { clearAuth, isAuthenticated } from '../utils/auth';

const SESSION_TIMEOUT_MS = 2 * 60 * 1000; // 2 minutes

const PUBLIC_ROUTES = [
  '/login',
  '/register',
  '/forgot-password',
  '/enter-email',
  '/reset-password',
  '/pending',
];

const ACTIVITY_EVENTS = [
  'click',
  'mousemove',
  'mousedown',
  'keydown',
  'scroll',
  'touchstart',
  'wheel',
];

export default function SessionTimeout() {
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    if (PUBLIC_ROUTES.includes(location.pathname) || !isAuthenticated()) {
      return undefined;
    }

    let timeoutId;

    const logoutForInactivity = () => {
      if (!isAuthenticated()) {
        return;
      }

      clearAuth();
      sessionStorage.setItem(
        'sessionTimeoutMessage',
        'Your session expired because the system was inactive for 2 minutes.'
      );
      navigate('/login', { replace: true });
    };

    const resetTimer = () => {
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(logoutForInactivity, SESSION_TIMEOUT_MS);
    };

    resetTimer();

    ACTIVITY_EVENTS.forEach((eventName) => {
      window.addEventListener(eventName, resetTimer, { passive: true });
    });

    return () => {
      window.clearTimeout(timeoutId);
      ACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, resetTimer);
      });
    };
  }, [location.pathname, navigate]);

  return null;
}
