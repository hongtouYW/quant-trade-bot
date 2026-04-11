package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.view.View;
import android.widget.FrameLayout;

import androidx.annotation.NonNull;

import com.dj.user.R;

public class FullscreenLoadingDialog extends Dialog {
    public FullscreenLoadingDialog(@NonNull Context context) {
        super(context, R.style.AppTheme_FullscreenDialog);
        setContentView(R.layout.view_fullscreen_loader);
        setCancelable(false); // prevent back press

        FrameLayout view = findViewById(R.id.view_fullscreen_loader);
        view.setVisibility(View.VISIBLE);
    }
}
