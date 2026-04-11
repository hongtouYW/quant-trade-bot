package com.dj.shop.activity.auth;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.dashboard.DashboardActivity;
import com.dj.shop.adapter.LanguageListViewAdapter;
import com.dj.shop.databinding.ActivityLoginBinding;
import com.dj.shop.databinding.ViewEditTextPasswordBinding;
import com.dj.shop.model.ItemLanguage;
import com.dj.shop.model.request.RequestLogin;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.SingletonUtil;
import com.dj.shop.widget.AndroidBug5497Workaround;
import com.dj.shop.widget.RoundedEditTextView;
import com.dj.shop.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;

public class LoginActivity extends BaseActivity {
    private ActivityLoginBinding binding;
    @NonNull
    protected RoundedEditTextView usernameField;
    @NonNull
    protected LinearLayout passwordPanel;
    private EditText passwordField;
    private ImageView passwordToggleImageView;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLoginBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);

        setupUsernameField();
        setupPasswordField();

        binding.buttonLogin.setTextColorRes(R.color.gold_D4AF37);
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
                        getString(R.string.profile_language_setting),
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
        ViewEditTextPasswordBinding viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordPanel = viewEditTextPasswordBinding.panelPassword;
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
        passwordToggleImageView = viewEditTextPasswordBinding.imageViewPasswordToggle;
        passwordToggleImageView.setOnClickListener(v ->
                togglePasswordVisibility(passwordField, passwordToggleImageView)
        );
    }

    protected void clearLoginErrors() {
        usernameField.clearError();
        clearError(this, passwordPanel);
    }

    private void login() {
        String username = usernameField.getText();
        String password = passwordField.getText().toString().trim();

        boolean hasError = false;
        if (username.isEmpty()) {
            usernameField.showError("");
            hasError = true;
        }
        if (password.isEmpty()) {
            showError(this, passwordPanel);
            hasError = true;
        }
        if (hasError) return;

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestLogin request = new RequestLogin(username, password, SingletonUtil.getInstance().getFcmToken());
        executeApiCall(this, apiService.login(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                Shop shop = response.getData();
                if (shop != null) {
                    CacheManager.saveObject(LoginActivity.this, CacheManager.KEY_SHOP_PROFILE, shop);
                }
                startAppActivity(
                        new Intent(LoginActivity.this, DashboardActivity.class),
                        null, true, true, true
                );
            }

            @Override
            public boolean onApiError(int code, String message) {
                if (code == 401) {
                    usernameField.showError(getString(R.string.login_invalid_credentials));
                    showError(LoginActivity.this, passwordPanel);
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
