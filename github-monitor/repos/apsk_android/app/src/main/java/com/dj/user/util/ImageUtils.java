package com.dj.user.util;

import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Base64;
import android.util.Log;

import com.google.zxing.BarcodeFormat;
import com.google.zxing.WriterException;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.qrcode.QRCodeWriter;

import java.io.File;
import java.io.FileOutputStream;
import java.io.OutputStream;

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

    public static Bitmap generateQRCode(Context context, String content) {
        int size = 512; // pixels
        QRCodeWriter writer = new QRCodeWriter();
        try {
            BitMatrix bitMatrix = writer.encode(content, BarcodeFormat.QR_CODE, size, size);
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

    public static Uri saveBitmap(Context context, Bitmap bitmap, String fileName) {
        OutputStream fos;
        Uri imageUri = null;
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                // Android 10+ → use MediaStore
                ContentValues values = new ContentValues();
                values.put(MediaStore.Images.Media.DISPLAY_NAME, fileName + ".png");
                values.put(MediaStore.Images.Media.MIME_TYPE, "image/png");
                values.put(MediaStore.Images.Media.RELATIVE_PATH, Environment.DIRECTORY_PICTURES);

                ContentResolver resolver = context.getContentResolver();
                imageUri = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);

                if (imageUri != null) {
                    fos = resolver.openOutputStream(imageUri);
                    bitmap.compress(Bitmap.CompressFormat.PNG, 100, fos);
                    fos.close();
                }
            } else {
                // Android 5–9 → write directly (need WRITE_EXTERNAL_STORAGE permission)
                File path = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_PICTURES);
                if (!path.exists()) path.mkdirs();

                File file = new File(path, fileName + ".png");
                fos = new FileOutputStream(file);
                bitmap.compress(Bitmap.CompressFormat.PNG, 100, fos);
                fos.close();

                imageUri = Uri.fromFile(file);
                // Make file visible in gallery
                Intent mediaScanIntent = new Intent(Intent.ACTION_MEDIA_SCANNER_SCAN_FILE);
                mediaScanIntent.setData(imageUri);
                context.sendBroadcast(mediaScanIntent);
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return imageUri;
    }
}
