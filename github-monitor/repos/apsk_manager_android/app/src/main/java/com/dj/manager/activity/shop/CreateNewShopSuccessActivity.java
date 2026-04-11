package com.dj.manager.activity.shop;

import android.content.Intent;
import android.os.Bundle;

import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityCreateNewShopSuccessBinding;

public class CreateNewShopSuccessActivity extends BaseActivity {
    private ActivityCreateNewShopSuccessBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityCreateNewShopSuccessBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        setupUI();
    }

    private void setupUI() {
        binding.buttonBack.setOnClickListener(view ->
                startAppActivity(new Intent(CreateNewShopSuccessActivity.this, ShopManagementActivity.class),
                        null, true, false, true, true
                ));
    }
}