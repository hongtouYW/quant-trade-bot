package com.dj.user.dj.activity.mine.setting;

import android.os.Bundle;

import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.DjActivityHelpDetailsBinding;

public class DJHelpDetailsActivity extends BaseActivity {

    private DjActivityHelpDetailsBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityHelpDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "帮助", 0, null);
        setupUI();
    }

    private void setupUI() {
    }
}