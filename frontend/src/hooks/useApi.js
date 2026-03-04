import { useState, useEffect, useCallback } from 'react';
import api from '../api/client';

/**
 * Generic hook for API GET requests with auto-refresh.
 */
export function useApi(url, { interval = 0, enabled = true } = {}) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetch = useCallback(async () => {
    if (!enabled || !url) return;
    try {
      const res = await api.get(url);
      setData(res.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.error || err.message);
    } finally {
      setLoading(false);
    }
  }, [url, enabled]);

  useEffect(() => {
    fetch();
    if (interval > 0 && enabled) {
      const id = setInterval(fetch, interval);
      return () => clearInterval(id);
    }
  }, [fetch, interval, enabled]);

  return { data, loading, error, refetch: fetch };
}
