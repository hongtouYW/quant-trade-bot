package com.dj.shop.activity.transaction;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.success.SuccessActivity;
import com.dj.shop.activity.success.SuccessBlockActivity;
import com.dj.shop.activity.yxi.SearchDetailsActivity;
import com.dj.shop.databinding.ActivityActionUserDetailsBinding;
import com.dj.shop.databinding.ViewEditTextPasswordBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.enums.UserStatus;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.model.request.RequestMemberBlock;
import com.dj.shop.model.request.RequestMemberChangePassword;
import com.dj.shop.model.request.RequestMemberSearch;
import com.dj.shop.model.request.RequestMemberTopUp;
import com.dj.shop.model.request.RequestMemberWithdraw;
import com.dj.shop.model.request.RequestMemberWithdrawQR;
import com.dj.shop.model.request.RequestPlayerSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Member;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;
import com.dj.shop.widget.AndroidBug5497Workaround;
import com.dj.shop.widget.CustomConfirmationDialog;
import com.dj.shop.widget.RoundedEditTextView;
import com.google.gson.Gson;

public class ActionUserDetailsActivity extends BaseActivity {
    ActivityActionUserDetailsBinding binding;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding;

    Transaction transaction; // from QR scan
    ActionType actionType;
    Shop shop;
    Member member;
    Player player;
    String memberId, playerId, yxiId;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityActionUserDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);
        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());

        parseIntentData();
        String title = "";
        if (actionType == ActionType.DETAILS_YXI_ID) {
            title = getString(R.string.details_player_title);
            playerId = getIntent().getStringExtra("id");
            yxiId = getIntent().getStringExtra("yxi_id");
            getPlayerProfile();
        } else {
            if (actionType == ActionType.DETAILS_USER_ID) {
                title = getString(R.string.details_user_title);
            }
            getMemberProfile();
        }
        setupToolbar(binding.toolbar.getRoot(), title, 0, null);
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
        }
        memberId = getIntent().getStringExtra("id");
        json = getIntent().getStringExtra("transaction");
        if (json != null) {
            transaction = new Gson().fromJson(json, Transaction.class);
        }
    }

    protected void setupByActionType(@Nullable ActionType actionType) {
        if (actionType == null) {
            return;
        }
        switch (actionType) {
            case TOP_UP:
                if (member == null) {
                    return;
                }
                binding.textViewPhoneId.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPhone());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.GONE);
                binding.panelJoinDate.setVisibility(View.VISIBLE);
                binding.textViewDate.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
                binding.panelYxiBalance.setVisibility(View.GONE);

                binding.panelEditText.setVisibility(View.VISIBLE);
                viewEditTextPasswordBinding.getRoot().setVisibility(View.GONE);
                binding.editTextAmount.setVisibility(View.VISIBLE);
                binding.editTextAmount.setInputFieldType(RoundedEditTextView.InputFieldType.CURRENCY);
                binding.editTextAmount.setHint(getString(R.string.details_hint_top_up_amount));

                binding.buttonAction.setText(R.string.details_button_top_up);
                binding.buttonAction.setOnClickListener(view -> memberTopUp());
                binding.textViewCancel.setVisibility(View.VISIBLE);
                binding.textViewCancel.setOnClickListener(view -> onBaseBackPressed());
                break;

            case WITHDRAWAL:
                if (member == null) {
                    return;
                }
                binding.textViewPhoneId.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPhone());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.GONE);
                binding.panelJoinDate.setVisibility(View.VISIBLE);
                binding.textViewDate.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
                binding.panelYxiBalance.setVisibility(View.GONE);

                binding.panelEditText.setVisibility(View.VISIBLE);
                viewEditTextPasswordBinding.getRoot().setVisibility(View.VISIBLE);
                viewEditTextPasswordBinding.editTextPassword.setHint(getString(R.string.login_hint_password));
                viewEditTextPasswordBinding.imageViewPasswordToggle.setOnClickListener(v -> togglePasswordVisibility(viewEditTextPasswordBinding.editTextPassword, viewEditTextPasswordBinding.imageViewPasswordToggle));

                binding.editTextAmount.setVisibility(View.VISIBLE);
                binding.editTextAmount.setInputFieldType(RoundedEditTextView.InputFieldType.CURRENCY);
                binding.editTextAmount.setHint(getString(R.string.details_hint_withdraw_amount));

                binding.buttonAction.setText(R.string.details_button_next);
                binding.buttonAction.setOnClickListener(view -> memberWithdraw());
                binding.textViewCancel.setVisibility(View.VISIBLE);
                binding.textViewCancel.setOnClickListener(view -> onBaseBackPressed());

                if (transaction != null) {
                    binding.editTextAmount.setText(FormatUtils.formatAmount(transaction.getAmount()));
                    binding.editTextAmount.setEnabled(false);
                    binding.buttonAction.setOnClickListener(view -> memberWithdrawFromQR());
                }
                break;

            case BLOCK_USER:
                if (member == null) {
                    return;
                }
                binding.textViewPhoneId.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPhone());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.VISIBLE);
                binding.textViewIdYxi.setText(R.string.details_account_id);
                binding.textViewIdYxiName.setText(member.getPrefix());
                binding.textViewIdYxiName.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPrefix());
                });
                binding.panelJoinDate.setVisibility(View.GONE);
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
                binding.panelYxiBalance.setVisibility(View.GONE);

                binding.panelEditText.setVisibility(View.GONE);
                binding.buttonAction.setVisibility(View.VISIBLE);
                binding.buttonAction.setText(R.string.details_button_block);
                binding.buttonAction.setOnClickListener(view -> blockUser());
                binding.textViewCancel.setVisibility(View.VISIBLE);
                binding.textViewCancel.setOnClickListener(view -> onBaseBackPressed());
                break;

            case DETAILS_USER_ID:
                if (member == null) {
                    return;
                }
                binding.textViewPhoneId.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPhone());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.VISIBLE);
                binding.textViewIdYxi.setText(getString(R.string.details_account_id));
                binding.textViewIdYxiName.setText(member.getPrefix());
                binding.textViewIdYxiName.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPrefix());
                });
                binding.panelJoinDate.setVisibility(View.VISIBLE);
                binding.textViewDate.setText(DateFormatUtils.formatIsoDate(member.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
                binding.panelYxiBalance.setVisibility(View.GONE);

                binding.panelEditText.setVisibility(View.GONE);
                binding.buttonAction.setText(getString(R.string.success_action_top_up));
                binding.buttonAction.setOnClickListener(view -> {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
                    bundle.putString("id", member.getMember_id());
                    startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                            false, false, true);
                });
                binding.textViewCancel.setText(getString(R.string.details_button_block));
                binding.textViewCancel.setOnClickListener(view -> blockUser());
                break;

            case DETAILS_YXI_ID:
                if (player == null) {
                    return;
                }
                binding.textViewPhoneId.setText(player.getLoginId());
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", player.getLoginId());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.VISIBLE);
                binding.textViewIdYxi.setText(R.string.details_yxi);
                binding.textViewIdYxiName.setText(player.getProviderName());
                binding.panelJoinDate.setVisibility(View.VISIBLE);
                binding.textViewDate.setText(DateFormatUtils.formatIsoDate(player.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(player.getMemberBalance())));
                binding.panelYxiBalance.setVisibility(View.VISIBLE);
                binding.textViewYxiBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(player.getBalance())));

                binding.panelEditText.setVisibility(View.GONE);
                binding.buttonAction.setText(getString(R.string.success_action_top_up));
                binding.buttonAction.setOnClickListener(view -> {
                    // TODO: 16/01/2026 directly top up to provider
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
                    bundle.putString("player", new Gson().toJson(player));
                    startAppActivity(new Intent(ActionUserDetailsActivity.this, SearchDetailsActivity.class),
                            bundle, false, false, true);
                });
                binding.textViewCancel.setText(getString(R.string.details_button_block));
                binding.textViewCancel.setOnClickListener(view ->
                        // TODO: 19/08/2025 Block player
                        startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessBlockActivity.class),
                                null, true, false, true
                        ));
                binding.textViewCancel.setVisibility(View.GONE);
                break;

            case CHANGE_PASSWORD:
                if (member == null) {
                    return;
                }
                binding.textViewPhoneId.setText(FormatUtils.formatMsianPhone(StringUtil.isNullOrEmpty(member.getPhone()) ? member.getMember_name() : member.getPhone()));
                binding.textViewPhoneId.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPhone());
                });

                binding.panelUserDetails.setVisibility(View.VISIBLE);
                binding.panelId.setVisibility(View.VISIBLE);
                binding.textViewIdYxi.setText(getString(R.string.details_account_id));
                binding.textViewIdYxiName.setText(member.getPrefix());
                binding.textViewIdYxiName.setOnClickListener(view -> {
                    StringUtil.copyToClipboard(this, "", member.getPrefix());
                });
                binding.panelJoinDate.setVisibility(View.GONE);
                binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(member.getBalance())));
                binding.panelYxiBalance.setVisibility(View.GONE);

                binding.panelEditText.setVisibility(View.GONE);
                binding.buttonAction.setVisibility(View.VISIBLE);
                binding.buttonAction.setText(R.string.details_button_change_password);
                binding.buttonAction.setOnClickListener(view -> changePassword());
                binding.textViewCancel.setVisibility(View.VISIBLE);
                binding.textViewCancel.setOnClickListener(view -> onBaseBackPressed());
                break;

            case CREATE_USER:
                break;
        }
    }

    private void getMemberProfile() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberSearch request = new RequestMemberSearch(shop.getShop_id(), memberId);
        executeApiCall(this, apiService.searchMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                memberId = member.getMember_id();
                setupByActionType(actionType);
            }

            @Override
            public boolean onApiError(int code, String message) {
                showCustomConfirmationDialog(ActionUserDetailsActivity.this, getString(R.string.app_name), message,
                        getString(R.string.button_confirm), new CustomConfirmationDialog.OnButtonClickListener() {
                            @Override
                            public void onButtonClicked() {
                                finish();
                            }
                        });
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                finish();
                return false;
            }
        }, true);
    }

    private void getPlayerProfile() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerSearch request = new RequestPlayerSearch(shop.getShop_id(), yxiId, playerId);
        executeApiCall(this, apiService.playerSearch(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                player = response.getData();
                member = player.getMember();
                memberId = member.getMember_id();
                setupByActionType(actionType);
            }

            @Override
            public boolean onApiError(int code, String message) {
                showCustomConfirmationDialog(ActionUserDetailsActivity.this, getString(R.string.app_name), message,
                        getString(R.string.button_confirm), new CustomConfirmationDialog.OnButtonClickListener() {
                            @Override
                            public void onButtonClicked() {
                                finish();
                            }
                        });
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }

    private void memberTopUp() {
        double amount = binding.editTextAmount.getCurrencyAmount();
        if (amount == 0) {
            binding.editTextAmount.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberTopUp request = new RequestMemberTopUp(shop.getShop_id(), member.getMember_id(), amount);
        executeApiCall(this, apiService.memberTopUp(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                Member member = response.getMember();
                if (member != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createTopUpSuccess(ActionUserDetailsActivity.this, FormatUtils.formatMsianPhone(member.getPhone()), binding.editTextAmount.getCurrencyAmount(), member.getBalance())));
                    startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessActivity.class), bundle, true, false, true);
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

    private void memberWithdraw() {
        double amount = binding.editTextAmount.getCurrencyAmount();
        String password = viewEditTextPasswordBinding.editTextPassword.getText().toString().trim();

        if (amount == 0 && password.isEmpty()) {
            binding.editTextAmount.showError("");
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        if (amount == 0) {
            binding.editTextAmount.showError("");
            return;
        }
        if (password.isEmpty()) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberWithdraw request = new RequestMemberWithdraw(shop.getShop_id(), member.getMember_id(), amount, password);
        executeApiCall(this, apiService.memberWithdraw(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Transaction> response) {
                Transaction transaction = response.getData();
                if (transaction != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createWithdrawalSuccess(ActionUserDetailsActivity.this, FormatUtils.formatMsianPhone(response.getPhone()), transaction.getAmount(), transaction.getAfter_balance())));
                    startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessActivity.class), bundle, true, false, true);
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

    private void memberWithdrawFromQR() {
        String password = viewEditTextPasswordBinding.editTextPassword.getText().toString().trim();
        if (password.isEmpty()) {
            showError(this, viewEditTextPasswordBinding.panelPassword);
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberWithdrawQR request = new RequestMemberWithdrawQR(shop.getShop_id(), member.getMember_id(), transaction.getCredit_id(), password);
        executeApiCall(this, apiService.memberWithdrawQR(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                Transaction transaction = response.getCredit();
                if (transaction != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createWithdrawalSuccess(ActionUserDetailsActivity.this, FormatUtils.formatMsianPhone(member.getPhone()), transaction.getAmount(), transaction.getAfter_balance())));
                    startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessActivity.class), bundle, true, false, true);
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

    private void changePassword() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberChangePassword request = new RequestMemberChangePassword(shop.getShop_id(), member.getMember_id());
        executeApiCall(this, apiService.changeUserPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String password = response.getPassword();
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createChangePasswordSuccess(ActionUserDetailsActivity.this, FormatUtils.formatMsianPhone(member.getPhone()), password)));
                startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessActivity.class),
                        bundle, true, false, true
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

    private void blockUser() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberBlock request = new RequestMemberBlock(shop.getShop_id(), member.getMember_id());
        executeApiCall(this, apiService.blockUser(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null && member.getUserStatus() == UserStatus.BLOCKED) {
                    Bundle bundle = new Bundle();
                    bundle.putString("id", member.getMember_id());
                    startAppActivity(new Intent(ActionUserDetailsActivity.this, SuccessBlockActivity.class),
                            bundle, true, false, true
                    );
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
}