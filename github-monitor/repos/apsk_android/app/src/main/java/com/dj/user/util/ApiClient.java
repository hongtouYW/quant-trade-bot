package com.dj.user.util;

import android.content.Context;
import android.content.Intent;
import android.util.Log;

import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.dj.user.R;
import com.dj.user.model.request.RequestRefresh;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Remain;
import com.dj.user.model.response.Transaction;
import com.dj.user.model.response.Yxi;
import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;

import org.json.JSONObject;

import java.io.IOException;
import java.util.List;
import java.util.concurrent.TimeUnit;

import okhttp3.Interceptor;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.ResponseBody;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

public class ApiClient {

    private static Retrofit retrofit;

    // Lock for controlling refresh
    private static final Object refreshLock = new Object();
    private static boolean isRefreshing = false;

    public static Retrofit getInstance(Context context) {
        if (retrofit == null) {
            Gson customGson = new GsonBuilder()
                    .registerTypeAdapter(
                            new TypeToken<List<Player>>() {
                            }.getType(),
                            new PlayerListDeserializer()
                    ).registerTypeAdapter(
                            new TypeToken<List<Yxi>>() {
                            }.getType(),
                            new YxiListDeserializer()
                    ).registerTypeAdapter(
                            new TypeToken<List<Transaction>>() {
                            }.getType(),
                            new CreditListDeserializer()
                    ).registerTypeAdapter(
                            new TypeToken<List<Transaction>>() {
                            }.getType(),
                            new PointListDeserializer()
                    )
                    .registerTypeAdapter(Remain.class, new RemainDeserializer())
                    .registerTypeAdapter(Integer.class, new StatusDeserializer())
                    .registerTypeAdapter(int.class, new StatusDeserializer())
                    .create();
            retrofit = new Retrofit.Builder()
                    .baseUrl(context.getString(R.string.api_base_url))
                    .addConverterFactory(GsonConverterFactory.create(customGson))
                    .client(provideHttpClient(context))
                    .build();
        }
        return retrofit;
    }

    private static OkHttpClient provideHttpClient(Context context) {
        HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
        logging.setLevel(HttpLoggingInterceptor.Level.BODY);

        return new OkHttpClient.Builder()
                .addInterceptor(provideMainInterceptor(context, logging))
                .addInterceptor(new EncryptionInterceptor(context))
                .addInterceptor(logging)
                .connectTimeout(60, TimeUnit.SECONDS) // default 10s → now 60s
                .readTimeout(60, TimeUnit.SECONDS)    // response reading timeout
                .writeTimeout(60, TimeUnit.SECONDS)
                .build();
    }

    private static Interceptor provideMainInterceptor(Context context, HttpLoggingInterceptor logging) {
        return chain -> {
            Request originalRequest = chain.request();
            String accessToken = CacheManager.getString(context, CacheManager.KEY_ACCESS_TOKEN);

            String language = CacheManager.getString(context, CacheManager.KEY_LANGUAGE);
            if (language == null || language.isEmpty()) {
                language = "zh";
            }
            Request requestWithToken = originalRequest.newBuilder()
                    .header("Accept-Language", language)
                    .header("Authorization", "Bearer " + accessToken)
                    .header("X-Device-Meta", DeviceInfoUtil.getDeviceMetaHeader(context))
                    .build();

            Response response = chain.proceed(requestWithToken);

            if (isUnauthorized(response)) {
                Log.d("### TOKEN", "Access token expired, attempting to refresh...");

                if (tryRefreshToken(context, logging)) {
                    String newAccessToken = CacheManager.getString(context, CacheManager.KEY_ACCESS_TOKEN);
                    Request retryRequest = originalRequest.newBuilder()
                            .header("Accept-Language", language)
                            .header("Authorization", "Bearer " + newAccessToken)
                            .header("X-Device-Meta", DeviceInfoUtil.getDeviceMetaHeader(context))
                            .build();

                    response.close();
                    return chain.proceed(retryRequest);
                } else {
                    handleForcedLogout(context);
                }
            }

            return response;
        };
    }

    private static boolean isUnauthorized(Response response) {
        try {
            ResponseBody peeked = response.peekBody(Long.MAX_VALUE);
            JSONObject json = new JSONObject(peeked.string());

            String error = json.optString("error", "").toLowerCase();
            return !json.optBoolean("status") && (
                    error.toLowerCase().contains("unauthenticated") ||
                            error.toLowerCase().contains("unauthorized") ||
                            (!error.toLowerCase().contains("otp") && error.toLowerCase().contains("expired"))
            );
        } catch (Exception e) {
            Log.e("### TOKEN_CHECK", "Unable to parse response", e);
        }
        return false;
    }

    private static boolean tryRefreshToken(Context context, HttpLoggingInterceptor logging) {
        synchronized (refreshLock) {
            if (isRefreshing) {
                try {
                    Log.d("### REFRESH", "Another thread is already refreshing, waiting...");
                    refreshLock.wait(); // wait until refresh is done
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    return false;
                }
                // after wait, check if new token exists
                String newToken = CacheManager.getString(context, CacheManager.KEY_ACCESS_TOKEN);
                return newToken != null && !newToken.isEmpty();
            }

            isRefreshing = true;
        }

        boolean success = false;
        int maxRetries = 3;
        int retryDelayMs = 2000; // 2 seconds between retries

        try {
            String refreshToken = CacheManager.getString(context, CacheManager.KEY_REFRESH_TOKEN);
            if (refreshToken == null || refreshToken.isEmpty()) return false;

            OkHttpClient refreshClient = new OkHttpClient.Builder()
                    .addInterceptor(new EncryptionInterceptor(context))
                    .addInterceptor(logging)
                    .connectTimeout(30, TimeUnit.SECONDS)
                    .readTimeout(30, TimeUnit.SECONDS)
                    .build();

            Gson customGson = new GsonBuilder()
                    .registerTypeAdapter(Integer.class, new StatusDeserializer())
                    .registerTypeAdapter(int.class, new StatusDeserializer())
                    .create();

            Retrofit refreshRetrofit = new Retrofit.Builder()
                    .baseUrl(context.getString(R.string.api_base_url))
                    .addConverterFactory(GsonConverterFactory.create(customGson))
                    .client(refreshClient)
                    .build();

            ApiService apiService = refreshRetrofit.create(ApiService.class);
            RequestRefresh request = new RequestRefresh(refreshToken);

            for (int attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    Log.d("### REFRESH", "Attempt " + attempt + " to refresh token...");
                    retrofit2.Response<BaseResponse<Void>> refreshResponse = apiService.refreshToken(request).execute();

                    if (refreshResponse.isSuccessful() && refreshResponse.body() != null && refreshResponse.body().isStatus()) {
                        BaseResponse<Void> body = refreshResponse.body();
                        CacheManager.saveString(context, CacheManager.KEY_ACCESS_TOKEN, body.getAccess_token());
                        CacheManager.saveString(context, CacheManager.KEY_REFRESH_TOKEN, body.getRefresh_token());

                        Log.d("### REFRESH", "New access token received.");
                        success = true;
                        break;
                    } else {
                        String errorMessage = "Unknown error";
                        if (refreshResponse.errorBody() != null) {
                            try {
                                String errorJson = refreshResponse.errorBody().string();
                                JSONObject obj = new JSONObject(errorJson);
                                errorMessage = obj.optString("error", errorJson);
                            } catch (Exception ignored) {
                            }
                        }

                        Log.w("### REFRESH", "Refresh failed (code " + refreshResponse.code() + "): " + errorMessage);

                        // If invalid/expired refresh token → don't retry
                        if (errorMessage.toLowerCase().contains("invalid")
                                || (!errorMessage.toLowerCase().contains("otp") && errorMessage.contains("expired") && errorMessage.toLowerCase().contains("expired"))
                                || errorMessage.toLowerCase().contains("unauthorized")) {
                            Log.w("### REFRESH", "Refresh token invalid/expired → stop retrying.");
                            break;
                        }
                    }
                } catch (IOException e) {
                    // Retry only for timeouts or connection issues
                    if (e.getMessage() != null && (
                            e.getMessage().contains("timeout") ||
                                    e.getMessage().contains("Failed to connect") ||
                                    e.getMessage().contains("Unable to resolve host"))
                    ) {
                        Log.w("### REFRESH", "Network timeout on attempt " + attempt + ", retrying...");
                    } else {
                        Log.e("### REFRESH", "Non-retryable IO error", e);
                        break; // stop retrying for other IO errors
                    }
                }

                if (!success && attempt < maxRetries) {
                    try {
                        Thread.sleep(retryDelayMs * attempt); // exponential backoff (2s, 4s, 6s)
                    } catch (InterruptedException ie) {
                        Thread.currentThread().interrupt();
                        break;
                    }
                }
            }

        } finally {
            synchronized (refreshLock) {
                isRefreshing = false;
                refreshLock.notifyAll();
            }
        }

        return success;
    }

    private static void handleForcedLogout(Context context) {
        Log.d("### LOGOUT", "Refreshing token failed → logging out");
        CacheManager.clearAll(context);
        Intent intent = new Intent("FORCE_LOGOUT");
        LocalBroadcastManager.getInstance(context.getApplicationContext()).sendBroadcast(intent);
    }
}
