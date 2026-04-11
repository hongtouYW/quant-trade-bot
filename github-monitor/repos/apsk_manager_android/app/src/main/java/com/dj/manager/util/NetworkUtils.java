package com.dj.manager.util;

import android.os.Handler;
import android.os.Looper;
import android.util.Log;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.InetAddress;
import java.net.NetworkInterface;
import java.net.URL;
import java.util.Collections;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class NetworkUtils {

    public interface PublicIpCallback {
        void onResult(String publicIp);
    }

    private static final ExecutorService executor = Executors.newSingleThreadExecutor();
    private static final Handler mainHandler = new Handler(Looper.getMainLooper());
    private static String cachedPublicIp = null;

    public static void fetchPublicIPv4(final PublicIpCallback callback) {
        // Retry if cached is invalid / local
        if (!isInvalidPublicIp(cachedPublicIp)) {
            mainHandler.post(() -> callback.onResult(cachedPublicIp));
            return;
        }
        executor.execute(() -> {
            String ip = null;
            try {
                URL url = new URL("https://api.ipify.org?format=text");
                HttpURLConnection connection =
                        (HttpURLConnection) url.openConnection();
                connection.setRequestMethod("GET");
                connection.setConnectTimeout(5000);
                connection.setReadTimeout(5000);

                BufferedReader reader = new BufferedReader(new InputStreamReader(connection.getInputStream()));
                ip = reader.readLine();
                reader.close();
            } catch (Exception e) {
                Log.e("###", "Public IP fetch failed", e);
            }
            // Fallback #1: Local IPv4
            if (isInvalidPublicIp(ip)) {
                ip = getDeviceIpAddress(true);
            }
            // Fallback #2: Hard default
            if (StringUtil.isNullOrEmpty(ip)) {
                ip = "0.0.0.0";
            }
            cachedPublicIp = ip;
            final String result = ip;
            mainHandler.post(() -> callback.onResult(result));
        });
    }


    public static String getDeviceIpAddress(boolean useIPv4) {
        try {
            for (NetworkInterface nif : Collections.list(NetworkInterface.getNetworkInterfaces())) {
                if (!nif.isUp()) continue;
                for (InetAddress addr : Collections.list(nif.getInetAddresses())) {
                    if (addr.isLoopbackAddress() || addr.isLinkLocalAddress()) continue;
                    String hostAddress = addr.getHostAddress();
                    boolean isIPv4 = false;
                    if (hostAddress != null) {
                        isIPv4 = hostAddress.indexOf(':') < 0;
                    }
                    if (useIPv4 && isIPv4) {
                        return hostAddress;
                    } else if (!useIPv4 && !isIPv4) {
                        int index = 0;
                        if (hostAddress != null) {
                            index = hostAddress.indexOf('%');
                        }
                        return index < 0 ? hostAddress : hostAddress != null ? hostAddress.substring(0, index) : null;
                    }
                }
            }
        } catch (Exception e) {
            Log.e("###", "getDeviceIpAddress failed", e);
        }
        return null;
    }

    private static boolean isInvalidPublicIp(String ip) {
        return StringUtil.isNullOrEmpty(ip) || "0.0.0.0".equals(ip) || isPrivateIPv4(ip);
    }

    private static boolean isPrivateIPv4(String ip) {
        if (ip.startsWith("10.")) return true;
        if (ip.startsWith("192.168.")) return true;
        if (ip.startsWith("127.")) return true;
        if (ip.startsWith("172.")) {
            try {
                int secondOctet = Integer.parseInt(ip.split("\\.")[1]);
                return secondOctet >= 16 && secondOctet <= 31;
            } catch (Exception ignored) {
            }
        }
        return false;
    }
}
