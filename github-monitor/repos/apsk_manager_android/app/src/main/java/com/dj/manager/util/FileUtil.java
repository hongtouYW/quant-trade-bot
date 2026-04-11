package com.dj.manager.util;

import android.content.Context;
import android.database.Cursor;
import android.graphics.Bitmap;
import android.net.Uri;
import android.provider.MediaStore;
import android.provider.OpenableColumns;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;

public class FileUtil {
    public static File getFileFromUri(Context context, Uri uri) throws IOException {
        // For older Android versions that support _data column
        if ("file".equalsIgnoreCase(uri.getScheme())) {
            return new File(uri.getPath());
        }
        // For content:// Uris (e.g., from gallery, camera)
        InputStream inputStream = context.getContentResolver().openInputStream(uri);
        if (inputStream == null) return null;

        String fileName = getFileName(context, uri);
        if (fileName == null) {
            fileName = "temp_file_" + System.currentTimeMillis();
        }
        File tempFile = new File(context.getCacheDir(), fileName);
        try (OutputStream outputStream = new FileOutputStream(tempFile)) {
            byte[] buffer = new byte[4096];
            int bytesRead;
            while ((bytesRead = inputStream.read(buffer)) != -1) {
                outputStream.write(buffer, 0, bytesRead);
            }
        }
        inputStream.close();
        return tempFile;
    }

    public static String getFileName(Context context, Uri uri) {
        String result = null;
        if (uri.getScheme().equals("content")) {
            try (Cursor cursor = context.getContentResolver().query(uri, null, null, null, null)) {
                if (cursor != null && cursor.moveToFirst()) {
                    int nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                    if (nameIndex != -1) {
                        result = cursor.getString(nameIndex);
                    }
                }
            }
        }
        if (result == null) {
            result = uri.getLastPathSegment();
        }
        return result;
    }

    public static long getFileSize(Context context, Uri uri) {
        Cursor cursor = context.getContentResolver().query(uri, null, null, null, null);
        if (cursor != null) {
            int sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE);
            cursor.moveToFirst();
            long size = cursor.getLong(sizeIndex);
            cursor.close();
            return size;
        }
        return 0;
    }

    public static String getPath(Context context, Uri uri) {
        String[] projection = {MediaStore.Images.Media.DATA};
        Cursor cursor = context.getContentResolver().query(uri, projection, null, null, null);
        if (cursor != null) {
            int column_index = cursor.getColumnIndexOrThrow(MediaStore.Images.Media.DATA);
            cursor.moveToFirst();
            String path = cursor.getString(column_index);
            cursor.close();
            return path;
        }
        return null;
    }

    public static File compressImageToUnder2MB(Context context, Uri uri) throws IOException {
        Bitmap bitmap = MediaStore.Images.Media.getBitmap(context.getContentResolver(), uri);
        int quality = 100;
        File compressedFile = File.createTempFile("compressed_", ".jpg", context.getCacheDir());

        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        bitmap.compress(Bitmap.CompressFormat.JPEG, quality, baos);

        while (baos.toByteArray().length > 2 * 1024 * 1024 && quality > 10) {
            baos.reset(); // clear the stream
            quality -= 5;
            bitmap.compress(Bitmap.CompressFormat.JPEG, quality, baos);
        }
        FileOutputStream out = new FileOutputStream(compressedFile);
        out.write(baos.toByteArray());
        out.flush();
        out.close();
        baos.close();

        if (compressedFile.length() > 2 * 1024 * 1024) {
            return null; // even at lowest quality it's still too big
        }
        return compressedFile;
    }
}
