package com.dj.manager.util;

import android.content.Context;
import android.util.Log;

import org.json.JSONObject;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;

import okhttp3.Interceptor;
import okhttp3.MediaType;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;
import okhttp3.ResponseBody;
import okio.Buffer;

public class EncryptionInterceptor implements Interceptor {

    private final Context context;
    private static final String IV_BASE = "RWf23muavY";

    public EncryptionInterceptor(Context context) {
        this.context = context;
    }

    @Override
    public Response intercept(Chain chain) throws IOException {
        Request original = chain.request();
        boolean skipRequestEncryption = "true".equalsIgnoreCase(original.header("X-No-Encryption"));

        Request encryptedRequest = original;

        if (!skipRequestEncryption) {
            RequestBody originalBody = original.body();

            if (originalBody != null && originalBody.contentType() != null
                    && originalBody.contentType().subtype().equals("json")) {
                try {
                    Buffer buffer = new Buffer();
                    originalBody.writeTo(buffer);
                    String originalJson = buffer.readString(StandardCharsets.UTF_8);

                    String ivSuffix = CryptoUtils.generateIvSuffix();
                    String fullIV = IV_BASE + ivSuffix;
                    String encrypted = CryptoUtils.encrypt(originalJson, fullIV);

                    JSONObject requestWrapper = new JSONObject();
                    requestWrapper.put("data", encrypted);
                    requestWrapper.put("iv", ivSuffix);

                    RequestBody newBody = RequestBody.create(
                            requestWrapper.toString(),
                            MediaType.get("application/json; charset=utf-8")
                    );

                    encryptedRequest = original.newBuilder()
                            .removeHeader("X-No-Encryption") // Clean up header
                            .method(original.method(), newBody)
                            .build();
                } catch (Exception e) {
                    throw new IOException("Encryption failed", e);
                }
            }
        } else {
            // Just remove the header and proceed with original body
            encryptedRequest = original.newBuilder()
                    .removeHeader("X-No-Encryption")
                    .build();
        }

        // Proceed with the encrypted request
        Response response = chain.proceed(encryptedRequest);

        // Decrypt response
        ResponseBody responseBody = response.body();
        if (responseBody != null && response.body().contentType() != null
                && response.body().contentType().subtype().equals("json")) {
            String responseString = responseBody.string();
            try {
                JSONObject jsonResponse = new JSONObject(responseString);
                String data = jsonResponse.optString("data", null);
                String ivSuffix = jsonResponse.optString("iv", null);

                String fullIV = IV_BASE + ivSuffix;
                String decrypted = CryptoUtils.decrypt(data, fullIV);

                Log.d("### DecryptedResponse", decrypted);

                // Optional: Verify signature
//                JSONObject decryptedJson = new JSONObject(decrypted);
//                Map<String, String> map = new HashMap<>();
//                Iterator<String> keys = decryptedJson.keys();
//                while (keys.hasNext()) {
//                    String key = keys.next();
//                    map.put(key, decryptedJson.getString(key));
//                }

//                String respSign = map.get("encode_sign");
//                map.remove("encode_sign");
//                String expectedSign = CryptoUtils.generateSignature(map);
//                if (respSign != null && !respSign.equals(expectedSign)) {
//                    throw new IOException("Invalid signature!");
//                }

                ResponseBody newResponseBody = ResponseBody.create(
                        decrypted,
                        MediaType.get("application/json; charset=utf-8")
                );
                return response.newBuilder().body(newResponseBody).build();

            } catch (Exception e) {
                throw new IOException("Decryption failed", e);
            }
        }

        return response;
    }
}
