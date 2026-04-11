package com.dj.user.activity;

import android.graphics.Bitmap;
import android.os.Bundle;

import com.dj.user.databinding.ActivityFullScreenImageBinding;
import com.dj.user.util.ImageUtils;
import com.squareup.picasso.Picasso;

public class FullScreenImageActivity extends BaseActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ActivityFullScreenImageBinding binding = ActivityFullScreenImageBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        String data = getIntent().getStringExtra("data");
        boolean isQrUrl = getIntent().getBooleanExtra("isQrUrl", false);
        if (data == null) {
            onBaseBackPressed();
            return;
        }
        if (data.startsWith("http") && !isQrUrl) {
            Picasso.get().load(data).centerInside().fit().into(binding.imageViewFull);
        } else {
            Bitmap bitmap = ImageUtils.generateQRCode(this, data);
            if (bitmap != null) {
                binding.imageViewFull.setImageBitmap(bitmap);
            }
        }
        binding.imageViewFull.setOnClickListener(v -> onBaseBackPressed());
    }
}
