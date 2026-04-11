package com.dj.user.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;
import android.os.CountDownTimer;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.KeyEvent;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityBindOtpBinding;
import com.dj.user.enums.ActionType;
import com.dj.user.model.request.RequestBindEmail;
import com.dj.user.model.request.RequestBindPhone;
import com.dj.user.model.request.RequestBindVerifyOTP;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;
import com.google.gson.Gson;

public class BindOTPActivity extends BaseActivity {

    private ActivityBindOtpBinding binding;
    private ActionType actionType;
    private RequestBindPhone bindPhoneRequest;
    private RequestBindEmail bindEmailRequest;
    private CountDownTimer countDownTimer;
    private static final long OTP_RESEND_INTERVAL = 300_000; // 300 seconds = 5 minutes
    private long timeLeft = OTP_RESEND_INTERVAL;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBindOtpBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.bind_otp_title), 0, null);
        setupUI();
        setupOTPInputs();
        if (savedInstanceState != null) {
            timeLeft = savedInstanceState.getLong("timeLeft", OTP_RESEND_INTERVAL);
        }
        startOtpCountdown(timeLeft);
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

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
            if (actionType == ActionType.BIND_PHONE) {
                json = getIntent().getStringExtra("request");
                bindPhoneRequest = new Gson().fromJson(json, RequestBindPhone.class);
                if (bindPhoneRequest != null) {
                    binding.textViewDesc.setText(String.format(getString(R.string.bind_otp_desc), FormatUtils.maskPhoneNumberLast4(bindPhoneRequest.getPhone())));
                }
            } else if (actionType == ActionType.BIND_EMAIL) {
                json = getIntent().getStringExtra("request");
                bindEmailRequest = new Gson().fromJson(json, RequestBindEmail.class);
                if (bindEmailRequest != null) {
                    binding.textViewDesc.setText(String.format(getString(R.string.bind_otp_desc), bindEmailRequest.getEmail()));
                }
            }
        }
    }

    private void setupUI() {
        binding.textViewCountDown.setOnClickListener(view -> {
            if (actionType == ActionType.BIND_PHONE) {
                requestBindPhone();
            } else if (actionType == ActionType.BIND_EMAIL) {
                requestBindEmail();
            }
        });
        binding.buttonVerify.setOnClickListener(view -> {
            if (actionType == ActionType.BIND_PHONE) {
                bindPhone();
            } else if (actionType == ActionType.BIND_EMAIL) {
                bindEmail();
            }
        });
    }

    private void setupOTPInputs() {
        setOTPWatcher(binding.editTextOtp1, null, binding.editTextOtp2);
        setOTPWatcher(binding.editTextOtp2, binding.editTextOtp1, binding.editTextOtp3);
        setOTPWatcher(binding.editTextOtp3, binding.editTextOtp2, binding.editTextOtp4);
        setOTPWatcher(binding.editTextOtp4, binding.editTextOtp3, binding.editTextOtp5);
        setOTPWatcher(binding.editTextOtp5, binding.editTextOtp4, binding.editTextOtp6);
        setOTPWatcher(binding.editTextOtp6, binding.editTextOtp5, null);
    }

    private void setOTPWatcher(EditText current, EditText previous, EditText next) {
        current.addTextChangedListener(new TextWatcher() {
            @Override
            public void afterTextChanged(Editable s) {
                if (s.length() == 1 && next != null) {
                    next.requestFocus();
                }
            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }
        });

        current.setOnKeyListener((v, keyCode, event) -> {
            if (event.getAction() == KeyEvent.ACTION_DOWN && keyCode == KeyEvent.KEYCODE_DEL) {
                if (current.getText().toString().isEmpty() && previous != null) {
                    previous.requestFocus();
                    previous.setSelection(previous.getText().length());
                    return true;
                }
            }
            return false;
        });
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
        if (bindPhoneRequest == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.requestBindPhone(bindPhoneRequest), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(BindOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
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
        String otp = binding.editTextOtp1.getText().toString().trim() +
                binding.editTextOtp2.getText().toString().trim() +
                binding.editTextOtp3.getText().toString().trim() +
                binding.editTextOtp4.getText().toString().trim() +
                binding.editTextOtp5.getText().toString().trim() +
                binding.editTextOtp6.getText().toString().trim();
        if (otp.length() < 6) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestBindVerifyOTP request = new RequestBindVerifyOTP(bindPhoneRequest.getMember_id(), otp);
        executeApiCall(this, apiService.bindPhone(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                CustomToast.showTopToast(BindOTPActivity.this, getString(R.string.bind_otp_success_phone));
                Intent intent = new Intent(BindOTPActivity.this, SecurityPrivacyActivity.class);
                intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
                startActivity(intent);
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

    private void requestBindEmail() {
        if (bindEmailRequest == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.requestBindEmail(bindEmailRequest), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(BindOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
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

    private void bindEmail() {
        if (bindEmailRequest == null) {
            return;
        }
        String otp = binding.editTextOtp1.getText().toString().trim() +
                binding.editTextOtp2.getText().toString().trim() +
                binding.editTextOtp3.getText().toString().trim() +
                binding.editTextOtp4.getText().toString().trim() +
                binding.editTextOtp5.getText().toString().trim() +
                binding.editTextOtp6.getText().toString().trim();
        if (otp.length() < 6) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestBindVerifyOTP request = new RequestBindVerifyOTP(bindEmailRequest.getMember_id(), otp);
        executeApiCall(this, apiService.bindEmail(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                CustomToast.showTopToast(BindOTPActivity.this, getString(R.string.bind_otp_success_email));
                Intent intent = new Intent(BindOTPActivity.this, SecurityPrivacyActivity.class);
                intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
                startActivity(intent);
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