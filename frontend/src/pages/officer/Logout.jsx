import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { clearAuth } from '../../utils/auth';

export default function Logout() {
  const navigate = useNavigate();
  useEffect(() => {
    clearAuth();
    navigate('/login');
  }, [navigate]);
  return null;
}
