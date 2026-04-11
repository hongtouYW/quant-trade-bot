package com.dj.user.activity.mine.bank;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityBankFormBinding;
import com.dj.user.model.request.RequestAddBank;
import com.dj.user.model.request.RequestDeleteBank;
import com.dj.user.model.request.RequestQuickPay;
import com.dj.user.model.response.Bank;
import com.dj.user.model.response.BankAccount;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.CustomToast;
import com.dj.user.widget.RoundedEditTextView;
import com.google.gson.Gson;

public class BankFormActivity extends BaseActivity {

    private ActivityBankFormBinding binding;
    private boolean isViewMode;
    private Member member;
    private String bankAccountName;
    private BankAccount bankAccount;
    private Bank selectedBank;

    private final ActivityResultLauncher<Intent> bankResultLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    Intent data = result.getData();
                    String bankJson = data.getStringExtra("data");
                    if (bankJson != null) {
                        selectedBank = new Gson().fromJson(bankJson, Bank.class);
                        binding.textViewBank.setText(selectedBank.getBank_name());
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBankFormBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(isViewMode ? R.string.bank_details_added_bank : R.string.bank_details_add_bank), 0, null);
        setupUI(isViewMode, bankAccount);
    }

    private void parseIntentData() {
        isViewMode = getIntent().getBooleanExtra("isViewMode", false);
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            if (isViewMode) {
                bankAccount = new Gson().fromJson(json, BankAccount.class);
            } else {
                bankAccountName = json;
            }
        }
    }

    private void setupUI(boolean isViewMode, BankAccount bank) {
        if (isViewMode) {
            if (bank != null) {
                binding.textViewBank.setText(bank.getBankName());
                binding.editTextAccountNo.setText(bank.getBank_account());
                binding.editTextName.setText(bank.getBank_full_name());
                binding.switchCompatQuickPay.setChecked(bank.isQuickPay());
            }
            // Disable inputs, except quick pay
            binding.panelBank.setEnabled(false);
            binding.editTextAccountNo.setEnabled(false);
            binding.editTextName.setEnabled(false);
            binding.editTextName.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
            binding.switchCompatQuickPay.setOnCheckedChangeListener((buttonView, isChecked) -> updateQuickPay());
            // Hide add button, show delete
            binding.buttonAdd.setVisibility(View.GONE);
            binding.buttonDelete.setVisibility(View.VISIBLE);
            binding.buttonDelete.setOnClickListener(v -> {
                showCustomConfirmationDialog(
                        this,
                        getString(R.string.bank_details_delete_title),
                        getString(R.string.bank_details_delete_desc),
                        "",
                        getString(R.string.bank_details_delete_negative),
                        getString(R.string.bank_details_delete_positive),
                        new CustomConfirmationDialog.OnButtonClickListener() {
                            @Override
                            public void onPositiveButtonClicked() {
                                deleteBankAccount();
                            }

                            @Override
                            public void onNegativeButtonClicked() {

                            }
                        }
                );
            });
        } else {
            // Add mode
            binding.buttonAdd.setVisibility(View.VISIBLE);
            binding.buttonDelete.setVisibility(View.GONE);
            binding.panelBank.setOnClickListener(view -> {
                clearErrorTransparent(this, binding.panelBank);
                Intent intent = new Intent(BankFormActivity.this, BankOptionActivity.class);
                bankResultLauncher.launch(intent);
            });
            binding.buttonAdd.setOnClickListener(view -> {
                binding.editTextName.clearError();
                clearErrorTransparent(this, binding.panelBank);
                binding.editTextAccountNo.clearError();
                addBankAccount();
            });
            if (!StringUtil.isNullOrEmpty(bankAccountName)) {
                binding.editTextName.setText(bankAccountName);
                binding.editTextName.setEnabled(false);
            }
        }

        // Common field setup
        binding.editTextName.setBackgroundTransparent(true);
        binding.editTextName.setHint(getString(R.string.bank_details_bank_account_holder));
        binding.editTextAccountNo.setBackgroundTransparent(true);
        binding.editTextAccountNo.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
        binding.editTextAccountNo.setHint(getString(R.string.bank_details_bank_account_number));
    }

    private void addBankAccount() {
        String name = binding.editTextName.getText();
        String accountNumber = binding.editTextAccountNo.getText();
        int isQuickPay = binding.switchCompatQuickPay.isChecked() ? 1 : 0;

        boolean hasError = false;
        if (name.isEmpty()) {
            binding.editTextName.showError("");
            hasError = true;
        }
        if (selectedBank == null) {
            showErrorTransparent(this, binding.panelBank);
            hasError = true;
        }
        if (accountNumber.isEmpty()) {
            binding.editTextAccountNo.showError("");
            hasError = true;
        }
        if (hasError) return;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestAddBank request = new RequestAddBank(member.getMember_id(), selectedBank.getBank_id(), accountNumber, name, isQuickPay);
        executeApiCall(this, apiService.addBankAccount(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<BankAccount> response) {
                CustomToast.showTopToast(BankFormActivity.this, getString(R.string.bank_details_added_success));
                onBaseBackPressed();
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

    private void updateQuickPay() {
        if (bankAccount == null) {
            return;
        }
        int isQuickPay = binding.switchCompatQuickPay.isChecked() ? 1 : 0;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestQuickPay request = new RequestQuickPay(member.getMember_id(), bankAccount.getBankaccount_id(), isQuickPay);
        executeApiCall(this, apiService.updateQuickPay(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<BankAccount> response) {
                CustomToast.showTopToast(BankFormActivity.this, getString(R.string.bank_details_update_success));
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

    private void deleteBankAccount() {
        if (bankAccount == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestDeleteBank request = new RequestDeleteBank(member.getMember_id(), bankAccount.getBankaccount_id());
        executeApiCall(this, apiService.deleteBankAccount(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<BankAccount> response) {
                CustomToast.showTopToast(BankFormActivity.this, getString(R.string.bank_details_delete_success));
                onBaseBackPressed();
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