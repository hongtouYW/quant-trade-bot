import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useLocation } from 'react-router-dom';
import api from '../api/client';

const AuthContext = createContext(null);

const STORAGE_KEYS = ['access_token', 'refresh_token', 'user_id', 'username'];

function loadUserFromStorage(role) {
  const token = localStorage.getItem(`${role}_access_token`);
  const userId = localStorage.getItem(`${role}_user_id`);
  const username = localStorage.getItem(`${role}_username`);
  if (token && userId) return { id: Number(userId), username, role };
  return null;
}

export function AuthProvider({ children }) {
  const [users, setUsers] = useState({ admin: null, agent: null });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setUsers({
      admin: loadUserFromStorage('admin'),
      agent: loadUserFromStorage('agent'),
    });
    setLoading(false);
  }, []);

  const login = useCallback(async (username, password, role) => {
    const endpoint = role === 'admin' ? '/auth/admin/login' : '/auth/agent/login';
    const res = await api.post(endpoint, { username, password });
    const { access_token, refresh_token, user: u } = res.data;
    localStorage.setItem(`${role}_access_token`, access_token);
    localStorage.setItem(`${role}_refresh_token`, refresh_token);
    localStorage.setItem(`${role}_user_id`, u.id);
    localStorage.setItem(`${role}_username`, u.username);
    const userData = { id: u.id, username: u.username, role };
    setUsers(prev => ({ ...prev, [role]: userData }));
    return userData;
  }, []);

  const logout = useCallback((role) => {
    STORAGE_KEYS.forEach(k => localStorage.removeItem(`${role}_${k}`));
    setUsers(prev => ({ ...prev, [role]: null }));
  }, []);

  return (
    <AuthContext.Provider value={{ users, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be inside AuthProvider');
  const location = useLocation();
  const role = location.pathname.startsWith('/admin') ? 'admin'
    : location.pathname.startsWith('/agent') ? 'agent'
    : null;
  const user = role ? ctx.users[role] : null;
  return {
    user,
    loading: ctx.loading,
    login: ctx.login,
    logout: () => { if (role) ctx.logout(role); },
  };
}
