package com.dj.user.activity.auth;

import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.text.Editable;
import android.text.Spannable;
import android.text.SpannableString;
import android.text.TextPaint;
import android.text.TextWatcher;
import android.text.method.LinkMovementMethod;
import android.text.style.ClickableSpan;
import android.text.style.ForegroundColorSpan;
import android.view.View;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.WebViewActivity;
import com.dj.user.databinding.ActivityRegisterBinding;
import com.dj.user.databinding.ViewEditTextPasswordBinding;
import com.dj.user.enums.OTPVerificationType;
import com.dj.user.model.request.RequestLoginRegister;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Country;
import com.dj.user.model.response.UserAgreement;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497WorkaroundBg;
import com.dj.user.widget.CountryBottomSheetDialogFragment;
import com.dj.user.widget.CustomTypefaceSpan;
import com.dj.user.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class RegisterActivity extends BaseActivity {
    private ActivityRegisterBinding binding;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding, viewEditTextConfirmPasswordBinding;
    private List<Country> countryList;
    private Country selectedCountry;
    private RoundedEditTextView phoneField;
    private EditText passwordField, confirmPasswordField;
    private String userAgreementUrl, userAgreementTitle;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityRegisterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);

        setupUI();
        setupFields();
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
        getUserAgreementUrl();

        if (binding.editTextInvitation.getText().isEmpty()) {
            String invitationCode = CacheManager.getString(this, CacheManager.KEY_INVITATION_CODE);
            binding.editTextInvitation.setText(!StringUtil.isNullOrEmpty(invitationCode) ? invitationCode : "");
        }
    }

    private void setupUI() {
        binding.imageViewBack.setOnClickListener(view -> onBaseBackPressed());
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
        binding.buttonRegister.setOnClickListener(view -> register());

        TextView tncTextView = binding.textViewTnc;
        String part1 = getString(R.string.register_tnc_desc);
        String part2 = getString(R.string.register_tnc);

        String fullText = part1 + " " + part2;

        Typeface typeface = ResourcesCompat.getFont(this, R.font.pingfang_sc_semi_bold);
        int color = ContextCompat.getColor(this, R.color.orange_F8AF07);
        if (typeface != null) {
            SpannableString spannable = new SpannableString(fullText);
            int start = fullText.indexOf(part2);
            int end = start + part2.length();

            ClickableSpan clickableSpan = new ClickableSpan() {
                @Override
                public void onClick(@NonNull View widget) {
                    if (!StringUtil.isNullOrEmpty(userAgreementUrl)) {
                        Bundle bundle = new Bundle();
                        bundle.putString("data", userAgreementUrl);
                        bundle.putString("title", userAgreementTitle);
                        startAppActivity(
                                new Intent(RegisterActivity.this, WebViewActivity.class),
                                bundle, false, false, true
                        );
                    }
                }

                @Override
                public void updateDrawState(@NonNull TextPaint ds) {
                    super.updateDrawState(ds);
                    ds.setColor(color);        // Keep color
                    ds.setUnderlineText(false); // Remove underline if you don't want it
                    ds.setTypeface(typeface);
                }
            };
            spannable.setSpan(clickableSpan, start, end, Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
            spannable.setSpan(new CustomTypefaceSpan(typeface), start, end, Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
            spannable.setSpan(new ForegroundColorSpan(color), start, end, Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);

            tncTextView.setText(spannable);
            tncTextView.setMovementMethod(LinkMovementMethod.getInstance());
            tncTextView.setHighlightColor(Color.TRANSPARENT);
        } else {
            tncTextView.setText(fullText);
        }
    }

    private void setupFields() {
        phoneField = binding.editTextPhone;
        phoneField.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
        phoneField.setHint(getString(R.string.register_hint_phone));

        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        viewEditTextConfirmPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextConfirmPassword.getRoot());
        passwordField = viewEditTextPasswordBinding.editTextPassword;
        confirmPasswordField = viewEditTextConfirmPasswordBinding.editTextPassword;

        passwordField.setHint(R.string.register_hint_password);
        passwordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable editable) {
                clearRegisterErrors();
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

        confirmPasswordField.setHint(R.string.register_hint_confirm_password);
        confirmPasswordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable editable) {
                clearRegisterErrors();
            }
        });
        viewEditTextConfirmPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                togglePasswordVisibility(confirmPasswordField, viewEditTextConfirmPasswordBinding.imageViewPasswordToggle)
        );

        binding.editTextInvitation.setHint(getString(R.string.register_hint_invitation_code));
        String invitationCode = CacheManager.getString(this, CacheManager.KEY_INVITATION_CODE);
        binding.editTextInvitation.setText(!StringUtil.isNullOrEmpty(invitationCode) ? invitationCode : "");
    }

    private void clearRegisterErrors() {
        phoneField.clearError();
        clearError(this, viewEditTextPasswordBinding.panelPassword);
        clearError(this, viewEditTextConfirmPasswordBinding.panelPassword);
    }

    private void getUserAgreementUrl() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getUserAgreementUrl(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<UserAgreement> response) {
                UserAgreement userAgreement = response.getData();
                userAgreementUrl = userAgreement.getUrl();
                userAgreementTitle = userAgreement.getTitle();
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

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getCountryList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase(selectedCountry != null ? selectedCountry.getCountry_code() : "mys"));
                }
                CacheManager.saveObject(RegisterActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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

    private void register() {
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String register = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
        String password = passwordField.getText().toString().trim();
        String confirmPassword = confirmPasswordField.getText().toString().trim();
        String invitationCode = binding.editTextInvitation.getText();

        boolean hasError = false;
        if (phone.isEmpty()) {
            phoneField.showError("");
            hasError = true;
        }
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
            Toast.makeText(this, R.string.register_password_mismatch, Toast.LENGTH_SHORT).show();
            return;
        }
        if (!StringUtil.isValidAlphanumeric(password)) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            showError(this, viewEditTextConfirmPasswordBinding.panelPassword);
            Toast.makeText(this, R.string.register_password_invalid, Toast.LENGTH_SHORT).show();
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestLoginRegister request = new RequestLoginRegister(register, password, "register",
                CacheManager.getString(this, CacheManager.KEY_AGENT_CODE), invitationCode,
                CacheManager.getString(this, CacheManager.KEY_SHOP_CODE)
        );
        executeApiCall(this, apiService.register(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    StringUtil.copyToClipboard(RegisterActivity.this, "", otp); // TODO: 12/09/2025 TESTING
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(OTPVerificationType.REGISTER));
                bundle.putString("request", new Gson().toJson(request));
                startAppActivity(new Intent(RegisterActivity.this, VerifyOTPActivity.class),
                        bundle, false, true, true
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
