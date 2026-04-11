package com.dj.manager.util;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.util.Base64;
import android.util.Log;

import com.dj.manager.model.response.Manager;
import com.google.zxing.BarcodeFormat;
import com.google.zxing.WriterException;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.qrcode.QRCodeWriter;

public class ImageUtils {

    public static Bitmap decodeBase64ToBitmap(String base64Image) {
        try {
            // Remove prefix if present (e.g., "data:image/png;base64,")
            if (base64Image.contains(",")) {
                base64Image = base64Image.split(",")[1];
            }

            byte[] decodedBytes = Base64.decode(base64Image, Base64.DEFAULT);
            return BitmapFactory.decodeByteArray(decodedBytes, 0, decodedBytes.length);
        } catch (IllegalArgumentException e) {
            Log.e("### decodeBase64", e.toString());
            return null;
        }
    }

    public static Bitmap generateQRCode(Context context) {
        String text = "";
        int size = 512; // pixels

        Manager manager = CacheManager.getObject(context, CacheManager.KEY_MANAGER_PROFILE, Manager.class);
        if (manager != null) {
            text = String.format("apsk://manager?id=%s", manager.getManager_id());
        }
        QRCodeWriter writer = new QRCodeWriter();
        try {
            BitMatrix bitMatrix = writer.encode(text, BarcodeFormat.QR_CODE, size, size);
            Bitmap bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.RGB_565);
            for (int x = 0; x < size; x++) {
                for (int y = 0; y < size; y++) {
                    bitmap.setPixel(x, y, bitMatrix.get(x, y) ? Color.BLACK : Color.WHITE);
                }
            }
            return bitmap;

        } catch (WriterException e) {
            Log.e("###", "generateQRCode: ", e);
            return null;
        }
    }
}
