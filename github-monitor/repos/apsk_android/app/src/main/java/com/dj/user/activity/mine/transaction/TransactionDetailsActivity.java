package com.dj.user.activity.mine.transaction;

import android.os.Bundle;
import android.view.View;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityTransactionDetailsBinding;
import com.dj.user.enums.CreditTransactionType;
import com.dj.user.enums.PointTransactionType;
import com.dj.user.enums.TransactionStatus;
import com.dj.user.enums.TransactionType;
import com.dj.user.model.request.RequestPaymentType;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.PaymentType;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.google.gson.Gson;

import java.util.List;

public class TransactionDetailsActivity extends BaseActivity {

    private ActivityTransactionDetailsBinding binding;
    private Member member;
    private Transaction transaction;
    private TransactionType type; // credit / point / history

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.transaction_details_title), 0, null);
        setupUI(transaction);
        getPaymentTypeList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            transaction = new Gson().fromJson(json, Transaction.class);
            json = getIntent().getStringExtra("type");
            type = new Gson().fromJson(json, TransactionType.class);
        }
    }

    private void setupUI(Transaction transaction) {
        if (transaction == null) return;
        hideAllPanels();
        TransactionStatus status = TransactionStatus.fromValue(transaction.getStatus());
        if (type == TransactionType.CREDIT || type == TransactionType.POINT) {
            setupCreditOrPointUI(transaction, type);
            binding.panelId.setVisibility(View.VISIBLE);
            binding.panelStatus.setVisibility(View.VISIBLE);
            binding.textViewId.setText(
                    type == TransactionType.CREDIT ? transaction.getInvoiceno() : transaction.getGamepoint_id()
            );
        } else if (type == TransactionType.HISTORY) {
            setupHistoryUI(transaction);
        }
        setupCommonUI(transaction, status);
    }

    private void hideAllPanels() {
        binding.panelType.setVisibility(View.GONE);
        binding.panelBank.setVisibility(View.GONE);
        binding.panelPaymentType.setVisibility(View.GONE);
        binding.panelProvider.setVisibility(View.GONE);
        binding.panelYxi.setVisibility(View.GONE);
        binding.panelBet.setVisibility(View.GONE);
        binding.panelWin.setVisibility(View.GONE);
        binding.panelStart.setVisibility(View.GONE);
        binding.panelEnd.setVisibility(View.GONE);
        binding.panelId.setVisibility(View.GONE);
        binding.panelStatus.setVisibility(View.GONE);
    }

    private void setupCommonUI(Transaction transaction, TransactionStatus status) {
        boolean hasBank = transaction.getBankaccount() != null;
        binding.panelBank.setVisibility(hasBank ? View.VISIBLE : View.GONE);
        binding.textViewBank.setText(transaction.getBankName());
        binding.textViewDate.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_SS_DASHED));
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewStatus.setTextColor(ContextCompat.getColor(this, status.getColorResId()));

        boolean hasRemarks = !StringUtil.isNullOrEmpty(transaction.getReason());
        binding.panelRemark.setVisibility(hasRemarks ? View.VISIBLE : View.GONE);
        binding.textViewRemark.setText(hasRemarks ? transaction.getReason() : "");
    }

    private void setupCreditOrPointUI(Transaction transaction, TransactionType type) {
        binding.panelType.setVisibility(View.VISIBLE);
        String amount = FormatUtils.formatAmount(transaction.getAmount());
        if (type == TransactionType.CREDIT) {
            CreditTransactionType creditType = transaction.getTransactionType();
            if (creditType != null) {
                String title = !StringUtil.isNullOrEmpty(transaction.getTitle()) ? transaction.getTitle() : getString(creditType.getLabel());
                binding.textViewType.setText(title);
                binding.textViewAmount.setText(String.format(getString(R.string.template_s_rm_s), creditType.getSymbol(), amount));

                boolean showProvider = creditType == CreditTransactionType.YXI_TOP_UP ||
                        creditType == CreditTransactionType.YXI_WITHDRAWAL;

                binding.panelProvider.setVisibility(showProvider ? View.VISIBLE : View.GONE);
                binding.textViewProvider.setText(transaction.getYxiProviderName());
            }
        } else {
            PointTransactionType pointType = PointTransactionType.fromValue(transaction.getType());
            if (pointType != null) {
                binding.textViewType.setText(pointType.getLabel());
                binding.textViewAmount.setText(String.format(getString(R.string.template_s_rm_s), pointType.getSymbol(), amount));
                binding.panelProvider.setVisibility(View.VISIBLE);
                binding.textViewProvider.setText(transaction.getYxiProviderName());
            }
        }
    }

    private void setupHistoryUI(Transaction transaction) {
        binding.panelProvider.setVisibility(View.VISIBLE);
        binding.textViewProvider.setText(transaction.getYxiProviderName());
        binding.panelYxi.setVisibility(View.VISIBLE);
        binding.textViewYxi.setText(transaction.getYxiName());
        binding.panelBet.setVisibility(View.VISIBLE);
        binding.textViewBet.setText(String.format(getString(R.string.template_rm_s), FormatUtils.formatAmount(transaction.getBetamount())));
        binding.panelWin.setVisibility(View.VISIBLE);
        binding.textViewWin.setText(String.format(getString(R.string.template_rm_s), FormatUtils.formatAmount(transaction.getWinloss())));
        binding.panelStart.setVisibility(View.VISIBLE);
        binding.textViewStart.setText(String.format(getString(R.string.template_rm_s), FormatUtils.formatAmount(transaction.getBefore_balance())));
        binding.panelEnd.setVisibility(View.VISIBLE);
        binding.textViewEnd.setText(String.format(getString(R.string.template_rm_s), FormatUtils.formatAmount(transaction.getAfter_balance())));
    }

    private void getPaymentTypeList() {
        if (type == TransactionType.POINT || type == TransactionType.HISTORY || transaction == null || transaction.getBankaccount() != null) {
            binding.panelPaymentType.setVisibility(View.GONE);
            return;
        }
        binding.panelPaymentType.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPaymentType request = new RequestPaymentType(member.getMember_id());
        executeApiCall(this, apiService.getPaymentTypeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<PaymentType>> response) {
                String paymentMethod = "CASH";
                List<PaymentType> paymentTypeList = response.getData();
                for (PaymentType paymentType : paymentTypeList) {
                    if (transaction != null && transaction.getPayment_id().equalsIgnoreCase(paymentType.getPayment_id())) {
                        paymentMethod = paymentType.getPayment_name().toUpperCase();
                        break;
                    }
                }
                binding.textViewPaymentType.setText(paymentMethod);
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