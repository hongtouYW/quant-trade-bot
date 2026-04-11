package com.dj.user.activity.mine.security;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Intent;
import android.os.Bundle;
import android.os.CountDownTimer;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.KeyEvent;
import android.view.View;
import android.widget.EditText;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityUpdatePasswordOtpBinding;
import com.dj.user.enums.OTPVerificationChannel;
import com.dj.user.model.request.RequestChangeOTP;
import com.dj.user.model.request.RequestChangePassword;
import com.dj.user.model.request.RequestChangeVerifyOTP;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;
import com.google.gson.Gson;

public class UpdatePasswordOTPActivity extends BaseActivity {

    private ActivityUpdatePasswordOtpBinding binding;
    private RequestChangePassword requestChangePassword;
    private Member member;

    private CountDownTimer phoneCountDownTimer, emailCountDownTimer;
    private static final long OTP_RESEND_INTERVAL = 300_000; // 300 seconds = 5 minutes
    private long phoneTimeLeft = OTP_RESEND_INTERVAL;
    private long emailTimeLeft = OTP_RESEND_INTERVAL;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUpdatePasswordOtpBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.change_password_otp_title), 0, null);
        setupOTPInputs();

        if (savedInstanceState != null) {
            phoneTimeLeft = savedInstanceState.getLong("phoneTimeLeft", OTP_RESEND_INTERVAL);
            startPhoneOtpCountdown(phoneTimeLeft);
            emailTimeLeft = savedInstanceState.getLong("emailTimeLeft", OTP_RESEND_INTERVAL);
            startEmailOtpCountdown(emailTimeLeft);
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (phoneCountDownTimer != null) {
            phoneCountDownTimer.cancel();
        }
        if (emailCountDownTimer != null) {
            emailCountDownTimer.cancel();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        outState.putLong("phoneTimeLeft", phoneTimeLeft);
        outState.putLong("emailTimeLeft", emailTimeLeft);
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            requestChangePassword = new Gson().fromJson(json, RequestChangePassword.class);
            getProfile();
        }
    }

    private void setupUI(Member member) {
        this.member = member;
        if (member.isPhoneBinded()) {
            binding.panelPhone.setVisibility(View.VISIBLE);
            binding.textViewPhoneDesc.setText(String.format(getString(R.string.change_password_otp_phone_desc), FormatUtils.maskPhoneNumberLast4(member.getPhone())));
            binding.textViewCountDownPhone.setOnClickListener(view -> requestOTP(OTPVerificationChannel.PHONE));
        } else {
            binding.panelPhone.setVisibility(View.GONE);
        }
        if (member.isEmailBinded()) {
            binding.panelEmail.setVisibility(View.VISIBLE);
            binding.textViewEmailDesc.setText(String.format(getString(R.string.change_password_otp_email_desc), FormatUtils.maskEmail(member.getEmail())));
            binding.textViewCountDownEmail.setOnClickListener(view -> requestOTP(OTPVerificationChannel.EMAIL));
        } else {
            binding.panelEmail.setVisibility(View.GONE);
        }
        if (member.isGoogleAuthenticatorBinded()) {
            binding.panelGoogle.setVisibility(View.VISIBLE);
            binding.textViewPaste.setOnClickListener(view -> {
                ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
                if (clipboard != null && clipboard.hasPrimaryClip()) {
                    ClipData clipData = clipboard.getPrimaryClip();
                    if (clipData != null && clipData.getItemCount() > 0) {
                        CharSequence clipText = clipData.getItemAt(0).getText();
                        if (clipText != null) {
                            String pastedText = clipText.toString().trim();
                            if (pastedText.length() == 6 && pastedText.matches("\\d{6}")) {
                                pasteOTPToFields(pastedText, new EditText[]{
                                        binding.editTextOtpGoogle1,
                                        binding.editTextOtpGoogle2,
                                        binding.editTextOtpGoogle3,
                                        binding.editTextOtpGoogle4,
                                        binding.editTextOtpGoogle5,
                                        binding.editTextOtpGoogle6
                                });
                            }
                        }
                    }
                }
            });
        } else {
            binding.panelGoogle.setVisibility(View.GONE);
        }
        binding.buttonVerify.setOnClickListener(view -> verifyOtpSequence());
    }

    private void setupOTPInputs() {
        EditText[] phoneOtpFields = new EditText[]{
                binding.editTextOtp1,
                binding.editTextOtp2,
                binding.editTextOtp3,
                binding.editTextOtp4,
                binding.editTextOtp5,
                binding.editTextOtp6
        };
        setupOTPGroup(phoneOtpFields, "PHONE");
        EditText[] emailOtpFields = new EditText[]{
                binding.editTextOtpEmail1,
                binding.editTextOtpEmail2,
                binding.editTextOtpEmail3,
                binding.editTextOtpEmail4,
                binding.editTextOtpEmail5,
                binding.editTextOtpEmail6
        };
        setupOTPGroup(emailOtpFields, "EMAIL");
        EditText[] googleOtpFields = new EditText[]{
                binding.editTextOtpGoogle1,
                binding.editTextOtpGoogle2,
                binding.editTextOtpGoogle3,
                binding.editTextOtpGoogle4,
                binding.editTextOtpGoogle5,
                binding.editTextOtpGoogle6
        };
        setupOTPGroup(googleOtpFields, "GOOGLE");
    }

    private void setupOTPGroup(EditText[] otpFields, String type) {
        for (int i = 0; i < otpFields.length; i++) {
            otpFields[i].setTag(type);
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
    }

    private void startPhoneOtpCountdown(long startTime) {
        if (phoneCountDownTimer != null) {
            phoneCountDownTimer.cancel();
        }
        phoneCountDownTimer = new CountDownTimer(startTime, 1000) {
            public void onTick(long millisUntilFinished) {
                long secondsLeft = millisUntilFinished / 1000;
                binding.textViewCountDownPhone.setText(String.format(getString(R.string.change_password_otp_resend_countdown), secondsLeft));
            }

            public void onFinish() {
                binding.textViewCountDownPhone.setText(R.string.change_password_otp_resend);
                binding.textViewCountDownPhone.setEnabled(true);
            }
        };
        binding.textViewCountDownPhone.setEnabled(false);
        phoneCountDownTimer.start();
    }

    private void startEmailOtpCountdown(long startTime) {
        if (emailCountDownTimer != null) {
            emailCountDownTimer.cancel();
        }
        emailCountDownTimer = new CountDownTimer(startTime, 1000) {
            public void onTick(long millisUntilFinished) {
                long secondsLeft = millisUntilFinished / 1000;
                binding.textViewCountDownEmail.setText(String.format(getString(R.string.change_password_otp_resend_countdown), secondsLeft));
            }

            public void onFinish() {
                binding.textViewCountDownEmail.setText(getString(R.string.change_password_otp_resend));
                binding.textViewCountDownEmail.setEnabled(true);
            }
        };
        binding.textViewCountDownEmail.setEnabled(false);
        emailCountDownTimer.start();
    }

    private void getProfile() {
        if (requestChangePassword == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(requestChangePassword.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member member = response.getData();
                setupUI(member);
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

    private void requestOTP(OTPVerificationChannel channel) {
        if (requestChangePassword == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestChangeOTP request = new RequestChangeOTP(requestChangePassword.getMember_id(), channel.getValue());
        executeApiCall(this, apiService.changePasswordRequestOTP(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(UpdatePasswordOTPActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
                }
                if (channel == OTPVerificationChannel.PHONE) {
                    phoneTimeLeft = OTP_RESEND_INTERVAL;
                    startPhoneOtpCountdown(phoneTimeLeft);
                } else if (channel == OTPVerificationChannel.EMAIL) {
                    startEmailOtpCountdown(emailTimeLeft);
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

    private void verifyOtpSequence() {
        if (member == null || requestChangePassword == null) return;
        if (member.isPhoneBinded()) {
            verifyOtpChannel(OTPVerificationChannel.PHONE, () -> {
                if (member.isEmailBinded()) {
                    verifyOtpChannel(OTPVerificationChannel.EMAIL, () -> {
                        if (member.isGoogleAuthenticatorBinded()) {
                            verifyOtpChannel(OTPVerificationChannel.GOOGLE, this::gotoNextScreen);
                        } else {
                            gotoNextScreen();
                        }
                    });
                } else if (member.isGoogleAuthenticatorBinded()) {
                    verifyOtpChannel(OTPVerificationChannel.GOOGLE, this::gotoNextScreen);
                } else {
                    gotoNextScreen();
                }
            });
        } else if (member.isEmailBinded()) {
            verifyOtpChannel(OTPVerificationChannel.EMAIL, () -> {
                if (member.isGoogleAuthenticatorBinded()) {
                    verifyOtpChannel(OTPVerificationChannel.GOOGLE, this::gotoNextScreen);
                } else {
                    gotoNextScreen();
                }
            });
        } else if (member.isGoogleAuthenticatorBinded()) {
            verifyOtpChannel(OTPVerificationChannel.GOOGLE, this::gotoNextScreen);
        } else {
            gotoNextScreen(); // fallback: no channel
        }
    }

    private String getOtpFromChannel(OTPVerificationChannel channel) {
        EditText[] fields;
        switch (channel) {
            case PHONE:
                fields = new EditText[]{
                        binding.editTextOtp1, binding.editTextOtp2, binding.editTextOtp3,
                        binding.editTextOtp4, binding.editTextOtp5, binding.editTextOtp6
                };
                break;
            case EMAIL:
                fields = new EditText[]{
                        binding.editTextOtpEmail1, binding.editTextOtpEmail2, binding.editTextOtpEmail3,
                        binding.editTextOtpEmail4, binding.editTextOtpEmail5, binding.editTextOtpEmail6
                };
                break;
            case GOOGLE:
                fields = new EditText[]{
                        binding.editTextOtpGoogle1, binding.editTextOtpGoogle2, binding.editTextOtpGoogle3,
                        binding.editTextOtpGoogle4, binding.editTextOtpGoogle5, binding.editTextOtpGoogle6
                };
                break;
            default:
                return null;
        }
        StringBuilder sb = new StringBuilder();
        for (EditText f : fields) {
            sb.append(f.getText().toString().trim());
        }
        return sb.toString();
    }

    private void gotoNextScreen() {
        CustomToast.showTopToast(this, getString(R.string.change_password_otp_success));
        Intent intent = new Intent(this, SecurityPrivacyActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        startActivity(intent);
        finish();
    }

    private void verifyOtpChannel(OTPVerificationChannel channel, Runnable onSuccessNext) {
        String otp = getOtpFromChannel(channel);
        if (otp == null || otp.length() < 6) {
            // TODO: 31/08/2025
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestChangeVerifyOTP request = new RequestChangeVerifyOTP(
                requestChangePassword.getMember_id(),
                requestChangePassword.getNewpassword(),
                otp,
                channel.getValue()
        );
        executeApiCall(this, apiService.changePasswordVerifyOTP(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                onSuccessNext.run();
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