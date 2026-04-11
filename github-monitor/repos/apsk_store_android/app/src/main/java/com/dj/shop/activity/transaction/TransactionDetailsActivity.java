package com.dj.shop.activity.transaction;

import android.content.Intent;
import android.os.Bundle;

import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.dashboard.DashboardSearchDetailsActivity;
import com.dj.shop.databinding.ActivityTransactionDetailsBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.enums.TransactionStatus;
import com.dj.shop.enums.TransactionType;
import com.dj.shop.model.request.RequestProfileGeneral;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.PaymentType;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.FormatUtils;
import com.google.gson.Gson;

import java.util.List;

public class TransactionDetailsActivity extends BaseActivity {
    private ActivityTransactionDetailsBinding binding;
    private Shop shop;
    private Transaction transaction;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        String json = getIntent().getStringExtra("data");
        transaction = new Gson().fromJson(json, Transaction.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.transaction_details_title), 0, null);
        setupUI(transaction);
//        getPaymentTypeList();
    }

    private void setupUI(Transaction transaction) {
        TransactionType type = transaction.getTransactionType();
        TransactionStatus status = transaction.getTransactionStatus();

        binding.imageViewType.setImageResource(type.getIconResId());
        binding.textViewType.setText(transaction.getTitle());
        String symbol = type.getSymbol();
        String amountStr = FormatUtils.formatAmount(Math.abs(transaction.getAmount()));
        if (transaction.getAmount() > 0) {
            symbol = "+";
            if (type == TransactionType.WITHDRAWAL || type == TransactionType.YXI_WITHDRAWAL || type == TransactionType.MANAGER_SETTLEMENT) {
                symbol = "-";
            }
        } else if (transaction.getAmount() < 0) {
            symbol = "-";
        } else {
            symbol = "";
        }
        binding.textViewAmount.setText(String.format(getString(R.string.template_s_s), symbol, amountStr));
        binding.textViewAmount.setTextColor(ContextCompat.getColor(this, type.getColorResId()));
        binding.textViewDate.setText(DateFormatUtils.formatIsoDate(transaction.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
        if (type == TransactionType.TOP_UP || type == TransactionType.WITHDRAWAL) {
            binding.textViewIdLabel.setText(getString(R.string.transaction_phone));
        } else if (transaction.getTransactiontype().equalsIgnoreCase("game")) {
            binding.textViewIdLabel.setText(getString(R.string.transaction_player_id));
        } else if (transaction.getTransactiontype().equalsIgnoreCase("shop")) {
            binding.textViewIdLabel.setText(getString(R.string.transaction_manager));
        }
        binding.textViewId.setText(transaction.getDisplayId());
        binding.textViewMethod.setText(transaction.getPaymentMethod());
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewId.setOnClickListener(view -> {
            if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL ||
                    type == TransactionType.TOP_UP || type == TransactionType.WITHDRAWAL) {
                Bundle bundle = new Bundle();
                if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL) {
                    bundle.putString("data", new Gson().toJson(ActionType.DETAILS_YXI_ID));
                    bundle.putString("id", transaction.getSearchId());
                    bundle.putString("yxi_id", transaction.getProviderId());
                    startAppActivity(new Intent(TransactionDetailsActivity.this, ActionUserDetailsActivity.class), bundle, false, false, true);

                } else {
//                    bundle.putString("data", new Gson().toJson(ActionType.DETAILS_USER_ID));
                    bundle.putString("data", transaction.getSearchId());
                    startAppActivity(new Intent(TransactionDetailsActivity.this, DashboardSearchDetailsActivity.class), bundle, false, false, true);
                }}
        });
    }

    private void getPaymentTypeList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfileGeneral request = new RequestProfileGeneral(shop.getShop_id());
        executeApiCall(this, apiService.getPaymentTypeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<PaymentType>> response) {
                List<PaymentType> paymentTypeList = response.getData();
                boolean found = false;
                for (PaymentType type : paymentTypeList) {
                    if (type.getPayment_id().equalsIgnoreCase(transaction.getPayment_id())) {
                        binding.textViewMethod.setText(type.getPayment_name());
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    binding.textViewMethod.setText("-");
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                binding.textViewMethod.setText("-");
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                binding.textViewMethod.setText("-");
                return false;
            }
        }, true);
    }
}