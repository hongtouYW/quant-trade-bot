package com.dj.user.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.ProfileSectionListViewAdapter;
import com.dj.user.databinding.ActivitySecurityPrivacyBinding;
import com.dj.user.model.ItemSection;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;

import java.util.ArrayList;
import java.util.List;

public class SecurityPrivacyActivity extends BaseActivity {

    private ActivitySecurityPrivacyBinding binding;
    private Member member;
    private ProfileSectionListViewAdapter memberAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySecurityPrivacyBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.security_privacy_title), 0, null);
        setupActionList();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getProfile();
    }

    private void setupActionList() {
        memberAdapter = new ProfileSectionListViewAdapter(this);
        binding.listViewSecurityPrivacy.setAdapter(memberAdapter);

        refreshActionList();
        memberAdapter.setOnItemClickListener((position, object) -> {
            ItemSection clicked = (ItemSection) object;
            switch (clicked.getId()) {
                case 1:
                    startAppActivity(new Intent(SecurityPrivacyActivity.this, BindPhoneActivity.class),
                            null, false, false, true);
                    break;
                case 2:
                    startAppActivity(new Intent(SecurityPrivacyActivity.this, BindEmailActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    startAppActivity(new Intent(SecurityPrivacyActivity.this, BindAuthenticatorActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    startAppActivity(new Intent(SecurityPrivacyActivity.this, BiometricActivity.class),
                            null, false, false, true);
                    break;
                case 5:
                    startAppActivity(new Intent(SecurityPrivacyActivity.this, UpdatePasswordActivity.class),
                            null, false, false, true);
                    break;
            }
        });
    }

    private void refreshActionList() {
        List<ItemSection> actionList = new ArrayList<>();
        actionList.add(new ItemSection(1, R.drawable.ic_security_phone, getString(R.string.security_privacy_phone),
                (member != null && member.isPhoneBinded()) ? getString(R.string.security_privacy_binded) : getString(R.string.security_privacy_to_bind)));
        actionList.add(new ItemSection(2, R.drawable.ic_security_email, getString(R.string.security_privacy_email),
                (member != null && member.isEmailBinded()) ? getString(R.string.security_privacy_binded) : getString(R.string.security_privacy_to_bind)));
        actionList.add(new ItemSection(3, R.drawable.ic_security_google, getString(R.string.security_privacy_google),
                (member != null && member.isGoogleAuthenticatorBinded()) ? getString(R.string.security_privacy_binded) : getString(R.string.security_privacy_to_bind)));

        boolean isEnabled = CacheManager.getBoolean(this, CacheManager.KEY_BIOMETRIC_ENABLED);
        actionList.add(new ItemSection(4, R.drawable.ic_security_biometric, getString(R.string.security_privacy_biometric), isEnabled ? getString(R.string.security_privacy_binded) : getString(R.string.security_privacy_to_bind)));

        actionList.add(new ItemSection(5, R.drawable.ic_security_password, getString(R.string.security_privacy_password), ""));

        memberAdapter.removeList();
        memberAdapter.replaceList(actionList);
    }

    private void getProfile() {
        if (member == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(SecurityPrivacyActivity.this, CacheManager.KEY_USER_PROFILE, member);
                    refreshActionList();
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, false);
    }
}