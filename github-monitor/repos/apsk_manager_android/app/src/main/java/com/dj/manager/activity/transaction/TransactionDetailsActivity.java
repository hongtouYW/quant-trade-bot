package com.dj.manager.activity.transaction;

import android.content.Intent;
import android.os.Bundle;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.user.UserDetailsActivity;
import com.dj.manager.activity.yxi.YxiPlayerDetailsActivity;
import com.dj.manager.databinding.ActivityTransactionDetailsBinding;
import com.dj.manager.enums.TransactionStatus;
import com.dj.manager.enums.TransactionType;
import com.dj.manager.model.request.RequestPaymentType;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.PaymentType;
import com.dj.manager.model.response.Player;
import com.dj.manager.model.response.Transaction;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.google.gson.Gson;

import java.util.List;

public class TransactionDetailsActivity extends BaseActivity {
    private ActivityTransactionDetailsBinding binding;
    private Manager manager;
    private Transaction transaction;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        String json = getIntent().getStringExtra("data");
        transaction = new Gson().fromJson(json, Transaction.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_transaction_details_title), 0, null);
        setupUI(transaction);
        getPaymentTypeList();
    }

    private void setupUI(Transaction transaction) {
        TransactionType type = transaction.getTransactionType();
        TransactionStatus status = transaction.getTransactionStatus();

        binding.imageViewType.setImageResource(type.getIconResId());
        binding.textViewType.setText(getString(type.getTitle()));
        String symbol;
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
        if (transaction.getTransactiontype().equalsIgnoreCase("member")) {
            binding.textViewIdLabel.setText(getString(R.string.shop_transaction_details_phone));
            binding.textViewId.setText(FormatUtils.formatMsianPhone(transaction.getPhone()));
        } else if (transaction.getTransactiontype().equalsIgnoreCase("game")) {
            binding.textViewIdLabel.setText(getString(R.string.shop_transaction_details_player));
            binding.textViewId.setText(transaction.getPlayerLogin());
        } else if (transaction.getTransactiontype().equalsIgnoreCase("shop")) {
            binding.textViewIdLabel.setText(getString(R.string.shop_transaction_details_manager));
            binding.textViewId.setText(transaction.getPrefixmanager());
        }
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewId.setOnClickListener(view -> {
            if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL ||
                    type == TransactionType.TOP_UP || type == TransactionType.WITHDRAWAL) {
                if (type == TransactionType.YXI_TOP_UP || type == TransactionType.YXI_WITHDRAWAL) {
                    Player player = new Player(Integer.parseInt(transaction.getGamemember_id()));
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(player));
                    startAppActivity(new Intent(TransactionDetailsActivity.this, YxiPlayerDetailsActivity.class),
                            bundle, false, false, false, true);
                } else {
                    Member member = new Member(Integer.parseInt(transaction.getMember_id()));
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(member));
                    startAppActivity(new Intent(TransactionDetailsActivity.this, UserDetailsActivity.class),
                            bundle, false, false, false, true);
                }
            }
        });
    }

    private void getPaymentTypeList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPaymentType request = new RequestPaymentType(manager.getManager_id());
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