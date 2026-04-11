package com.dj.user.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityBindEmailBinding;
import com.dj.user.enums.ActionType;
import com.dj.user.model.request.RequestBindEmail;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.google.gson.Gson;

public class BindEmailActivity extends BaseActivity {

    private ActivityBindEmailBinding binding;
    private Member member;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBindEmailBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.bind_email_title), 0, null);
        setupUI();
    }

    private void setupUI() {
        binding.editTextEmail.setText(!StringUtil.isNullOrEmpty(member.getEmail()) ? member.getEmail() : "");
        binding.editTextEmail.setBackgroundTransparent(true);
        binding.editTextEmail.setHint(getString(R.string.bind_email_hint));

        binding.buttonNext.setOnClickListener(view -> bindEmail());
    }

    private void bindEmail() {
        if (member == null) {
            return;
        }
        String email = binding.editTextEmail.getText();
        if (email.isEmpty() || !StringUtil.isValidEmail(email)) {
            binding.editTextEmail.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestBindEmail request = new RequestBindEmail(member.getMember_id(), email);
        executeApiCall(this, apiService.requestBindEmail(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(BindEmailActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(ActionType.BIND_EMAIL));
                bundle.putString("request", new Gson().toJson(request));
                startAppActivity(new Intent(BindEmailActivity.this, BindOTPActivity.class),
                        bundle, false, false, true
                );
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}