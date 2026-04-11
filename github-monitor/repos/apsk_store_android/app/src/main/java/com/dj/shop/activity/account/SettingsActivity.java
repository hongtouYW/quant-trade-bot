package com.dj.shop.activity.account;

import android.content.Intent;
import android.os.Bundle;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.PrinterActivity;
import com.dj.shop.databinding.ActivitySettingsBinding;

public class SettingsActivity extends BaseActivity {
    private ActivitySettingsBinding binding;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySettingsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.profile_setting_title), 0, null);
        setupUI();
    }

    private void setupUI() {
        binding.panelUserDetails.setOnClickListener(view -> startAppActivity(
                new Intent(SettingsActivity.this, UserDetailsActivity.class),
                null, false, false, true
        ));
        binding.panelPrinter.setOnClickListener(view -> startAppActivity(
                new Intent(SettingsActivity.this, PrinterActivity.class),
                null, false, false, true
        ));
    }
}