package com.dj.user.activity.auth;

import android.content.Intent;
import android.graphics.Typeface;
import android.os.Bundle;
import android.text.Editable;
import android.text.Spannable;
import android.text.SpannableString;
import android.text.TextWatcher;
import android.text.style.ForegroundColorSpan;
import android.widget.EditText;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.databinding.ActivityLoginBinding;
import com.dj.user.databinding.ViewEditTextPasswordBinding;
import com.dj.user.enums.OTPVerificationType;
import com.dj.user.model.request.RequestLoginRegister;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Country;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.SingletonUtil;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497WorkaroundBg;
import com.dj.user.widget.CountryBottomSheetDialogFragment;
import com.dj.user.widget.CustomTypefaceSpan;
import com.dj.user.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class LoginActivity extends BaseActivity {
    private ActivityLoginBinding binding;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding;
    private RoundedEditTextView phoneField;
    private EditText passwordField;
    private List<Country> countryList;
    private Country selectedCountry;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLoginBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);

        setupUsernameField();
        setupPasswordField();
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        Country[] cached = CacheManager.getObject(this, CacheManager.KEY_COUNTRY_LIST, Country[].class);
        if (cached != null) {
            countryList = new ArrayList<>(Arrays.asList(cached));
        } else {
            getCountryList();
        }
    }

    private void setupUsernameField() {
        phoneField = binding.editTextPhone;
        phoneField.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
        phoneField.setHint(getString(R.string.login_hint_phone));
    }

    private void setupPasswordField() {
        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordField = viewEditTextPasswordBinding.editTextPassword;
        passwordField.setHint(R.string.login_hint_password);

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
        phoneField.clearError();
        clearError(this, viewEditTextPasswordBinding.panelPassword);
    }

    private void setupUI() {
        binding.panelCountryCode.setOnClickListener(view -> {
            if (countryList == null) {
                return;
            }
            CountryBottomSheetDialogFragment.newInstance(new ArrayList<>(countryList), (country, pos) -> {
                selectedCountry = country;
                binding.textViewCountryCode.setText(String.format(getString(R.string.template_plus_s), country.getPhone_code()));
            }).show(getSupportFragmentManager(), "CountryBottomSheet");
        });
        binding.textViewCountryCode.setText(R.string.country_code_mys);
        binding.textViewForgotPassword.setOnClickListener(view ->
                startAppActivity(new Intent(LoginActivity.this, RequestResetActivity.class),
                        null, false, false, true
                ));
        binding.buttonLogin.setOnClickListener(view -> login());

        TextView registerTextView = binding.textViewRegister;
        String part1 = getString(R.string.login_no_account);
        String part2 = getString(R.string.login_register);

        String fullText = part1 + " " + part2;

        Typeface typeface = ResourcesCompat.getFont(this, R.font.pingfang_sc_semi_bold);
        int color = ContextCompat.getColor(this, R.color.orange_F8AF07);
        if (typeface != null) {
            SpannableString spannable = new SpannableString(fullText);
            int start = fullText.indexOf(part2);
            int end = start + part2.length();

            spannable.setSpan(new CustomTypefaceSpan(typeface),
                    start, end,
                    Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
            spannable.setSpan(new ForegroundColorSpan(color),
                    start, end,
                    Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
            registerTextView.setText(spannable);
        } else {
            registerTextView.setText(fullText);
        }
        registerTextView.setOnClickListener(view ->
                startAppActivity(new Intent(LoginActivity.this, RegisterActivity.class),
                        null, false, false, true
                ));
    }

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getCountryList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase(selectedCountry != null ? selectedCountry.getCountry_code() : "mys"));
                }
                CacheManager.saveObject(LoginActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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

    private void login() {
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String login = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
        String password = passwordField.getText().toString().trim();

        if (phone.isEmpty() && password.isEmpty()) {
            phoneField.showError("");
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        if (phone.isEmpty()) {
            phoneField.showError("");
            return;
        }
        if (password.isEmpty()) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestLoginRegister request = new RequestLoginRegister(login, password, "login", SingletonUtil.getInstance().getFcmToken()); // CacheManager.getString(this, CacheManager.KEY_AGENT_CODE));
        executeApiCall(this, apiService.login(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                if (response.isNeedbinding()) {
                    CacheManager.saveBoolean(LoginActivity.this, CacheManager.KEY_NEED_BINDING, true);
                    Bundle bundle = new Bundle();
                    bundle.putString("data", response.getMember_id());
                    startAppActivity(
                            new Intent(LoginActivity.this, LoginBindActivity.class),
                            bundle, false, false, true
                    );
                    return;
                }
                Member member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(LoginActivity.this, CacheManager.KEY_USER_PROFILE, member);
                    startAppActivity(
                            new Intent(LoginActivity.this, DashboardActivity.class),
                            null, true, true, true
                    );
                    return;
                }
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    StringUtil.copyToClipboard(LoginActivity.this, "", otp); // TODO: 12/09/2025 TESTING
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(OTPVerificationType.LOGIN));
                bundle.putString("request", new Gson().toJson(request));
                startAppActivity(new Intent(LoginActivity.this, VerifyOTPActivity.class),
                        bundle, false, true, true
                );
            }

            @Override
            public boolean onApiError(int code, String message) {
                if (code == 401) {
                    phoneField.showError(getString(R.string.login_invalid_credentials));
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
