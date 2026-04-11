package com.dj.user.activity.mine.withdraw;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Log;
import android.view.View;
import android.view.animation.LinearInterpolator;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.mine.bank.BankFormActivity;
import com.dj.user.activity.mine.bank.BankOptionActivity;
import com.dj.user.databinding.ActivityWithdrawBinding;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestWithdraw;
import com.dj.user.model.response.Bank;
import com.dj.user.model.response.BankAccount;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Transaction;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.BankAccountBottomSheetDialogFragment;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.text.DecimalFormat;
import java.util.ArrayList;
import java.util.List;

public class WithdrawActivity extends BaseActivity {

    private ActivityWithdrawBinding binding;
    private Member member;
    private double totalPoints = 0.0;
    private double withdrawAmount = 0.0;
    private boolean withCommission = false;
    private double percentage = 0.0;
    private BankAccount selectedBankAccount;
    private String bankAccountName;
    private Bank selectedBank;
    private BankAccountBottomSheetDialogFragment bankAccountBottomSheet;
    private ObjectAnimator refreshAnimator;
    private List<BankAccount> bankAccountList;
    private boolean isSwitchBankAccount = false;

    private final ActivityResultLauncher<Intent> bankResultLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    Intent data = result.getData();
                    String bankJson = data.getStringExtra("data");
                    if (bankJson != null) {
                        selectedBank = new Gson().fromJson(bankJson, Bank.class);
                        binding.textViewFieldBankName.setText(selectedBank.getBank_name());
                        binding.textViewFieldBankName.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityWithdrawBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.withdraw_title), 0, null);
        setupUI();
        setupCurrencyInputWatcher();
        setupViewData();
        getBankAccountList();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getProfile();
        getYxiTransferList();
        if (bankAccountBottomSheet != null && bankAccountBottomSheet.isVisible()) {
            getBankAccountList();
        }
    }

    private void setupUI() {
        binding.imageViewRefresh.setOnClickListener(view -> {
            getProfile();
            getYxiTransferList();
        });
        binding.textViewAll.setOnClickListener(view -> binding.editTextAmount.setText(FormatUtils.formatAmount(member.getBalance())));
        binding.textViewBank.setOnClickListener(view -> {
            binding.textViewBank.setBackgroundResource(R.drawable.bg_tab_start);
            binding.textViewBank.setTextColor(ContextCompat.getColor(this, R.color.black_000000));
            binding.textViewQr.setBackgroundResource(R.drawable.bg_tab_end_bordered);
            binding.textViewQr.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
            showBank();
        });
        binding.buttonProceed.setOnClickListener(view -> withdraw());
        binding.textViewQr.setOnClickListener(view -> {
            binding.textViewBank.setBackgroundResource(R.drawable.bg_tab_start_bordered);
            binding.textViewBank.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
            binding.textViewQr.setBackgroundResource(R.drawable.bg_tab_end);
            binding.textViewQr.setTextColor(ContextCompat.getColor(this, R.color.black_000000));
            showQR();
        });
        binding.buttonGenerateQr.setOnClickListener(view -> getWithdrawQr());
    }

    private void setupCurrencyInputWatcher() {
        binding.editTextAmount.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;
            private String current = "";

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                clearErrorTransparent(WithdrawActivity.this, binding.panelAmountInput);
                if (editing) return;
                editing = true;

                try {
                    String raw = s.toString();
                    if (raw.isEmpty()) {
                        binding.editTextAmount.setText("");
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        binding.editTextAmount.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits) / 100.0;
                    DecimalFormat formatter = new DecimalFormat("###,##0.00");
                    String formatted = formatter.format(parsed);

                    binding.editTextAmount.setText(formatted);
                    binding.editTextAmount.setSelection(formatted.length());
                    setupFeeViewData();

                } catch (Exception e) {
                    Log.e("###", "afterTextChanged: ", e);
                }
                editing = false;
            }
        });
    }

    private void setupViewData() {
        binding.textViewId.setText(member.getPrefix());
        binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
        binding.textViewPoint.setText(FormatUtils.formatAmount(totalPoints));
    }

    private void setupBankViewData() {
        if (selectedBankAccount == null) {
            binding.panelBankForm.setVisibility(View.VISIBLE);
            binding.panelBankDetails.setVisibility(View.GONE);

            binding.panelFieldBank.setOnClickListener(view -> {
                Intent intent = new Intent(WithdrawActivity.this, BankOptionActivity.class);
                bankResultLauncher.launch(intent);
            });
            binding.editTextAccountName.setBackgroundTransparent(true);
            binding.editTextAccountName.setHint(getString(R.string.withdraw_bank_account_name));
            binding.editTextAccountName.setTextSize(14f);
            binding.editTextAccountName.setText(bankAccountName);
            binding.editTextAccountName.setEnabled(StringUtil.isNullOrEmpty(bankAccountName));
            binding.editTextAccountNo.setBackgroundTransparent(true);
            binding.editTextAccountNo.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
            binding.editTextAccountNo.setHint(getString(R.string.withdraw_bank_account_number));
            binding.editTextAccountNo.setTextSize(14f);

        } else {
            binding.panelBankForm.setVisibility(View.GONE);
            binding.panelBankDetails.setVisibility(View.VISIBLE);

            binding.panelChangeAccount.setOnClickListener(view -> {
                isSwitchBankAccount = true;
                getBankAccountList();
//                bankAccount = null;
//                setupBankViewData();
            });
            binding.textViewBankName.setText(selectedBankAccount.getBankName());
            binding.textViewAccountName.setText(selectedBankAccount.getBank_full_name());
            binding.textViewAccountNo.setText(selectedBankAccount.getBank_account());
        }
    }

    private void setupFeeViewData() {
        withdrawAmount = FormatUtils.getEditTextAmount(binding.editTextAmount);
        binding.textViewPercentage.setVisibility(withCommission ? View.VISIBLE : View.GONE);
        binding.textViewWithdrawable.setVisibility(withCommission && withdrawAmount > 0 ? View.VISIBLE : View.GONE);

        binding.textViewPercentage.setText(String.format(getString(R.string.withdraw_percentage), FormatUtils.formatAmount(percentage)));
        double receivable = withdrawAmount * ((100 - percentage) / 100);
        binding.textViewWithdrawable.setText(String.format(getString(R.string.withdraw_withdrawable), FormatUtils.formatAmount(receivable)));
    }

    private void showBank() {
        binding.panelBank.setVisibility(View.VISIBLE);
        binding.panelQr.setVisibility(View.GONE);
    }

    private void showQR() {
        binding.panelBank.setVisibility(View.GONE);
        binding.panelQr.setVisibility(View.VISIBLE);
    }

    private void startRefreshAnimation() {
        if (refreshAnimator == null) {
            refreshAnimator = ObjectAnimator.ofFloat(binding.imageViewRefresh, "rotation", 0f, 360f);
            refreshAnimator.setDuration(300);
            refreshAnimator.setRepeatCount(ValueAnimator.INFINITE);
            refreshAnimator.setInterpolator(new LinearInterpolator());
        }
        refreshAnimator.start();
    }

    private void stopRefreshAnimation() {
        if (refreshAnimator != null && refreshAnimator.isRunning()) {
            refreshAnimator.cancel();
            binding.imageViewRefresh.setRotation(0f);
        }
    }

    private void getProfile() {
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(WithdrawActivity.this, CacheManager.KEY_USER_PROFILE, member);
                }
                setupViewData();
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return false;
            }
        }, false);
    }

    private void getBankAccountList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getBankAccountList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<BankAccount>> response) {
                percentage = response.getCharge();
                withCommission = !response.isHas_game();
                setupFeeViewData();

                bankAccountList = response.getData();
                if (bankAccountList != null && !bankAccountList.isEmpty()) {
                    if (selectedBankAccount == null) {
                        // Find a QuickPay account
                        selectedBankAccount = bankAccountList.stream()
                                .filter(BankAccount::isQuickPay)
                                .findFirst()
                                .orElse(bankAccountList.get(0)); // fallback first item
                        selectedBankAccount.setSelected(true);
                    } else {
                        // If already selected, update flags accordingly
                        String selectedId = selectedBankAccount.getBankaccount_id();
                        for (BankAccount bankAccount : bankAccountList) {
                            bankAccount.setSelected(bankAccount.getBankaccount_id().equalsIgnoreCase(selectedId));
                        }
                    }
                    // Set the name
                    bankAccountName = selectedBankAccount.getBank_full_name();
                }
                setupBankViewData();
                if (bankAccountBottomSheet != null && bankAccountBottomSheet.isVisible()) {
                    // Refresh existing sheet
                    bankAccountBottomSheet.updateBankAccounts(new ArrayList<>(bankAccountList));
                } else if (isSwitchBankAccount) {
                    // First time open
                    bankAccountBottomSheet = BankAccountBottomSheetDialogFragment.newInstance(new ArrayList<>(bankAccountList),
                            (bankAccount) -> {
                                selectedBankAccount = bankAccount;
                                setupBankViewData();
                            },
                            () -> {
                                Bundle bundle = new Bundle();
                                bundle.putString("data", bankAccountName);
                                startAppActivity(new Intent(WithdrawActivity.this, BankFormActivity.class),
                                        !StringUtil.isNullOrEmpty(bankAccountName) ? bundle : null,
                                        false, false, true
                                );
                            }
                    );
                    bankAccountBottomSheet.show(getSupportFragmentManager(), "BankAccountBottomSheet");
                    isSwitchBankAccount = false;
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

    private void getYxiTransferList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getYxiTransferList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
                totalPoints = 0.0;
                List<YxiProvider> providerList = response.getData();
                // Handle empty or null list early
                if (providerList == null || providerList.isEmpty()) {
                    binding.textViewPoint.setText(FormatUtils.formatAmount(totalPoints));
                    stopRefreshAnimation();
                    return;
                }
                // Calculate total points
                totalPoints = providerList.stream()
                        .filter(p -> p.getPlayer() != null)
                        .flatMap(p -> p.getPlayer().stream())
                        .mapToDouble(Player::getBalance)
                        .sum();
                binding.textViewPoint.setText(FormatUtils.formatAmount(totalPoints));
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return true;
            }
        }, false);
    }

    private void withdraw() {
        withdrawAmount = FormatUtils.getEditTextAmount(binding.editTextAmount);
        String bankId = selectedBank != null ? selectedBank.getBank_id() : "";
        String bankAccountName = binding.editTextAccountName.getText();
        String bankAccountNo = binding.editTextAccountNo.getText();

        if (withdrawAmount == 0) {
            return;
        }
        if (selectedBankAccount == null) {
            if (bankId.isEmpty() || bankAccountName.isEmpty() || bankAccountNo.isEmpty()) {
                return;
            }
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestWithdraw request;
        if (selectedBankAccount == null) {
            request = new RequestWithdraw(member.getMember_id(), withdrawAmount, bankId, bankAccountNo, bankAccountName);
        } else {
            request = new RequestWithdraw(member.getMember_id(), selectedBankAccount.getBankaccount_id(), withdrawAmount);
        }
        executeApiCall(this, apiService.withdraw(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Transaction> response) {
                binding.editTextAmount.setText("");
                showCustomConfirmationDialog(WithdrawActivity.this,
                        "", getString(R.string.withdraw_application_submitted), "",
                        "", "OK", new CustomConfirmationDialog.OnButtonClickListener() {
                            @Override
                            public void onPositiveButtonClicked() {

                            }

                            @Override
                            public void onNegativeButtonClicked() {

                            }
                        }
                );
                getProfile();
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

    private void getWithdrawQr() {
        double withdrawAmount = FormatUtils.getEditTextAmount(binding.editTextAmount);
        if (withdrawAmount == 0) {
            showErrorTransparent(this, binding.panelAmountInput);
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestWithdraw request = new RequestWithdraw(member.getMember_id(), withdrawAmount);
        executeApiCall(this, apiService.getWithdrawQr(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String id = "";
                List<Transaction> rawList = response.getCredit();
                if (rawList != null && !rawList.isEmpty()) {
                    Transaction credit = rawList.get(0);
                    id = credit.getCredit_id();
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", response.getQr());
                bundle.putString("id", id);
                bundle.putDouble("amount", withdrawAmount);
                startAppActivity(new Intent(WithdrawActivity.this, WithdrawQRActivity.class),
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
}