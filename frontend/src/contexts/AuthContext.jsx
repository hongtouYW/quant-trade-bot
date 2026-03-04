import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    const role = localStorage.getItem('user_role');
    const userId = localStorage.getItem('user_id');
    const username = localStorage.getItem('username');
    if (token && role) {
      setUser({ id: Number(userId), username, role });
    }
    setLoading(false);
  }, []);

  const login = useCallback(async (username, password, role) => {
    const endpoint = role === 'admin' ? '/auth/admin/login' : '/auth/agent/login';
    const res = await api.post(endpoint, { username, password });
    const { access_token, refresh_token, user: u } = res.data;
    localStorage.setItem('access_token', access_token);
    localStorage.setItem('refresh_token', refresh_token);
    localStorage.setItem('user_role', role);
    localStorage.setItem('user_id', u.id);
    localStorage.setItem('username', u.username);
    const userData = { id: u.id, username: u.username, role };
    setUser(userData);
    return userData;
  }, []);

  const logout = useCallback(() => {
    localStorage.clear();
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be inside AuthProvider');
  return ctx;
}
