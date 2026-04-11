package com.dj.manager.activity.user;

import android.content.Intent;
import android.os.Bundle;

import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityUserChangePasswordSuccessBinding;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.google.gson.Gson;

public class UserChangePasswordSuccessActivity extends BaseActivity {
    private ActivityUserChangePasswordSuccessBinding binding;
    private Member member;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUserChangePasswordSuccessBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        member = new Gson().fromJson(json, Member.class);
    }

    private void setupUI() {
        if (member == null) {
            return;
        }
        binding.textViewId.setText(FormatUtils.formatMsianPhone(member.getMember_login()));
        binding.textViewId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getMember_login()));
        binding.textViewPassword.setText(member.getMember_pass());
        binding.textViewId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getMember_pass()));
        binding.buttonBack.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(member));
            startAppActivity(new Intent(UserChangePasswordSuccessActivity.this, UserDetailsActivity.class),
                    null, true, false, true, true
            );
        });
    }
}