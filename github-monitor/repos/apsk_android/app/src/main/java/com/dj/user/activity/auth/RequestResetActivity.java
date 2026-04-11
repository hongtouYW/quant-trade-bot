package com.dj.user.activity.auth;

import android.content.Intent;
import android.os.Bundle;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityRequestResetBinding;
import com.dj.user.enums.OTPVerificationType;
import com.dj.user.model.request.RequestReset;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Country;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497WorkaroundBg;
import com.dj.user.widget.CountryBottomSheetDialogFragment;
import com.dj.user.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class RequestResetActivity extends BaseActivity {
    private ActivityRequestResetBinding binding;
    private List<Country> countryList;
    private Country selectedCountry;
    private RoundedEditTextView phoneField;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityRequestResetBinding.inflate(getLayoutInflater());
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
        binding.buttonVerify.setOnClickListener(view -> requestReset());
    }

    private void setupFields() {
        phoneField = binding.editTextPhone;
        phoneField.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
        phoneField.setHint(getString(R.string.reset_request_hint_phone));
    }

    private void clearVerifyErrors() {
        phoneField.clearError();
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
                CacheManager.saveObject(RequestResetActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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

    private void requestReset() {
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String reset = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);

        boolean hasError = false;
        if (phone.isEmpty()) {
            phoneField.showError("");
            hasError = true;
        }
        if (hasError) return;

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestReset request = new RequestReset(reset);
        executeApiCall(this, apiService.requestReset(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    StringUtil.copyToClipboard(RequestResetActivity.this, "", otp); // TODO: 12/09/2025 TESTING
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(OTPVerificationType.RESET_PASSWORD));
                bundle.putString("request", new Gson().toJson(request));
                bundle.putString("id", response.getMember_id());
                startAppActivity(new Intent(RequestResetActivity.this, VerifyOTPActivity.class),
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
