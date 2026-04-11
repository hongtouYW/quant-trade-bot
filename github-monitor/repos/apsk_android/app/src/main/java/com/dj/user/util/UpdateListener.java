package com.dj.user.util;

import java.io.File;

public interface UpdateListener {
    void onStart();

    void onProgress(int percent);

    void onDownloaded(File apk);

    void onError(String msg);
}