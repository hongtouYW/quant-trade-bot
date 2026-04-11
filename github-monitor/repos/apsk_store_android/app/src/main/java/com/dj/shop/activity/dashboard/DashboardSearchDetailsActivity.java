package com.dj.shop.activity.dashboard;

import android.content.Intent;
import android.graphics.PorterDuff;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.transaction.ActionUserDetailsActivity;
import com.dj.shop.databinding.ActivityDashboardSearchDetailsBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.request.RequestMemberPassword;
import com.dj.shop.model.request.RequestMemberSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Member;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;
import com.google.gson.Gson;

public class DashboardSearchDetailsActivity extends BaseActivity {

    private ActivityDashboardSearchDetailsBinding binding;
    Shop shop;
    String memberId;
    Member member;
    private boolean isPasswordVisible = false;
    private String password = "";

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardSearchDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
    }

    @Override
    protected void onResume() {
        super.onResume();
        getMemberPassword();
    }

    private void parseIntentData() {
        memberId = getIntent().getStringExtra("data");
        getMemberProfile();
    }

    void setupUI() {
        if (member == null) {
            return;
        }
        binding.textViewPhone.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
        binding.textViewPhone.setOnClickListener(view -> {
            StringUtil.copyToClipboard(this, "", member.getPhone());
        });

        binding.panelPassword.setVisibility(shop.canViewCredentials() ? View.VISIBLE : View.GONE);
        binding.imageViewPasswordToggle.setOnClickListener(view -> {
            isPasswordVisible = !isPasswordVisible;
            if (!StringUtil.isNullOrEmpty(password)) {
                updatePasswordVisibility();
            } else {
                if (isPasswordVisible) {
                    getMemberPassword();
                }
            }
        });

        binding.textViewId.setText(member.getPrefix());
        binding.textViewId.setOnClickListener(view -> {
            StringUtil.copyToClipboard(this, "", member.getPrefix());
        });
        binding.textViewDate.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
        binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
        binding.buttonTopUp.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
            bundle.putString("id", member.getMember_id());
            startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                    false, false, true);
        });
        binding.buttonWithdraw.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(ActionType.WITHDRAWAL));
            bundle.putString("id", member.getMember_id());
            startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                    false, false, true);
        });
        binding.buttonChangePassword.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(ActionType.CHANGE_PASSWORD));
            bundle.putString("id", member.getMember_id());
            startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                    false, false, true);
        });
        binding.buttonBordered.setTextWhite();
        binding.buttonBordered.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(ActionType.BLOCK_USER));
            bundle.putString("id", member.getMember_id());
            startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                    false, false, true);
        });
    }

    private void updatePasswordVisibility() {
        binding.textViewPassword.setText(isPasswordVisible ? password : getString(R.string.placeholder_password_masked));
        binding.textViewPassword.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", password));
        if (isPasswordVisible) {
            binding.imageViewPasswordToggle.setColorFilter(ContextCompat.getColor(this, R.color.gray_C2C3CB), PorterDuff.Mode.SRC_IN);
        } else {
            binding.imageViewPasswordToggle.setColorFilter(ContextCompat.getColor(this, R.color.gold_D4AF37), PorterDuff.Mode.SRC_IN);
        }
    }

    private void getMemberProfile() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberSearch request = new RequestMemberSearch(shop.getShop_id(), memberId);
        executeApiCall(this, apiService.searchMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                memberId = member.getMember_id();
                setupUI();
                getMemberPassword();
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

    private void getMemberPassword() {
        if (member == null || !shop.canViewCredentials()) return;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberPassword request = new RequestMemberPassword(shop.getShop_id(), memberId);
        executeApiCall(this, apiService.getMemberPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                password = response.getPassword();
                updatePasswordVisibility();
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