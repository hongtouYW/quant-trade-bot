package com.dj.user.activity.auth;

import android.content.ClipData;
import android.content.ClipboardManager;
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
import com.dj.user.activity.DashboardActivity;
import com.dj.user.databinding.ActivityVerifyOtpBinding;
import com.dj.user.enums.OTPVerificationType;
import com.dj.user.model.request.RequestLoginRegister;
import com.dj.user.model.request.RequestReset;
import com.dj.user.model.request.RequestResetVerifyOTP;
import com.dj.user.model.request.RequestVerifyOTP;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.SingletonUtil;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497WorkaroundBg;
import com.google.gson.Gson;

public class VerifyOTPActivity extends BaseActivity {
    private ActivityVerifyOtpBinding binding;
    private OTPVerificationType otpVerificationType;
    private RequestLoginRegister requestLoginRegister;
    private RequestReset requestReset;
    private String phoneNumber;
    private String memberId;

    private CountDownTimer countDownTimer;
    private static final long OTP_RESEND_INTERVAL = 300_000; // 300 seconds = 5 minutes
    private long timeLeft = OTP_RESEND_INTERVAL;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityVerifyOtpBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);

        parseIntentData();
        setupUI();
        setupOTPInputs();
        if (savedInstanceState != null) {
            timeLeft = savedInstanceState.getLong("timeLeft", OTP_RESEND_INTERVAL);
        }
        startOtpCountdown(timeLeft);

        // TODO: 12/09/2025 TESTING
        binding.editTextOtp1.postDelayed(() -> {
            ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
            if (clipboard != null && clipboard.hasPrimaryClip()) {
                ClipData clipData = clipboard.getPrimaryClip();
                if (clipData != null && clipData.getItemCount() > 0) {
                    CharSequence clipText = clipData.getItemAt(0).getText();
                    if (clipText != null) {
                        String pastedText = clipText.toString().trim();
                        if (pastedText.length() == 6 && pastedText.matches("\\d{6}")) {
                            pasteOTPToFields(pastedText, new EditText[]{
                                    binding.editTextOtp1,
                                    binding.editTextOtp2,
                                    binding.editTextOtp3,
                                    binding.editTextOtp4,
                                    binding.editTextOtp5,
                                    binding.editTextOtp6,
                            });
                        }
                    }
                }
            }
        }, 300);
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
        otpVerificationType = new Gson().fromJson(json, OTPVerificationType.class);

        json = getIntent().getStringExtra("request");
        if (otpVerificationType == OTPVerificationType.LOGIN || otpVerificationType == OTPVerificationType.REGISTER) {
            requestLoginRegister = new Gson().fromJson(json, RequestLoginRegister.class);
            if (requestLoginRegister != null) {
                phoneNumber = requestLoginRegister.getPhone();
            }
        } else if (otpVerificationType == OTPVerificationType.RESET_PASSWORD) {
            requestReset = new Gson().fromJson(json, RequestReset.class);
            if (requestReset != null) {
                phoneNumber = requestReset.getPhone();
            }
            memberId = getIntent().getStringExtra("id");
        }
    }

    private void setupUI() {
        binding.textViewDesc.setText(String.format(getString(R.string.verify_otp_desc), FormatUtils.maskPhoneNumberLast4(phoneNumber)));
        binding.imageViewBack.setOnClickListener(view -> onBaseBackPressed());
        binding.textViewCountDown.setOnClickListener(v -> {
            if (binding.textViewCountDown.isEnabled()) {
                if (otpVerificationType == OTPVerificationType.LOGIN) {
                    resendLoginRequest();
                } else if (otpVerificationType == OTPVerificationType.REGISTER) {
                    resendRegisterRequest();
                } else if (otpVerificationType == OTPVerificationType.RESET_PASSWORD) {
                    resendResetRequest();
                }
            }
        });
        binding.buttonVerify.setOnClickListener(view -> checkAndSubmitOTP());
    }

    private void setupOTPInputs() {
//        setOTPWatcher(binding.editTextOtp1, null, binding.editTextOtp2);
//        setOTPWatcher(binding.editTextOtp2, binding.editTextOtp1, binding.editTextOtp3);
//        setOTPWatcher(binding.editTextOtp3, binding.editTextOtp2, binding.editTextOtp4);
//        setOTPWatcher(binding.editTextOtp4, binding.editTextOtp3, binding.editTextOtp5);
//        setOTPWatcher(binding.editTextOtp5, binding.editTextOtp4, binding.editTextOtp6);
//        setOTPWatcher(binding.editTextOtp6, binding.editTextOtp5, null);
        EditText[] otpFields = new EditText[]{
                binding.editTextOtp1,
                binding.editTextOtp2,
                binding.editTextOtp3,
                binding.editTextOtp4,
                binding.editTextOtp5,
                binding.editTextOtp6
        };
        setupOTPGroup(otpFields);
    }

//    private void setOTPWatcher(EditText current, EditText previous, EditText next) {
//        current.addTextChangedListener(new TextWatcher() {
//            @Override
//            public void afterTextChanged(Editable s) {
//                if (s.length() == 1 && next != null) {
//                    next.requestFocus();
//                }
//            }
//
//            @Override
//            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
//            }
//
//            @Override
//            public void onTextChanged(CharSequence s, int start, int before, int count) {
//            }
//        });
//
//        current.setOnKeyListener((v, keyCode, event) -> {
//            if (event.getAction() == KeyEvent.ACTION_DOWN && keyCode == KeyEvent.KEYCODE_DEL) {
//                if (current.getText().toString().isEmpty() && previous != null) {
//                    previous.requestFocus();
//                    previous.setSelection(previous.getText().length());
//                    return true;
//                }
//            }
//            return false;
//        });
//    }

    private void setupOTPGroup(EditText[] otpFields) {
        for (int i = 0; i < otpFields.length; i++) {
            EditText previous = i > 0 ? otpFields[i - 1] : null;
            EditText next = i < otpFields.length - 1 ? otpFields[i + 1] : null;
            setOTPWatcher(otpFields[i], previous, next, otpFields);
            otpFields[i].setOnFocusChangeListener((v, hasFocus) -> {
                if (hasFocus) {
                    EditText editText = (EditText) v;
                    editText.post(() -> {
                        editText.setSelection(editText.getText().length());
                    });
                }
            });
        }
    }

    private void setOTPWatcher(EditText current, EditText previous, EditText next, EditText[] otpFields) {
        current.addTextChangedListener(new TextWatcher() {
            private String beforeChange = "";

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
                beforeChange = s.toString();
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (s.length() > 1) {
                    // User pasted something in this field
                    String pasted = s.toString().replaceAll("\\D", ""); // keep only digits
                    if (pasted.length() == otpFields.length) {
                        pasteOTPToFields(pasted, otpFields); // reset all and paste from beginning
                    } else {
                        // If not full OTP, just keep first char
                        s.replace(0, s.length(), pasted.substring(0, 1));
                    }
                    return; // stop further auto-focus logic
                }
                if (s.length() == 1 && next != null) {
                    next.requestFocus();
                }
                // TODO: 12/09/2025 TEMP DISABLED
//                checkAndSubmitOTP();
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

    private void pasteOTPToFields(String otp, EditText[] otpFields) {
        if (otp == null || otp.length() != otpFields.length) return;
        for (EditText otpField : otpFields) {
            otpField.setText("");
        }
        for (int i = 0; i < otpFields.length; i++) {
            otpFields[i].setText(String.valueOf(otp.charAt(i)));
        }
        otpFields[otpFields.length - 1].requestFocus();
        otpFields[otpFields.length - 1].setSelection(otpFields[otpFields.length - 1].length());

        // TODO: 12/09/2025 TEMP DISABLED
//        checkAndSubmitOTP();
    }

    private void startOtpCountdown(long startTime) {
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
        countDownTimer = new CountDownTimer(startTime, 1000) {
            public void onTick(long millisUntilFinished) {
                long secondsLeft = millisUntilFinished / 1000;
                binding.textViewCountDown.setText(String.format(getString(R.string.verify_otp_count_down), secondsLeft));
            }

            public void onFinish() {
                binding.textViewCountDown.setText(R.string.verify_otp_resend);
                binding.textViewCountDown.setEnabled(true);
            }
        };
        binding.textViewCountDown.setEnabled(false);
        countDownTimer.start();
    }

    private void checkAndSubmitOTP() {
        String otp = binding.editTextOtp1.getText().toString().trim() +
                binding.editTextOtp2.getText().toString().trim() +
                binding.editTextOtp3.getText().toString().trim() +
                binding.editTextOtp4.getText().toString().trim() +
                binding.editTextOtp5.getText().toString().trim() +
                binding.editTextOtp6.getText().toString().trim();

        if (otp.length() == 6) {
            if (otpVerificationType == OTPVerificationType.LOGIN || otpVerificationType == OTPVerificationType.REGISTER) {
                loginRegisterVerifyOTP();
            } else if (otpVerificationType == OTPVerificationType.RESET_PASSWORD) {
                resetVerifyOTP();
            }
        }
    }

    private void loginRegisterVerifyOTP() {
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
        RequestVerifyOTP request = new RequestVerifyOTP(
                requestLoginRegister.getPhone(),
                requestLoginRegister.getPassword(), otp,
                "phone",
                requestLoginRegister.getModule(),
                requestLoginRegister.getModule().equalsIgnoreCase("login") ? null : CacheManager.getString(this, CacheManager.KEY_AGENT_CODE),
                requestLoginRegister.getInvitationCode(),
                requestLoginRegister.getModule().equalsIgnoreCase("login") ? null : CacheManager.getString(this, CacheManager.KEY_SHOP_CODE),
                SingletonUtil.getInstance().getFcmToken()
        );
        executeApiCall(this, apiService.loginRegisterVerifyOTP(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(VerifyOTPActivity.this, CacheManager.KEY_USER_PROFILE, member);
                }
                startAppActivity(
                        new Intent(VerifyOTPActivity.this, DashboardActivity.class),
                        null, true, true, true
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

    private void resendLoginRequest() {
        if (requestLoginRegister == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.login(requestLoginRegister), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(VerifyOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
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

    private void resendRegisterRequest() {
        if (requestLoginRegister == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.register(requestLoginRegister), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(VerifyOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
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

    private void resetVerifyOTP() {
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
        RequestResetVerifyOTP request = new RequestResetVerifyOTP(memberId, otp);
        executeApiCall(this, apiService.resetVerifyOTP(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(request));
                startAppActivity(new Intent(VerifyOTPActivity.this, ResetPasswordActivity.class),
                        bundle, false, false, true);
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

    private void resendResetRequest() {
        if (requestReset == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.requestReset(requestReset), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(VerifyOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
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
}
