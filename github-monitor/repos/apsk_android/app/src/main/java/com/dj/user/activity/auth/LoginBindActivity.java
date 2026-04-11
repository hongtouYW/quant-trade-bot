package com.dj.user.activity.auth;

import android.content.Intent;
import android.os.Bundle;
import android.os.CountDownTimer;
import android.text.Editable;
import android.text.TextWatcher;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.databinding.ActivityLoginBindBinding;
import com.dj.user.model.request.RequestBindPhone;
import com.dj.user.model.request.RequestRandomBindVerifyOTP;
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
import com.dj.user.widget.RoundedEditTextView;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class LoginBindActivity extends BaseActivity {
    private ActivityLoginBindBinding binding;
    private RoundedEditTextView phoneField;
    private EditText otpField;
    private String memberId;
    private RequestBindPhone bindPhoneRequest;
    private List<Country> countryList;
    private Country selectedCountry;
    private String lastPhone = "";
    private boolean isFirstSend = true;
    private CountDownTimer countDownTimer;
    private static final long OTP_RESEND_INTERVAL = 300_000; // 300 seconds = 5 minutes
    private long timeLeft = OTP_RESEND_INTERVAL;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLoginBindBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);

        parseIntentData();
        setupUsernameField();
        setupOtpField();
        setupUI();
        if (savedInstanceState != null) {
            timeLeft = savedInstanceState.getLong("timeLeft", OTP_RESEND_INTERVAL);
        }
    }

    private void parseIntentData() {
        memberId = getIntent().getStringExtra("data");
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        outState.putLong("timeLeft", timeLeft);
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
        phoneField.addTextChangedListener(new TextWatcher() {
            @Override
            public void afterTextChanged(Editable s) {
                String currentPhone = s.toString();
                if (!currentPhone.equals(lastPhone)) {
                    resetOtpState();
                }
                lastPhone = currentPhone;
            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {

            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {

            }
        });
    }

    private void setupOtpField() {
        otpField = binding.editTextOtp;
        otpField.addTextChangedListener(new TextWatcher() {
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
        binding.textViewCountDown.setText(R.string.login_bind_send);
        binding.textViewCountDown.setOnClickListener(v -> {
            if (isFirstSend) {
                isFirstSend = false;
            }
            requestBindPhone();
        });
    }

    private void clearLoginErrors() {
        phoneField.clearError();
        clearError(this, binding.panelOtp);
    }

    private void setupUI() {
        binding.imageViewBack.setOnClickListener(view -> {
            CacheManager.clearAll(this);
            onBaseBackPressed();
        });
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
        binding.buttonLoginBind.setOnClickListener(view -> bindPhone());
    }

    private void resetOtpState() {
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
        timeLeft = OTP_RESEND_INTERVAL;
        isFirstSend = true;
        binding.textViewCountDown.setText(R.string.login_bind_send);
        binding.textViewCountDown.setEnabled(true);
        bindPhoneRequest = null;
    }

    private void startOtpCountdown(long startTime) {
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
        countDownTimer = new CountDownTimer(startTime, 1000) {
            public void onTick(long millisUntilFinished) {
                long secondsLeft = millisUntilFinished / 1000;
                binding.textViewCountDown.setText(String.format(getString(R.string.bind_otp_resend_countdown), secondsLeft));
            }

            public void onFinish() {
                binding.textViewCountDown.setText(R.string.bind_otp_resend);
                binding.textViewCountDown.setEnabled(true);
            }
        };
        binding.textViewCountDown.setEnabled(false);
        countDownTimer.start();
    }

    private void requestBindPhone() {
        if (StringUtil.isNullOrEmpty(memberId)) {
            return;
        }
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String number = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
        if (phone.isEmpty()) {
            binding.editTextPhone.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        bindPhoneRequest = new RequestBindPhone(memberId, number);
        executeApiCall(this, apiService.requestRandomBindPhone(bindPhoneRequest), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(LoginBindActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
                }
                timeLeft = OTP_RESEND_INTERVAL;
                startOtpCountdown(timeLeft);
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

    private void bindPhone() {
        if (bindPhoneRequest == null) {
            return;
        }
        String otp = otpField.getText().toString().trim();
        if (otp.length() < 6) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestRandomBindVerifyOTP request = new RequestRandomBindVerifyOTP(bindPhoneRequest.getMember_id(), bindPhoneRequest.getPhone(), otp, SingletonUtil.getInstance().getFcmToken());
        executeApiCall(this, apiService.randomBindPhone(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(LoginBindActivity.this, CacheManager.KEY_USER_PROFILE, member);
                    startAppActivity(
                            new Intent(LoginBindActivity.this, DashboardActivity.class),
                            null, true, true, true
                    );
                }
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

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getCountryList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase(selectedCountry != null ? selectedCountry.getCountry_code() : "mys"));
                }
                CacheManager.saveObject(LoginBindActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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
