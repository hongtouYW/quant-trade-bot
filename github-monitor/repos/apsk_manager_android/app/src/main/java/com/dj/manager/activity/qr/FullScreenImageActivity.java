package com.dj.manager.activity.qr;

import android.graphics.Bitmap;
import android.os.Bundle;

import androidx.annotation.Nullable;

import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityFullScreenImageBinding;
import com.dj.manager.util.ImageUtils;

public class FullScreenImageActivity extends BaseActivity {

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ActivityFullScreenImageBinding binding = ActivityFullScreenImageBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        Bitmap bitmap = ImageUtils.generateQRCode(this);
        if (bitmap != null) {
            binding.imageViewFull.setImageBitmap(bitmap);
        }
        binding.imageViewFull.setOnClickListener(v -> finish());
    }
}
