package com.dj.user.dj.activity.mine.setting;

import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.DjActivityContactUsBinding;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.StringUtil;

public class DJContactUsActivity extends BaseActivity {

    private DjActivityContactUsBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityContactUsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.find_us_title), 0, null);
        setupUI();
    }

    private void setupUI() {
        binding.imageViewCopyWeb1.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", binding.textViewWeb1.getText().toString()));
        binding.imageViewCopyWeb2.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", binding.textViewWeb2.getText().toString()));
        binding.buttonSaveAddress.setOnClickListener(view -> captureAndSavePage());
        binding.imageViewCopyEmail.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", binding.textViewEmail.getText().toString()));
        binding.buttonSaveEmail.setOnClickListener(view -> captureAndSavePage());
    }

    private void captureAndSavePage() {
        try {
            View view = binding.scrollView;
            Bitmap bitmap = Bitmap.createBitmap(view.getWidth(), view.getHeight(), Bitmap.Config.ARGB_8888);
            Canvas canvas = new Canvas(bitmap);
            view.draw(canvas);

            String fileName = "contact_us_" + System.currentTimeMillis();
            Uri uri = ImageUtils.saveBitmap(this, bitmap, fileName);
            if (uri != null) {
                Toast.makeText(this, "Saved to gallery: " + uri.getPath(), Toast.LENGTH_LONG).show();
            } else {
                Toast.makeText(this, "Failed to save image", Toast.LENGTH_SHORT).show();
            }
        } catch (Exception e) {
            Log.e("###", "captureAndSavePage: ", e);
            Toast.makeText(this, "Error capturing page", Toast.LENGTH_SHORT).show();
        }
    }
}