package com.dj.user.util;

import android.annotation.SuppressLint;
import android.content.Context;
import android.os.Build;
import android.provider.Settings;

import org.json.JSONObject;

import java.util.Locale;
import java.util.TimeZone;

public class DeviceInfoUtil {

    private static String cachedHeader;

    public static String getDeviceMetaHeader(Context context) {
        if (cachedHeader != null) return cachedHeader;
        try {
            JSONObject json = new JSONObject();
            // --- Web compatible ---
            json.put("ua", buildUserAgent(context));
            json.put("platform", "Android");
            json.put("lang", Locale.getDefault().toLanguageTag());
            json.put("tz", TimeZone.getDefault().getID());
            // --- Mobile extras ---
            json.put("device_id", getDeviceId(context));
            json.put("os", "Android");
            json.put("os_version", Build.VERSION.RELEASE);
            json.put("brand", Build.BRAND);
            json.put("model", Build.MODEL);
            json.put("app_version", getAppVersion(context));
            // Header-safe Base64
            cachedHeader = json.toString();
            return cachedHeader;
        } catch (Exception e) {
            return "";
        }
    }

    private static String buildUserAgent(Context context) {
        return "Android_" + Build.VERSION.RELEASE +
                " (" + Build.MANUFACTURER + " " + Build.MODEL + ")" +
                " App_" + getAppVersion(context);
    }

    private static String getAppVersion(Context context) {
        return String.format("v%s_%s", VersionUtil.getVersionName(context), VersionUtil.getVersionCode(context));
    }

    @SuppressLint("HardwareIds")
    private static String getDeviceId(Context context) {
        return Settings.Secure.getString(
                context.getContentResolver(),
                Settings.Secure.ANDROID_ID
        );
    }
}
