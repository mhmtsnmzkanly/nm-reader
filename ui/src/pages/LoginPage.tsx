import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export const LoginPage: React.FC = () => {
  const { openAuthModal, isAuthenticated } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/');
    } else {
      openAuthModal('login');
      navigate('/', { replace: true });
    }
  }, [isAuthenticated, navigate, openAuthModal]);

  return null;
};
