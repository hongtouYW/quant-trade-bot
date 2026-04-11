package com.dj.user.util;

import android.util.Base64;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Map;

import javax.crypto.Cipher;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;

public class CryptoUtils {
    private static final String AES_KEY = "0XxdjmI55ZjjqQLO3nI7gGqrBP0Vz9jS"; // 32 chars
    private static final String IV_BASE = "RWf23muavY"; // 10 chars
    private static final String SIGN_KEY = "NRkw0g3iJLDvw5tJ5PuVt5276z0SOuyL";

    public static String generateIvSuffix() {
        byte[] randomBytes = new byte[3];
        new SecureRandom().nextBytes(randomBytes);
        String hex = bytesToHex(randomBytes);
        return hex.substring(0, 6);
    }

    private static String bytesToHex(byte[] bytes) {
        StringBuilder sb = new StringBuilder();
        for (byte b : bytes) sb.append(String.format("%02x", b));
        return sb.toString();
    }

    public static String generateSignature(Map<String, String> payload) throws Exception {
        List<String> keys = new ArrayList<>(payload.keySet());
        Collections.sort(keys);
        StringBuilder sb = new StringBuilder();
        for (String key : keys) {
            sb.append(URLEncoder.encode(key, "UTF-8"))
                    .append("=")
                    .append(URLEncoder.encode(payload.get(key), "UTF-8"))
                    .append("&");
        }
        sb.deleteCharAt(sb.length() - 1); // Remove trailing &
        sb.append(SIGN_KEY);
        return md5(sb.toString());
    }

    public static String md5(String input) throws Exception {
        java.security.MessageDigest md = java.security.MessageDigest.getInstance("MD5");
        byte[] messageDigest = md.digest(input.getBytes(StandardCharsets.UTF_8));
        StringBuilder hexString = new StringBuilder();
        for (byte b : messageDigest)
            hexString.append(String.format("%02x", b));
        return hexString.toString();
    }

    public static String encrypt(String json, String iv) throws Exception {
        IvParameterSpec ivSpec = new IvParameterSpec(iv.getBytes(StandardCharsets.UTF_8));
        SecretKeySpec secretKey = new SecretKeySpec(AES_KEY.getBytes(StandardCharsets.UTF_8), "AES");
        Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
        cipher.init(Cipher.ENCRYPT_MODE, secretKey, ivSpec);
        byte[] encrypted = cipher.doFinal(json.getBytes(StandardCharsets.UTF_8));
        return Base64.encodeToString(encrypted, Base64.NO_WRAP);
    }

    public static String decrypt(String base64Data, String iv) throws Exception {
        IvParameterSpec ivSpec = new IvParameterSpec(iv.getBytes(StandardCharsets.UTF_8));
        SecretKeySpec secretKey = new SecretKeySpec(AES_KEY.getBytes(StandardCharsets.UTF_8), "AES");
        Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
        cipher.init(Cipher.DECRYPT_MODE, secretKey, ivSpec);
        byte[] decoded = Base64.decode(base64Data, Base64.NO_WRAP);
        byte[] decrypted = cipher.doFinal(decoded);
        return new String(decrypted, StandardCharsets.UTF_8);
    }
}
