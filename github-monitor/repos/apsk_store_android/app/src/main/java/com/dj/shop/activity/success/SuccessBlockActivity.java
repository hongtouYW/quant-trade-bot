package com.dj.shop.activity.success;

import android.os.Bundle;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.databinding.ActivitySuccessBlockBinding;
import com.dj.shop.model.request.RequestMemberBlockReason;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.widget.AndroidBug5497Workaround;
import com.dj.shop.widget.RoundedEditTextView;

public class SuccessBlockActivity extends BaseActivity {
    private ActivitySuccessBlockBinding binding;
    private Shop shop;
    private String memberId;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySuccessBlockBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        setupUI();
    }

    private void parseIntentData() {
        memberId = getIntent().getStringExtra("id");
    }

    private void setupUI() {
        binding.editTextReason.setInputFieldType(RoundedEditTextView.InputFieldType.MULTILINE_TEXT);
        binding.editTextReason.setHint(getString(R.string.action_main_block_reason_hint));
        binding.buttonSubmit.setOnClickListener(view -> submitBlockReason());
        binding.textViewLink.setOnClickListener(view -> onBaseBackPressed());
    }

    private void submitBlockReason() {
        String reason = binding.editTextReason.getText();
        if (reason.isEmpty()) {
            binding.editTextReason.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberBlockReason request = new RequestMemberBlockReason(shop.getShop_id(), memberId, reason);
        executeApiCall(this, apiService.blockUserReason(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                showCustomConfirmationDialog(
                        SuccessBlockActivity.this,
                        getString(R.string.action_main_block_reason_success_title),
                        getString(R.string.action_main_block_reason_success_desc),
                        getString(R.string.action_main_block_reason_success_okay),
                        () -> onBaseBackPressed()
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