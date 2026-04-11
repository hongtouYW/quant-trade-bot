package com.dj.user.dj.activity.mine.security;

import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Paint;
import android.net.Uri;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.KeyEvent;
import android.view.View;
import android.widget.EditText;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.FullScreenImageActivity;
import com.dj.user.databinding.DjActivityBindAuthenticatorBinding;
import com.dj.user.model.request.RequestBindGoogleOTP;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.GoogleAuthenticator;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.GoogleAuthenticatorUtil;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;

public class DJBindAuthenticatorActivity extends BaseActivity {

    private DjActivityBindAuthenticatorBinding binding;
    private Member member;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityBindAuthenticatorBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.google_authentication_title), 0, null);
        setupUI();
        setupOTPInputs();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getAuthenticatorInfo();
    }

    private void setupUI() {
        boolean isBinded = member.isGoogleAuthenticatorBinded();
        binding.textViewAction.setText(isBinded ? getString(R.string.google_authentication_unbind) : getString(R.string.google_authentication_bind));
        binding.textViewApp.setPaintFlags(binding.textViewApp.getPaintFlags() | Paint.UNDERLINE_TEXT_FLAG);
        binding.textViewApp.setOnClickListener(view -> GoogleAuthenticatorUtil.openGoogleAuthenticator(this));

        binding.panelStore.setVisibility(isBinded ? View.GONE : View.VISIBLE);
        binding.imageViewGoogle.setOnClickListener(view -> {
            String url = "https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2";
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
        });
        binding.imageViewApple.setOnClickListener(view -> {

        });

        binding.panelStep2.setVisibility(isBinded ? View.GONE : View.VISIBLE);
        binding.textViewStep3.setText(isBinded ? getString(R.string.google_authentication_step_2_binded) : getString(R.string.google_authentication_step_3));
        binding.buttonNext.setOnClickListener(view -> bindUnbindGoogleAuthenticator());
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

    private void getAuthenticatorInfo() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getGoogleAuthenticatorInfo(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<GoogleAuthenticator> response) {
                GoogleAuthenticator googleAuthenticator = response.getData();
                if (googleAuthenticator != null) {
                    Bitmap bitmap = ImageUtils.generateQRCode(DJBindAuthenticatorActivity.this, googleAuthenticator.getQr());
                    if (bitmap != null) {
                        binding.imageViewQr.setImageBitmap(bitmap);
                        binding.imageViewQr.setOnClickListener(v -> {
                            Bundle bundle = new Bundle();
                            bundle.putString("data", googleAuthenticator.getQr());
                            startAppActivity(new Intent(DJBindAuthenticatorActivity.this, FullScreenImageActivity.class),
                                    bundle, false, false, true);
                        });
                        binding.panelCopy.setOnClickListener(view ->
                                StringUtil.copyToClipboard(DJBindAuthenticatorActivity.this, "Shared Secret", googleAuthenticator.getSecret())
                        );
                    }
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }

    private void bindUnbindGoogleAuthenticator() {
        boolean isBinded = member.isGoogleAuthenticatorBinded();
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
        RequestBindGoogleOTP request = new RequestBindGoogleOTP(member.getMember_id(), otp, isBinded ? 0 : 1);
        executeApiCall(this, apiService.bindGoogle(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                CustomToast.showTopToast(DJBindAuthenticatorActivity.this, isBinded ? getString(R.string.google_authentication_success_unbind) : getString(R.string.google_authentication_success_bind));
                Intent intent = new Intent(DJBindAuthenticatorActivity.this, DJSecurityPrivacyActivity.class);
                intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
                startActivity(intent);
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}