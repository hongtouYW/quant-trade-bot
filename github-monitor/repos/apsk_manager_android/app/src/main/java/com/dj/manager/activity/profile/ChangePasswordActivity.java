package com.dj.manager.activity.profile;

import android.os.Bundle;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityChangePasswordBinding;
import com.dj.manager.databinding.ViewEditTextPasswordBinding;
import com.dj.manager.model.request.RequestChangePassword;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.CustomToast;

public class ChangePasswordActivity extends BaseActivity {
    private ActivityChangePasswordBinding binding;
    private ViewEditTextPasswordBinding passwordBinding, newPasswordBinding, confirmNewPasswordBinding;
    private EditText passwordField, newPasswordField, confirmNewPasswordField;
    private Manager manager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityChangePasswordBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.change_password_title), 0, null);
        setupUI();
    }

    private void setupUI() {
        passwordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordField = passwordBinding.editTextPassword;
        passwordField.setHint(R.string.change_password_hint_old);
        passwordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(passwordField, passwordBinding.imageViewPasswordToggle));

        newPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextNewPassword.getRoot());
        newPasswordField = newPasswordBinding.editTextPassword;
        newPasswordField.setHint(R.string.change_password_hint_new);
        newPasswordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(newPasswordField, newPasswordBinding.imageViewPasswordToggle));

        confirmNewPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextConfirmPassword.getRoot());
        confirmNewPasswordField = confirmNewPasswordBinding.editTextPassword;
        confirmNewPasswordField.setHint(R.string.change_password_hint_confirm);
        confirmNewPasswordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(confirmNewPasswordField, confirmNewPasswordBinding.imageViewPasswordToggle));

        binding.buttonSubmit.setOnClickListener(view -> changePassword());
    }

    private void changePassword() {
        clearError(this, passwordBinding.panelPassword);
        clearError(this, newPasswordBinding.panelPassword);
        clearError(this, confirmNewPasswordBinding.panelPassword);

        String oldPassword = passwordField.getText().toString();
        String newPassword = newPasswordField.getText().toString();
        String confirmPassword = confirmNewPasswordField.getText().toString();
        boolean hasError = false;
        if (oldPassword.isEmpty()) {
            showError(this, passwordBinding.panelPassword);
            hasError = true;
        }
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
            Toast.makeText(this, R.string.change_password_mismatch, Toast.LENGTH_SHORT).show();
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestChangePassword request = new RequestChangePassword(manager.getManager_id(), oldPassword, newPassword);
        executeApiCall(this, apiService.changePassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Manager> response) {
                manager = response.getData();
                CustomToast.showTopToast(ChangePasswordActivity.this, getString(R.string.change_password_success));
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