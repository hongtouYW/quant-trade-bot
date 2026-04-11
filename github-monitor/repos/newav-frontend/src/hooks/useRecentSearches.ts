import { useState, useEffect, useCallback } from "react";

const STORAGE_KEY = "recent_searches";
const MAX_RECENT_SEARCHES = 10;
const MAX_KEYWORD_LENGTH = 50;

export const useRecentSearches = () => {
  const [recentSearches, setRecentSearches] = useState<string[]>([]);

  // Load recent searches from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed)) {
          setRecentSearches(parsed.slice(0, MAX_RECENT_SEARCHES));
        }
      }
    } catch (error) {
      console.error("Failed to load recent searches:", error);
      setRecentSearches([]);
    }
  }, []);

  // Add a new search keyword - memoized to prevent infinite loops
  const addSearch = useCallback((keyword: string) => {
    const trimmed = keyword.trim().slice(0, MAX_KEYWORD_LENGTH);
    if (!trimmed) return;

    setRecentSearches((prev) => {
      // Remove duplicate if exists (case-insensitive)
      const filtered = prev.filter(
        (item) => item.toLowerCase() !== trimmed.toLowerCase()
      );

      // Add to beginning and limit to MAX_RECENT_SEARCHES
      const updated = [trimmed, ...filtered].slice(0, MAX_RECENT_SEARCHES);

      // Persist to localStorage
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
      } catch (error) {
        console.error("Failed to save recent searches:", error);
      }

      return updated;
    });
  }, []);

  // Remove a specific search keyword - memoized
  const removeSearch = useCallback((keyword: string) => {
    setRecentSearches((prev) => {
      const updated = prev.filter((item) => item !== keyword);

      // Persist to localStorage
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
      } catch (error) {
        console.error("Failed to update recent searches:", error);
      }

      return updated;
    });
  }, []);

  // Clear all recent searches - memoized
  const clearAll = useCallback(() => {
    setRecentSearches([]);
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      console.error("Failed to clear recent searches:", error);
    }
  }, []);

  return {
    recentSearches,
    addSearch,
    removeSearch,
    clearAll,
  };
};
