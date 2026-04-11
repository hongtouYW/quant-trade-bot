package com.dj.shop.widget;

import android.app.Dialog;
import android.content.Context;
import android.graphics.Bitmap;
import android.widget.ImageView;

import androidx.annotation.NonNull;

import com.dj.shop.R;
import com.dj.shop.util.ImageUtils;

public class QrExpandedDialog extends Dialog {
    public QrExpandedDialog(@NonNull Context context, String qrContent) {
        super(context, R.style.AppTheme_FullscreenDialog);
        setContentView(R.layout.dialog_expanded_qr);
        setCancelable(true);

        ImageView collapseImageView = findViewById(R.id.imageView_collapse);
        collapseImageView.setOnClickListener(v -> dismiss());

        ImageView qrImageView = findViewById(R.id.imageView_qr);
        Bitmap bitmap = ImageUtils.generateQRCode(context);
        if (bitmap != null) {
            qrImageView.setImageBitmap(bitmap);
        }
    }
}
