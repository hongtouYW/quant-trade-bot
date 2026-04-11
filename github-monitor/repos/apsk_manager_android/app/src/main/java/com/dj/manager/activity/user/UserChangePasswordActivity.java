package com.dj.manager.activity.user;

import android.content.Intent;
import android.os.Bundle;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityUserChangePasswordBinding;
import com.dj.manager.model.request.RequestMemberChangePassword;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.google.gson.Gson;

public class UserChangePasswordActivity extends BaseActivity {
    private ActivityUserChangePasswordBinding binding;
    private Manager manager;
    private Member member;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUserChangePasswordBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.user_change_password_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        member = new Gson().fromJson(json, Member.class);
    }

    private void setupUI() {
        String vipName = "vip_" + member.getVip();
        int resId = getResources().getIdentifier(vipName, "drawable", getPackageName());
        binding.imageViewVip.setImageResource(resId);
        binding.textViewPhone.setText(FormatUtils.formatMsianPhone(member.getPhone()));
        binding.textViewPhone.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getPhone()));

        binding.textViewId.setText(member.getPrefix());
        binding.textViewId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getPrefix()));
        binding.buttonConfirm.setOnClickListener(view -> changeMemberPassword());
    }

    private void changeMemberPassword() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberChangePassword request = new RequestMemberChangePassword(manager.getManager_id(), member.getMember_id());
        executeApiCall(this, apiService.changeMemberPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member updatedMember = response.getData();
                String password = response.getPassword();
                updatedMember.setMember_pass(password);
                member = updatedMember;

                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(updatedMember));
                startAppActivity(new Intent(UserChangePasswordActivity.this, UserChangePasswordSuccessActivity.class),
                        bundle, true, false, false, true
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