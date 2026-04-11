package com.dj.manager.activity.user;

import android.os.Bundle;

import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityActionReasonBinding;
import com.dj.manager.enums.ReasonActionType;
import com.dj.manager.model.request.RequestMemberReason;
import com.dj.manager.model.request.RequestShopReason;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.RoundedEditTextView;
import com.google.gson.Gson;

public class ActionReasonActivity extends BaseActivity {
    private ActivityActionReasonBinding binding;
    private ReasonActionType actionType;
    private Manager manager;
    private String memberId, shopId;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityActionReasonBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(actionType.getTitle()), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        actionType = new Gson().fromJson(json, ReasonActionType.class);
        if (actionType == ReasonActionType.CLOSE_SHOP) {
            shopId = getIntent().getStringExtra("id");
        } else {
            memberId = getIntent().getStringExtra("id");
        }
    }

    private void setupUI() {
        binding.editTextReason.setInputFieldType(RoundedEditTextView.InputFieldType.MULTILINE_TEXT);
        binding.editTextReason.setHint("输入原因");
        binding.buttonSubmit.setOnClickListener(view -> {
            if (actionType == ReasonActionType.CLOSE_SHOP) {
                closeShop();
            } else if (actionType == ReasonActionType.BLOCK_USER) {
                blockMember();
            } else if (actionType == ReasonActionType.DELETE_USER) {
                deleteMember();
            }
        });
    }

    private void blockMember() {
        String reason = binding.editTextReason.getText();
        if (reason.isEmpty()) {
            binding.editTextReason.showError("");
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberReason request = new RequestMemberReason(manager.getManager_id(), memberId, reason);
        executeApiCall(this, apiService.submitBlockMemberReason(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                onBaseBackPressed();
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

    private void deleteMember() {
        String reason = binding.editTextReason.getText();
        if (reason.isEmpty()) {
            binding.editTextReason.showError("");
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberReason request = new RequestMemberReason(manager.getManager_id(), memberId, reason);
        executeApiCall(this, apiService.submitDeleteMemberReason(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                onBaseBackPressed();
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

    private void closeShop() {
        String reason = binding.editTextReason.getText();
        if (reason.isEmpty()) {
            binding.editTextReason.showError("");
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopReason request = new RequestShopReason(manager.getManager_id(), shopId, reason);
        executeApiCall(this, apiService.submitCloseShopReason(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                onBaseBackPressed();
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