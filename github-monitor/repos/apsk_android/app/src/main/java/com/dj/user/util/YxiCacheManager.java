package com.dj.user.util;

import com.dj.user.model.response.Yxi;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public class YxiCacheManager {
    // Limit cache to 3 providers
    private static final int MAX_CACHE_SIZE = 3;

    // accessOrder = true makes LinkedHashMap maintain insertion order based on access (recently used)
    private static final Map<String, List<Yxi>> yxiCache = new LinkedHashMap<>(MAX_CACHE_SIZE, 0.75f, true) {
        @Override
        protected boolean removeEldestEntry(Map.Entry<String, List<Yxi>> eldest) {
            return size() > MAX_CACHE_SIZE;
        }
    };

    public static List<Yxi> getCachedYxiList(String providerId) {
        return yxiCache.get(providerId);
    }

    public static void putCachedYxiList(String providerId, List<Yxi> yxiList) {
        if (providerId == null || yxiList == null) return;
        yxiCache.put(providerId, yxiList);
    }

    public static void clearCache() {
        yxiCache.clear();
    }
}
