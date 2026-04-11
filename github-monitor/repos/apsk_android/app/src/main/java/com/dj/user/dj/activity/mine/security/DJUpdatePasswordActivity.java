package com.dj.user.dj.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.DjActivityUpdatePasswordBinding;
import com.dj.user.databinding.ViewEditTextPasswordBinding;
import com.dj.user.model.request.RequestChangePassword;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.PasswordUtils;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.List;

public class DJUpdatePasswordActivity extends BaseActivity {

    private DjActivityUpdatePasswordBinding binding;
    private ViewEditTextPasswordBinding viewEditTextOldPasswordBinding;
    private ViewEditTextPasswordBinding viewEditTextNewPasswordBinding;
    private ViewEditTextPasswordBinding viewEditTextConfirmPasswordBinding;
    private Member member;
    private EditText passwordField, newPasswordField, confirmNewPasswordField;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityUpdatePasswordBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.change_password_title), 0, null);
        setupUI();
    }

    private void setupUI() {
        viewEditTextOldPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextOldPassword.getRoot());
        viewEditTextNewPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextNewPassword.getRoot());
        viewEditTextConfirmPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextConfirmPassword.getRoot());

        viewEditTextOldPasswordBinding.panelPassword.setBackgroundResource(R.drawable.bg_edit_text_transparent);
        viewEditTextOldPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(passwordField, viewEditTextOldPasswordBinding.imageViewPasswordToggle)
        );
        passwordField = viewEditTextOldPasswordBinding.editTextPassword;
        passwordField.setHint(R.string.change_password_hint_old_password);

        viewEditTextNewPasswordBinding.panelPassword.setBackgroundResource(R.drawable.bg_edit_text_transparent);
        viewEditTextNewPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(newPasswordField, viewEditTextNewPasswordBinding.imageViewPasswordToggle)
        );
        newPasswordField = viewEditTextNewPasswordBinding.editTextPassword;
        newPasswordField.setHint(R.string.change_password_hint_new_password);

        viewEditTextConfirmPasswordBinding.panelPassword.setBackgroundResource(R.drawable.bg_edit_text_transparent);
        viewEditTextConfirmPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(confirmNewPasswordField, viewEditTextConfirmPasswordBinding.imageViewPasswordToggle)
        );
        confirmNewPasswordField = viewEditTextConfirmPasswordBinding.editTextPassword;
        confirmNewPasswordField.setHint(R.string.change_password_hint_confirm_password);

        List<View> passwordStrengthBar = new ArrayList<>();
        passwordStrengthBar.add(binding.viewBar1);
        passwordStrengthBar.add(binding.viewBar2);
        passwordStrengthBar.add(binding.viewBar3);
        passwordStrengthBar.add(binding.viewBar4);

        newPasswordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                int strength = PasswordUtils.getPasswordStrengthLevel(s.toString());

                for (int i = 0; i < passwordStrengthBar.size(); i++) {
                    View bar = passwordStrengthBar.get(i);
                    if (i < strength) {
                        bar.setBackgroundResource(R.drawable.bg_password_strength_active);
                    } else {
                        bar.setBackgroundResource(R.drawable.bg_password_strength);
                    }
                }
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });

        binding.buttonNext.setOnClickListener(view -> resetPassword());
    }

    private void resetPassword() {
        if (member == null) {
            return;
        }
        String password = passwordField.getText().toString().trim();
        String newPassword = newPasswordField.getText().toString().trim();
        String confirmPassword = confirmNewPasswordField.getText().toString().trim();

        boolean hasError = false;
        if (password.isEmpty()) {
            showErrorTransparent(this, viewEditTextOldPasswordBinding.panelPassword);
            hasError = true;
        }
        if (newPassword.isEmpty()) {
            showErrorTransparent(this, viewEditTextNewPasswordBinding.panelPassword);
            hasError = true;
        }
        if (confirmPassword.isEmpty()) {
            showErrorTransparent(this, viewEditTextConfirmPasswordBinding.panelPassword);
            hasError = true;
        }
        if (hasError) return;
        if (!newPassword.equals(confirmPassword)) {
            showErrorTransparent(this, viewEditTextConfirmPasswordBinding.panelPassword);
            Toast.makeText(this, R.string.change_password_mismatch, Toast.LENGTH_SHORT).show();
            return;
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestChangePassword request = new RequestChangePassword(member.getMember_id(), password, newPassword);
        executeApiCall(this, apiService.changePassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(request));
                startAppActivity(new Intent(DJUpdatePasswordActivity.this, DJUpdatePasswordOTPActivity.class),
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