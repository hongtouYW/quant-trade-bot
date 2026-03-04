import axios from 'axios';

const STORAGE_KEYS = ['access_token', 'refresh_token', 'user_id', 'username'];

function getCurrentRole() {
  const path = window.location.pathname;
  return path.startsWith('/admin') ? 'admin' : 'agent';
}

function clearRoleStorage(role) {
  STORAGE_KEYS.forEach(k => localStorage.removeItem(`${role}_${k}`));
}

const api = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
});

// Attach JWT token based on current URL path
api.interceptors.request.use((config) => {
  const role = getCurrentRole();
  const token = localStorage.getItem(`${role}_access_token`);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 → try refresh, then redirect to role-specific login
api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const original = error.config;
    const status = error.response?.status;
    // 401 = expired/missing token, 422 = invalid token (e.g. wrong signing key)
    if (
      (status === 401 || status === 422) &&
      !original._retry &&
      !original.url?.includes('/auth/')
    ) {
      original._retry = true;
      const role = getCurrentRole();
      const refresh = localStorage.getItem(`${role}_refresh_token`);
      if (refresh) {
        try {
          const res = await axios.post('/api/auth/refresh', null, {
            headers: { Authorization: `Bearer ${refresh}` },
          });
          const newToken = res.data.access_token;
          localStorage.setItem(`${role}_access_token`, newToken);
          original.headers.Authorization = `Bearer ${newToken}`;
          return api(original);
        } catch {
          clearRoleStorage(role);
          window.location.href = `/${role}/login`;
        }
      } else {
        clearRoleStorage(role);
        window.location.href = `/${role}/login`;
      }
    }
    return Promise.reject(error);
  }
);

export default api;
