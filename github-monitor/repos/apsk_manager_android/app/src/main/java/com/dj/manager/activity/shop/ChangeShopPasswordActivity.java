package com.dj.manager.activity.shop;

import android.os.Bundle;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityChangeShopPasswordBinding;
import com.dj.manager.databinding.ViewEditTextPasswordBinding;
import com.dj.manager.model.request.RequestUpdateShopPassword;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;

public class ChangeShopPasswordActivity extends BaseActivity {
    private ActivityChangeShopPasswordBinding binding;
    private ViewEditTextPasswordBinding newPasswordBinding, confirmNewPasswordBinding;
    private EditText newPasswordField, confirmNewPasswordField;
    private Manager manager;
    private Shop shop;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityChangeShopPasswordBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_change_password_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            shop = new Gson().fromJson(json, Shop.class);
        }
    }

    private void setupUI() {
        newPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextNewPassword.getRoot());
        newPasswordField = newPasswordBinding.editTextPassword;
        newPasswordField.setHint(R.string.shop_change_password_new_hint);
        newPasswordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(newPasswordField, newPasswordBinding.imageViewPasswordToggle));

        confirmNewPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextConfirmPassword.getRoot());
        confirmNewPasswordField = confirmNewPasswordBinding.editTextPassword;
        confirmNewPasswordField.setHint(R.string.shop_change_password_confirm_hint);
        confirmNewPasswordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(confirmNewPasswordField, confirmNewPasswordBinding.imageViewPasswordToggle));

        binding.buttonSubmit.setOnClickListener(view -> changePassword());
    }

    private void changePassword() {
        String newPassword = newPasswordField.getText().toString();
        String confirmPassword = confirmNewPasswordField.getText().toString();
        boolean hasError = false;
        if (newPassword.isEmpty()) {
            showError(this, newPasswordBinding.panelPassword);
            hasError = true;
        }
        if (confirmPassword.isEmpty()) {
            showError(this, confirmNewPasswordBinding.panelPassword);
            hasError = true;
        }
        if (hasError) return;
        if (!newPassword.equals(confirmPassword)) {
            showError(this, confirmNewPasswordBinding.panelPassword);
            Toast.makeText(this, R.string.shop_change_password_mismatch, Toast.LENGTH_SHORT).show();
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateShopPassword request = new RequestUpdateShopPassword(manager.getManager_id(), shop.getShop_id(), newPassword);
        executeApiCall(this, apiService.updateShopPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                shop = response.getData();
                CustomToast.showTopToast(ChangeShopPasswordActivity.this, getString(R.string.shop_change_password_updated));
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