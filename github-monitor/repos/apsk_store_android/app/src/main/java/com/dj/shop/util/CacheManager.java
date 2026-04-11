package com.dj.shop.util;

import android.content.Context;
import android.content.SharedPreferences;

import androidx.annotation.NonNull;

import com.google.gson.Gson;

import java.lang.reflect.Type;

public class CacheManager {

    private static final String PREFS_NAME = "app_cache";
    private static final Gson gson = new Gson();

    // Static keys for common cache items
    public static final String KEY_ACCESS_TOKEN = "access_token";
    public static final String KEY_REFRESH_TOKEN = "refresh_token";
    public static final String KEY_SHOP_PROFILE = "shop_profile";
    public static final String KEY_LANGUAGE = "language";

    //
    public static final String KEY_COUNTRY_LIST = "country_list";

    // Save string value
    public static void saveString(@NonNull Context context, String key, String value) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().putString(key, value).apply();
    }

    public static String getString(@NonNull Context context, String key) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        return prefs.getString(key, null);
    }

    public static <T> void saveObject(@NonNull Context context, String key, T object) {
        String json = gson.toJson(object);
        saveString(context, key, json);
    }

    public static <T> T getObject(@NonNull Context context, String key, Class<T> classOfT) {
        String json = getString(context, key);
        if (json == null) return null;
        return gson.fromJson(json, classOfT);
    }

    public static <T> T getObject(@NonNull Context context, String key, Type typeOfT) {
        String json = getString(context, key);
        if (json == null) return null;
        return gson.fromJson(json, typeOfT);
    }

    public static void remove(@NonNull Context context, String key) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().remove(key).apply();
    }

    public static void clearAll(@NonNull Context context) {
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        String language = prefs.getString(KEY_LANGUAGE, null);
        String countryList = prefs.getString(KEY_COUNTRY_LIST, null);
        prefs.edit().clear().apply();

        SharedPreferences.Editor editor = prefs.edit();
        if (language != null) {
            editor.putString(KEY_LANGUAGE, language);
        }
        if (countryList != null) {
            editor.putString(KEY_COUNTRY_LIST, countryList);
        }
        editor.apply();
    }
}
