package com.dj.manager.activity;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.widget.EditText;

import com.dj.manager.R;
import com.dj.manager.activity.dashboard.DashboardActivity;
import com.dj.manager.adapter.LanguageListViewAdapter;
import com.dj.manager.databinding.ActivityLoginBinding;
import com.dj.manager.databinding.ViewEditTextPasswordBinding;
import com.dj.manager.model.ItemLanguage;
import com.dj.manager.model.request.RequestLogin;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.SingletonUtil;
import com.dj.manager.widget.AndroidBug5497Workaround;
import com.dj.manager.widget.RoundedEditTextView;
import com.dj.manager.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;

public class LoginActivity extends BaseActivity {

    private ActivityLoginBinding binding;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding;

    private RoundedEditTextView usernameField;
    private EditText passwordField;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLoginBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);

        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
        setupUI();
        setupUsernameField();
        setupPasswordField();
    }

    private void setupUI() {
        binding.textViewForgotPassword.setOnClickListener(view ->
                showCustomConfirmationDialog(this,
                        getString(R.string.forgot_password_title),
                        getString(R.string.forgot_password_desc),
                        getString(R.string.forgot_password_okay), this::dismissCustomConfirmationDialog));
        binding.buttonLogin.setOnClickListener(view -> login());

        String currentLanguage = getCurrentLanguage(this);
        binding.textViewLanguage.setText(getString(
                currentLanguage.equalsIgnoreCase("en") ? R.string.language_english :
                        currentLanguage.equalsIgnoreCase("ms") ? R.string.language_malay :
                                R.string.language_chinese)
        );
        ArrayList<ItemLanguage> languageList = new ArrayList<>();
        languageList.add(new ItemLanguage(1, getString(R.string.language_chinese), "zh"));
        languageList.add(new ItemLanguage(2, getString(R.string.language_english), "en"));
        languageList.add(new ItemLanguage(3, getString(R.string.language_malay), "ms"));
        for (ItemLanguage itemLanguage : languageList) {
            itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(currentLanguage));
        }
        LanguageListViewAdapter languageListViewAdapter = new LanguageListViewAdapter(this);
        binding.panelLanguage.setOnClickListener(view ->
                SelectionBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_language_settings),
                        false,
                        languageList,
                        languageListViewAdapter,
                        (language, pos) -> {
                            setLocale(language.getCode());
                            for (ItemLanguage itemLanguage : languageList) {
                                itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(language.getCode()));
                            }
                        },
                        ItemLanguage.class).show(getSupportFragmentManager(), "LanguageSheet")
        );
    }

    private void setupUsernameField() {
        usernameField = binding.editTextUsername;
        usernameField.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        usernameField.setHint(getString(R.string.login_hint_username));
    }

    private void setupPasswordField() {
        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordField = viewEditTextPasswordBinding.editTextPassword;
        passwordField.setHint(getString(R.string.login_hint_password));

        passwordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable editable) {
                clearLoginErrors();
            }
        });

        viewEditTextPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(passwordField, viewEditTextPasswordBinding.imageViewPasswordToggle)
        );
    }

    private void clearLoginErrors() {
        usernameField.clearError();
        clearError(this, viewEditTextPasswordBinding.panelPassword);
    }

    private void login() {
        String username = usernameField.getText();
        String password = passwordField.getText().toString().trim();

        if (username.isEmpty() && password.isEmpty()) {
            usernameField.showError("");
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        if (username.isEmpty()) {
            usernameField.showError("");
            return;
        }
        if (password.isEmpty()) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }

        showLoading(true);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestLogin request = new RequestLogin(username, password, SingletonUtil.getInstance().getFcmToken());
        executeApiCall(this, apiService.login(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Manager> response) {
                Manager manager = response.getData();
                if (manager != null) {
                    CacheManager.saveObject(LoginActivity.this, CacheManager.KEY_MANAGER_PROFILE, manager);
                }
                startAppActivity(
                        new Intent(LoginActivity.this, DashboardActivity.class),
                        null, true, true, false, true
                );
            }

            @Override
            public boolean onApiError(int code, String message) {
                if (code == 401) {
                    usernameField.showError(getString(R.string.login_invalid_credentials));
                    showError(LoginActivity.this, viewEditTextPasswordBinding.panelPassword);
                    return true;
                }
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}