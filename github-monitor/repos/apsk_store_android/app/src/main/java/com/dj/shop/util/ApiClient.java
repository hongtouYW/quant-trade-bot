package com.dj.shop.util;

import android.content.Context;
import android.content.Intent;
import android.util.Log;

import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.dj.shop.R;
import com.dj.shop.model.request.RequestRefresh;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Transaction;
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

    public static Retrofit getInstance(Context context) {
        if (retrofit == null) {
            Gson customGson = new GsonBuilder()
                    .registerTypeAdapter(
                            new TypeToken<BaseResponse<List<Transaction>>>() {
                            }.getType(),
                            new TransactionListDeserializer()
                    )
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
                .connectTimeout(30, TimeUnit.SECONDS) // default 10s → now 60s
                .readTimeout(30, TimeUnit.SECONDS)    // response reading timeout
                .writeTimeout(30, TimeUnit.SECONDS)
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
                    .build();

            Response response = chain.proceed(requestWithToken);

            if (isUnauthorized(response)) {
                Log.d("### TOKEN", "Access token expired, attempting to refresh...");

                if (tryRefreshToken(context, logging)) {
                    String newAccessToken = CacheManager.getString(context, CacheManager.KEY_ACCESS_TOKEN);
                    Request retryRequest = originalRequest.newBuilder()
                            .header("Accept-Language", language)
                            .header("Authorization", "Bearer " + newAccessToken)
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
                    error.contains("unauthenticated") ||
                            error.contains("unauthorized") ||
                            error.contains("invalid") ||
                            error.contains("expired")
            );
        } catch (Exception e) {
            Log.e("### TOKEN_CHECK", "Unable to parse response", e);
        }
        return false;
    }

    private static boolean tryRefreshToken(Context context, HttpLoggingInterceptor logging) {
        String refreshToken = CacheManager.getString(context, CacheManager.KEY_REFRESH_TOKEN);
        if (refreshToken == null || refreshToken.isEmpty()) return false;

        try {
            OkHttpClient refreshClient = new OkHttpClient.Builder()
                    .addInterceptor(new EncryptionInterceptor(context))
                    .addInterceptor(logging)
                    .build();

            Retrofit refreshRetrofit = new Retrofit.Builder()
                    .baseUrl(context.getString(R.string.api_base_url))
                    .addConverterFactory(GsonConverterFactory.create())
                    .client(refreshClient)
                    .build();

            ApiService apiService = refreshRetrofit.create(ApiService.class);
            RequestRefresh request = new RequestRefresh(refreshToken);
            retrofit2.Response<BaseResponse<Void>> refreshResponse = apiService.refreshToken(request).execute();

            if (refreshResponse.isSuccessful() && refreshResponse.body() != null && refreshResponse.body().isStatus()) {
                BaseResponse<Void> body = refreshResponse.body();
                CacheManager.saveString(context, CacheManager.KEY_ACCESS_TOKEN, body.getAccess_token());
                CacheManager.saveString(context, CacheManager.KEY_REFRESH_TOKEN, body.getRefresh_token());

                Log.d("### REFRESH", "New access token received.");
                return true;
            }

        } catch (IOException e) {
            Log.e("### REFRESH", "Failed to refresh token", e);
        }

        return false;
    }

    private static void handleForcedLogout(Context context) {
        Log.d("### LOGOUT", "Refreshing token failed → logging out");
        CacheManager.clearAll(context);
        Intent intent = new Intent("FORCE_LOGOUT");
        LocalBroadcastManager.getInstance(context.getApplicationContext()).sendBroadcast(intent);
    }
}
