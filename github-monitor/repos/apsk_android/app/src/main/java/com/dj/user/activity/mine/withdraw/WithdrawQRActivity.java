package com.dj.user.activity.mine.withdraw;

import android.graphics.Bitmap;
import android.os.Bundle;
import android.os.CountDownTimer;
import android.os.Handler;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityWithdrawQrBinding;
import com.dj.user.enums.TransactionStatus;
import com.dj.user.model.request.RequestPaymentStatus;
import com.dj.user.model.request.RequestWithdraw;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;
import com.dj.user.widget.TopUpWithdrawDialog;

import java.util.List;
import java.util.Locale;

public class WithdrawQRActivity extends BaseActivity {

    private ActivityWithdrawQrBinding binding;
    private Member member;
    private String id, qr;
    private double amount;
    private CountDownTimer countDownTimer;
    private static final long OTP_RESEND_INTERVAL = 301_000; // 300 seconds = 5 minutes
    private long timeLeft = OTP_RESEND_INTERVAL;

    private final Handler handler = new Handler();
    private final long PAYMENT_STATUS_INTERVAL = 5000; // 5 seconds
    private boolean isCheckingPaymentStatus = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityWithdrawQrBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.withdraw_qr_title), 0, null);
        setupUI();
        if (savedInstanceState != null) {
            timeLeft = savedInstanceState.getLong("timeLeft", OTP_RESEND_INTERVAL);
        }
        startCountdown(timeLeft);
        startPaymentStatusPolling();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
        stopPaymentStatusPolling();
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        outState.putLong("timeLeft", timeLeft);
    }


    private void parseIntentData() {
        qr = getIntent().getStringExtra("data");
        id = getIntent().getStringExtra("id");
        amount = getIntent().getDoubleExtra("amount", 0.00);
    }

    private void setupUI() {
        binding.textViewAmount.setText(String.format(getString(R.string.template_rm_s), FormatUtils.formatAmount(amount)));
        Bitmap bitmap = ImageUtils.generateQRCode(this, qr);
        if (bitmap != null) {
            binding.imageViewQr.setImageBitmap(bitmap);
        }
    }

    private void startCountdown(long startTime) {
        if (countDownTimer != null) {
            countDownTimer.cancel();
        }
        countDownTimer = new CountDownTimer(startTime, 1000) {
            public void onTick(long millisUntilFinished) {
                long secondsLeft = millisUntilFinished / 1000;
                binding.textViewCountDown.setText(String.format(Locale.ENGLISH, getString(R.string.template_d_secs), secondsLeft));
            }

            public void onFinish() {
                stopPaymentStatusPolling();
                getWithdrawQr();
            }
        };
        countDownTimer.start();
    }

    private void startPaymentStatusPolling() {
        if (StringUtil.isNullOrEmpty(id) || isCheckingPaymentStatus) {
            return;
        }
        isCheckingPaymentStatus = true;
        Runnable checkStatusRunnable = new Runnable() {
            @Override
            public void run() {
                getPaymentStatus(id);
                if (isCheckingPaymentStatus) {
                    handler.postDelayed(this, PAYMENT_STATUS_INTERVAL);
                }
            }
        };
        handler.post(checkStatusRunnable);
    }

    private void stopPaymentStatusPolling() {
        isCheckingPaymentStatus = false;
        handler.removeCallbacksAndMessages(null);
    }


    private void getWithdrawQr() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestWithdraw request = new RequestWithdraw(member.getMember_id(), amount);
        executeApiCall(this, apiService.getWithdrawQr(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                timeLeft = OTP_RESEND_INTERVAL;
                startCountdown(timeLeft);
                List<Transaction> rawList = response.getCredit();
                if (rawList != null && !rawList.isEmpty()) {
                    Transaction credit = rawList.get(0);
                    id = credit.getCredit_id();
                    startPaymentStatusPolling();
                }
                qr = response.getQr();
                setupUI();
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

    private void getPaymentStatus(String id) {
        if (StringUtil.isNullOrEmpty(id)) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPaymentStatus request = new RequestPaymentStatus(member.getMember_id(), id);
        executeApiCall(this, apiService.getWithdrawalStatus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                List<Transaction> transactionList = response.getCredit();
                if (transactionList != null && !transactionList.isEmpty()) {
                    Transaction credit = transactionList.get(0);
                    TransactionStatus status = TransactionStatus.fromValue(credit.getStatus());
                    if (status == TransactionStatus.SUCCESS) {
                        stopPaymentStatusPolling();
                        TopUpWithdrawDialog topUpWithdrawDialog = new TopUpWithdrawDialog
                                (WithdrawQRActivity.this, false, getString(R.string.withdraw_qr_success_title), getString(R.string.withdraw_qr_success_desc), credit.getAmount(),
                                        getString(R.string.withdraw_qr_success_positive), () -> {
                                    CustomToast.showTopToast(WithdrawQRActivity.this, String.format(getString(R.string.withdraw_qr_success_toast), FormatUtils.formatAmount(credit.getAmount())));
                                    onBaseBackPressed();
                                });
                        topUpWithdrawDialog.show();
                    }
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
        }, false);
    }
}