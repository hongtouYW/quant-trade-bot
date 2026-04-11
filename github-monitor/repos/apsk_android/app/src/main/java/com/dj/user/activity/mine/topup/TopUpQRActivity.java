package com.dj.user.activity.mine.topup;

import android.os.Bundle;

import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityTopUpQrBinding;

public class TopUpQRActivity extends BaseActivity {

    private ActivityTopUpQrBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTopUpQrBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "QR充值", 0, null);
    }
}