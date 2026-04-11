package com.dj.user.activity.auth;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityResetPasswordBinding;
import com.dj.user.databinding.ViewEditTextPasswordBinding;
import com.dj.user.model.request.RequestResetPassword;
import com.dj.user.model.request.RequestResetVerifyOTP;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497WorkaroundBg;
import com.dj.user.widget.CustomConfirmationDialog;
import com.google.gson.Gson;

public class ResetPasswordActivity extends BaseActivity {
    private ActivityResetPasswordBinding binding;
    private RequestResetVerifyOTP requestResetVerifyOTP;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding, viewEditTextConfirmPasswordBinding;
    private EditText passwordField, confirmPasswordField;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityResetPasswordBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);

        parseIntentData();
        setupUI();
        setupFields();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            requestResetVerifyOTP = new Gson().fromJson(json, RequestResetVerifyOTP.class);
        }
    }

    private void setupUI() {
        binding.imageViewBack.setOnClickListener(view -> onBaseBackPressed());
        binding.buttonChangePassword.setOnClickListener(view -> resetPassword());
    }

    private void setupFields() {
        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        viewEditTextConfirmPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextConfirmPassword.getRoot());
        passwordField = viewEditTextPasswordBinding.editTextPassword;
        confirmPasswordField = viewEditTextConfirmPasswordBinding.editTextPassword;

        passwordField.setHint(R.string.reset_password_hint_new_password);
        passwordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable editable) {
                clearChangePasswordErrors();
                if (StringUtil.isValidAlphanumeric(editable.toString())) {
                    binding.imageViewPasswordRequirement.setImageResource(R.drawable.ic_check_selected);
                } else {
                    binding.imageViewPasswordRequirement.setImageResource(R.drawable.ic_check);
                }
            }
        });
        viewEditTextPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(passwordField, viewEditTextPasswordBinding.imageViewPasswordToggle)
        );

        confirmPasswordField.setHint(R.string.reset_password_hint_confirm_password);
        confirmPasswordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable editable) {
                clearChangePasswordErrors();
            }
        });
        viewEditTextConfirmPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(confirmPasswordField, viewEditTextConfirmPasswordBinding.imageViewPasswordToggle)
        );
    }

    private void clearChangePasswordErrors() {
        clearError(this, viewEditTextPasswordBinding.panelPassword);
        clearError(this, viewEditTextConfirmPasswordBinding.panelPassword);
    }

    private void resetPassword() {
        if (requestResetVerifyOTP == null) {
            return;
        }
        String password = passwordField.getText().toString().trim();
        String confirmPassword = confirmPasswordField.getText().toString().trim();

        boolean hasError = false;
        if (password.isEmpty()) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            hasError = true;
        }
        if (confirmPassword.isEmpty()) {
            showError(this, viewEditTextConfirmPasswordBinding.panelPassword);
            hasError = true;
        }
        if (hasError) return;
        if (!password.equals(confirmPassword)) {
            showError(this, viewEditTextConfirmPasswordBinding.panelPassword);
            Toast.makeText(this, R.string.reset_password_mismatch, Toast.LENGTH_SHORT).show();
            return;
        }
        if (!StringUtil.isValidAlphanumeric(password)) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            showError(this, viewEditTextConfirmPasswordBinding.panelPassword);
            Toast.makeText(this, R.string.reset_password_invalid, Toast.LENGTH_SHORT).show();
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestResetPassword request = new RequestResetPassword(requestResetVerifyOTP.getMember_id(), password);
        executeApiCall(this, apiService.resetPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                showCustomConfirmationDialog(
                        ResetPasswordActivity.this,
                        getString(R.string.reset_password_success_title),
                        getString(R.string.reset_password_success_desc), "", "",
                        getString(R.string.reset_password_success_okay),
                        new CustomConfirmationDialog.OnButtonClickListener() {
                            @Override
                            public void onPositiveButtonClicked() {
                                startAppActivity(new Intent(ResetPasswordActivity.this, LoginActivity.class),
                                        null, true, true, true);
                            }

                            @Override
                            public void onNegativeButtonClicked() {

                            }
                        }
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
