package com.dj.user.util;

import android.content.Context;
import android.content.SharedPreferences;

import com.google.gson.Gson;

import java.lang.reflect.Type;

public class CacheManager {

    private static final String PREFS_NAME = "app_cache";
    private static final Gson gson = new Gson();

    // Static keys for common cache items
    public static final String KEY_ACCESS_TOKEN = "access_token";
    public static final String KEY_REFRESH_TOKEN = "refresh_token";
    public static final String KEY_USER_PROFILE = "user_profile";
    public static final String KEY_AGENT_CODE = "agent_code";
    public static final String KEY_SHOP_CODE = "shop_code";
    public static final String KEY_INVITATION_CODE = "invitation_code";
    public static final String KEY_LANGUAGE = "language";
    public static final String KEY_BIOMETRIC_ENABLED = "biometric_enabled";
    public static final String KEY_NEED_BINDING = "need_binding";

    //
    public static final String KEY_COUNTRY_LIST = "country_list";

    // Save string value
    public static void saveString(Context context, String key, String value) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().putString(key, value).apply();
    }

    public static String getString(Context context, String key) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        return prefs.getString(key, null);
    }

    public static void saveBoolean(Context context, String key, boolean value) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().putBoolean(key, value).apply();
    }

    public static boolean getBoolean(Context context, String key) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        return prefs.getBoolean(key, false); // default = false
    }

    public static <T> void saveObject(Context context, String key, T object) {
        String json = gson.toJson(object);
        saveString(context, key, json);
    }

    public static <T> T getObject(Context context, String key, Class<T> classOfT) {
        String json = getString(context, key);
        if (json == null) return null;
        return gson.fromJson(json, classOfT);
    }

    public static <T> T getObject(Context context, String key, Type typeOfT) {
        String json = getString(context, key);
        if (json == null) return null;
        return gson.fromJson(json, typeOfT);
    }

    public static void remove(Context context, String key) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().remove(key).apply();
    }

    public static void clearAll(Context context) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        // Preserve important values
        String language = prefs.getString(KEY_LANGUAGE, null);
        String agentCode = prefs.getString(KEY_AGENT_CODE, null);
        String shopCode = prefs.getString(KEY_SHOP_CODE, null);
        String countryList = prefs.getString(KEY_COUNTRY_LIST, null);
        prefs.edit().clear().apply();
        // Restore preserved values
        SharedPreferences.Editor editor = prefs.edit();
        if (language != null) {
            editor.putString(KEY_LANGUAGE, language);
        }
        if (agentCode != null) {
            editor.putString(KEY_AGENT_CODE, agentCode);
        }
        if (shopCode != null) {
            editor.putString(KEY_SHOP_CODE, shopCode);
        }
        if (countryList != null) {
            editor.putString(KEY_COUNTRY_LIST, countryList);
        }
        editor.apply();
    }
}
