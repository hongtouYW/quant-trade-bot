package com.dj.shop.util;

import android.content.Context;
import android.content.pm.PackageManager;

public final class VersionUtil {

    public static String getVersionCode(Context context) {
        try {
            return String.valueOf(context.getPackageManager().getPackageInfo(context.getPackageName(), 0).versionCode);
        } catch (PackageManager.NameNotFoundException e) {
            return "0";
        }
    }

    public static String getVersionName(Context context) {
        try {
            return context.getPackageManager().getPackageInfo(context.getPackageName(), 0).versionName;
        } catch (PackageManager.NameNotFoundException e) {
            return "";
        }
    }

    public static boolean isVersionLower(Context context, String target) {
        String currentVersion = getVersionName(context);
        String[] currentParts = currentVersion.split("\\.");
        String[] targetParts = target.split("\\.");

        int maxLength = Math.max(currentParts.length, targetParts.length);
        for (int i = 0; i < maxLength; i++) {
            int currentVal = i < currentParts.length ? Integer.parseInt(currentParts[i]) : 0;
            int targetVal = i < targetParts.length ? Integer.parseInt(targetParts[i]) : 0;
            if (currentVal < targetVal) return true;
            if (currentVal > targetVal) return false;
        }
        return false; // equal
    }
}