package com.dj.user.fragment.topup;

import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.mine.bank.BankListActivity;
import com.dj.user.activity.mine.topup.TopUpActivity;
import com.dj.user.adapter.PaymentPlatformGridItemAdapter;
import com.dj.user.databinding.FragmentTopUpOnlineBinding;
import com.dj.user.enums.TransactionStatus;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemChip;
import com.dj.user.model.request.RequestPaymentStatus;
import com.dj.user.model.request.RequestPaymentType;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestTopUp;
import com.dj.user.model.response.BankAccount;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.PaymentType;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.TopUpWithdrawDialog;
import com.google.android.flexbox.FlexboxLayout;

import java.text.DecimalFormat;
import java.util.ArrayList;
import java.util.List;

public class TopUpOnlineFragment extends BaseFragment {

    private FragmentTopUpOnlineBinding binding;
    private Context context;
    private Member member;
    private Transaction credit;
    private PaymentPlatformGridItemAdapter paymentPlatformGridItemAdapter;
    private PaymentType selectedPaymentType;
    private boolean hasBankAccountAdded = false;
    private double minAmount = 0.01;
    private double maxAmount = 0.0;

    public TopUpOnlineFragment newInstance(Context context) {
        TopUpOnlineFragment fragment = new TopUpOnlineFragment();
        fragment.context = context;
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context ctx) {
        super.onAttach(ctx);
        if (context == null) {
            context = ctx;
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentTopUpOnlineBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);
        setupPlatform();
        setupUI();
        getPaymentTypeList();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getBankAccountList();
        if (credit != null) {
            getPaymentStatus(credit.getCredit_id());
        }
    }

    private void setupPlatform() {
        paymentPlatformGridItemAdapter = new PaymentPlatformGridItemAdapter(context);
        binding.gridViewPlatform.setAdapter(paymentPlatformGridItemAdapter);
        paymentPlatformGridItemAdapter.setOnPaymentTypeClickListener(paymentType -> {
            selectedPaymentType = paymentType;
            setupAmountFlexbox();
        });
    }

    private void setupAmountFlexbox() {
        if (!isAdded()) {
            return;
        }
        String[] amountTypeList = selectedPaymentType.getAmount_type().split(",");
        minAmount = selectedPaymentType.getMin_amount();
        maxAmount = selectedPaymentType.getMax_amount();
        String range;
        if (maxAmount > 0) {
            range = String.format(getString(R.string.top_up_range), FormatUtils.formatAmount(minAmount), FormatUtils.formatAmount(maxAmount));
        } else {
            range = String.format(getString(R.string.top_up_min), FormatUtils.formatAmount(minAmount));
        }
        binding.textViewMinMax.setText(range);

        List<ItemChip> options = new ArrayList<>();
        for (String amount : amountTypeList) {
            int value = Integer.parseInt(amount.trim());
            options.add(new ItemChip(amount, String.valueOf(value * 100)));
        }

        FlexboxLayout flexboxLayout = binding.flexboxLayoutAmount;
        flexboxLayout.removeAllViews();

        LayoutInflater inflater = LayoutInflater.from(context);
        for (ItemChip chip : options) {
            View chipView = inflater.inflate(R.layout.item_chip_filter, flexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            chipView.setOnClickListener(v -> {
                for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                    View child = flexboxLayout.getChildAt(i);
                    TextView textView = child.findViewById(R.id.chip_label);

                    textView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
                    child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                }
                chipLabel.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
                chipView.setBackgroundResource(R.drawable.bg_button_orange);

                binding.editTextAmount.setText(chip.getValue());
            });
            flexboxLayout.addView(chipView);
        }
        double currentEditTextAmount = FormatUtils.getEditTextAmount(binding.editTextAmount);
        if (currentEditTextAmount != 0) {
            String plainValue = String.valueOf((int) currentEditTextAmount);
            for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                View child = flexboxLayout.getChildAt(i);
                TextView chipLabel = child.findViewById(R.id.chip_label);
                if (chipLabel.getText().toString().equalsIgnoreCase(plainValue)) {
                    chipLabel.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
                    child.setBackgroundResource(R.drawable.bg_button_orange);
                } else {
                    chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
                    child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                }
            }
        }
    }

    private void setupUI() {
        binding.editTextAmount.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;

            @Override
            public void afterTextChanged(Editable s) {
                if (editing) return;
                editing = true;
                try {
                    String raw = s.toString().trim();
                    if (raw.isEmpty()) {
                        resetChips();
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        resetChips();
                        binding.editTextAmount.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits) / 100.0;
                    DecimalFormat formatter = new DecimalFormat("###,##0.00");
                    String formatted = formatter.format(parsed);

                    binding.editTextAmount.setText(formatted);
                    binding.editTextAmount.setSelection(formatted.length());
                    String plainValue = String.valueOf((int) parsed);
                    for (int i = 0; i < binding.flexboxLayoutAmount.getChildCount(); i++) {
                        View child = binding.flexboxLayoutAmount.getChildAt(i);
                        TextView chipLabel = child.findViewById(R.id.chip_label);
                        if (chipLabel.getText().toString().equalsIgnoreCase(plainValue)) {
                            // Highlight this chip
                            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
                            child.setBackgroundResource(R.drawable.bg_button_orange);
                        } else {
                            // Reset chip
                            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
                            child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                        }
                    }
                } catch (Exception e) {
                    Log.e("###", "afterTextChanged: ", e);
                }
                editing = false;
            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {

            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {

            }
        });
        binding.buttonTopUp.setOnClickListener(view -> topUp());
    }

    private void resetChips() {
        for (int i = 0; i < binding.flexboxLayoutAmount.getChildCount(); i++) {
            View child = binding.flexboxLayoutAmount.getChildAt(i);
            TextView chipLabel = child.findViewById(R.id.chip_label);
            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
        }
    }

    private void getPaymentTypeList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPaymentType request = new RequestPaymentType(member.getMember_id(), "online");
        ((TopUpActivity) context).executeApiCall(context, apiService.getPaymentTypeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<PaymentType>> response) {
                List<PaymentType> paymentTypeList = response.getData();
                paymentPlatformGridItemAdapter.replaceItems(paymentTypeList);
                if (!paymentTypeList.isEmpty()) {
                    selectedPaymentType = paymentTypeList.get(0);
                    paymentPlatformGridItemAdapter.setSelectedIndex(0);
                    setupAmountFlexbox();
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

    private void getBankAccountList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((TopUpActivity) context).executeApiCall(context, apiService.getBankAccountList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<BankAccount>> response) {
                List<BankAccount> bankAccountList = response.getData();
                hasBankAccountAdded = bankAccountList != null && !bankAccountList.isEmpty();
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

    private void topUp() {
        double topUpAmount = FormatUtils.getEditTextAmount(binding.editTextAmount);
        if (topUpAmount < minAmount || (maxAmount > 0 && topUpAmount > maxAmount)) {
            String message;
            if (topUpAmount > maxAmount) {
                message = String.format(getString(R.string.top_up_max_error), FormatUtils.formatAmount(maxAmount));
            } else if (topUpAmount < 0) {
                message = String.format(getString(R.string.top_up_min_error), FormatUtils.formatAmount(minAmount));
            } else {
                message = String.format(getString(R.string.top_up_range_error), FormatUtils.formatAmount(minAmount), FormatUtils.formatAmount(maxAmount));
            }
            ((TopUpActivity) context).showCustomConfirmationDialog(
                    context, "", message, "", "",
                    getString(R.string.top_up_okay), new CustomConfirmationDialog.OnButtonClickListener() {
                        @Override
                        public void onPositiveButtonClicked() {

                        }

                        @Override
                        public void onNegativeButtonClicked() {

                        }
                    }
            );
            return;
        }
        if (!hasBankAccountAdded) {
            ((TopUpActivity) context).showCustomConfirmationDialog(
                    context, "", getString(R.string.top_up_add_bank_account), "", "",
                    getString(R.string.top_up_okay), new CustomConfirmationDialog.OnButtonClickListener() {
                        @Override
                        public void onPositiveButtonClicked() {
                            ((TopUpActivity) context).startAppActivity(new Intent(context, BankListActivity.class),
                                    null, false, false, true);
                        }

                        @Override
                        public void onNegativeButtonClicked() {

                        }
                    }
            );
            return;
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestTopUp request = new RequestTopUp(member.getMember_id(), selectedPaymentType.getPayment_id(), topUpAmount);
        ((TopUpActivity) context).executeApiCall(context, apiService.topUp(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                List<Transaction> transactionList = response.getCredit();
                if (transactionList != null && !transactionList.isEmpty()) {
                    credit = transactionList.get(0);
                }
                String url = response.getUrl();
                if (!StringUtil.isNullOrEmpty(url)) {
                    Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    context.startActivity(browserIntent);
                } else {
                    Toast.makeText(context, R.string.top_up_error_no_url, Toast.LENGTH_SHORT).show();
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

    public void getPaymentStatus(String id) {
        if (StringUtil.isNullOrEmpty(id)) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPaymentStatus request = new RequestPaymentStatus(member.getMember_id(), id);
        ((TopUpActivity) context).executeApiCall(context, apiService.getDepositStatus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                if (!isAdded()) {
                    return;
                }
                List<Transaction> transactionList = response.getCredit();
                if (transactionList != null && !transactionList.isEmpty()) {
                    credit = transactionList.get(0);
                }
                if (credit != null) {
                    TransactionStatus status = TransactionStatus.fromValue(credit.getStatus());
                    if (status == TransactionStatus.SUCCESS) {
                        TopUpWithdrawDialog topUpWithdrawDialog = new TopUpWithdrawDialog(context, true,
                                getString(R.string.top_up_success_title),
                                getString(R.string.top_up_success_desc),
                                credit.getAmount(),
                                getString(R.string.top_up_okay), () -> ((TopUpActivity) context).closePage()
                        );
                        topUpWithdrawDialog.show();
                    } else {
                        Toast.makeText(context, status.getTitle(), Toast.LENGTH_SHORT).show();
                    }
                    credit = null;
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

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
