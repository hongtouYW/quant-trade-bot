package com.dj.manager.util;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.TextView;

import androidx.core.content.FileProvider;

import com.dj.manager.R;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class InAppUpdater {

    private static final String PREF = "in_app_updater";
    private static final String KEY_PENDING_URL = "pending_url";

    private Activity activity;
    private AlertDialog dialog;
    private ProgressBar progressBar;
    private TextView textTitle, textProgress;

    public InAppUpdater(Activity activity) {
        this.activity = activity;
    }

    // =============================
    // PUBLIC ENTRY
    // =============================
    public void startUpdate(String apkUrl) {
        if (!canInstallUnknownApps()) {
            savePendingUrl(apkUrl);
            requestInstallPermission();
            return;
        }
        downloadApk(apkUrl);
    }

    // =============================
    // PERMISSION
    // =============================
    private boolean canInstallUnknownApps() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            return activity.getPackageManager().canRequestPackageInstalls();
        }
        return true;
    }

    private void requestInstallPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Intent intent = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES);
            intent.setData(Uri.parse("package:" + activity.getPackageName()));
            activity.startActivity(intent);
        }
    }

    // =============================
    // SAVE / RESTORE PENDING URL
    // =============================
    private void savePendingUrl(String url) {
        SharedPreferences prefs = activity.getSharedPreferences(PREF, Context.MODE_PRIVATE);
        prefs.edit().putString(KEY_PENDING_URL, url).apply();
    }

    public static String consumePendingUrl(Context context) {
        SharedPreferences prefs = context.getSharedPreferences(PREF, Context.MODE_PRIVATE);
        String url = prefs.getString(KEY_PENDING_URL, null);
        if (url != null) prefs.edit().remove(KEY_PENDING_URL).apply();
        return url;
    }

    // =============================
    // DOWNLOAD APK
    // =============================
    private void downloadApk(String apkUrl) {
        showProgressDialog();

        new Thread(() -> {
            try {
                URL url = new URL(apkUrl);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.connect();

                int fileLength = conn.getContentLength();

                InputStream input = conn.getInputStream();

                File apkFile = new File(
                        activity.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS),
                        "update.apk"
                );

                FileOutputStream output = new FileOutputStream(apkFile);

                byte[] data = new byte[4096];
                long total = 0;
                int count;

                while ((count = input.read(data)) != -1) {
                    total += count;
                    output.write(data, 0, count);

                    int progress = (int) (total * 100 / fileLength);

                    int downloadedKB = (int) (total / 1024);
                    int totalKB = (int) (fileLength / 1024);

                    int finalProgress = progress;
                    activity.runOnUiThread(() -> {
                        progressBar.setProgress(finalProgress);
                        textTitle.setText(R.string.in_app_updater_downloading_update);
                        textProgress.setText(String.format(activity.getString(R.string.in_app_updater_progress), finalProgress, downloadedKB, totalKB));
                    });
                }

                output.close();
                input.close();

                // Show installing state
                showInstallingState();

                new Handler(Looper.getMainLooper()).postDelayed(() -> {
                    installApk(apkFile);
                }, 500);

            } catch (Exception e) {
                e.printStackTrace();
                dismissDialog();
            }
        }).start();
    }

    // =============================
    // DIALOG
    // =============================
    private void showProgressDialog() {
        View view = LayoutInflater.from(activity).inflate(R.layout.dialog_update_progress, null);
        progressBar = view.findViewById(R.id.progressBar);
        textTitle = view.findViewById(R.id.textTitle);
        textProgress = view.findViewById(R.id.textProgress);
        dialog = new AlertDialog.Builder(activity)
                .setView(view)
                .setCancelable(false)
                .create();
        dialog.show();
    }

    private void showInstallingState() {
        activity.runOnUiThread(() -> {
            textTitle.setText(R.string.in_app_updater_installing_update);
            textProgress.setText(R.string.in_app_updater_wait);
            progressBar.setIndeterminate(true);
        });
    }

    private void dismissDialog() {
        activity.runOnUiThread(() -> {
            if (dialog != null && dialog.isShowing()) {
                dialog.dismiss();
            }
        });
    }

    // =============================
    // INSTALL APK
    // =============================
    private void installApk(File apkFile) {
        try {
            Uri apkUri = FileProvider.getUriForFile(activity, activity.getPackageName() + ".provider", apkFile);
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(apkUri, "application/vnd.android.package-archive");
            intent.setFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
            activity.startActivity(intent);
            dismissDialog();
        } catch (Exception e) {
            e.printStackTrace();
            dismissDialog();
        }
    }

    // =============================
    // CLEANUP OLD APK
    // =============================
    public static void cleanupIfNeeded(Context context) {
        try {
            File apkFile = new File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "update.apk");
            if (apkFile.exists()) apkFile.delete();
        } catch (Exception ignored) {
        }
    }
}