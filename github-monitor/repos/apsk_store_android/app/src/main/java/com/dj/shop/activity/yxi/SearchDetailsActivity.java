package com.dj.shop.activity.yxi;

import android.content.Intent;
import android.os.Bundle;
import android.util.Log;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.success.SuccessYxiActivity;
import com.dj.shop.databinding.ActivitySearchDetailsBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.model.request.RequestPlayerTopUpWithdraw;
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
import com.dj.shop.widget.RoundedEditTextView;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class SearchDetailsActivity extends BaseActivity {
    ActivitySearchDetailsBinding binding;
    private ActionType actionType;
    private Shop shop;
    Player player;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySearchDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);
        parseIntentData();

        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
        setupByActionType(actionType);
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
        }
        json = getIntent().getStringExtra("player");
        if (json != null) {
            player = new Gson().fromJson(json, Player.class);
        }
    }

    private void setupByActionType(ActionType actionType) {
        String icon = player.getProvider().getIcon();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).centerInside().fit().into(binding.imageViewYxi, new Callback() {
                @Override
                public void onSuccess() {

                }

                @Override
                public void onError(Exception e) {
                    binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
                }
            });
        } else {
            binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
        }
        binding.textViewYxiName.setText(player.getProvider().getProvider_name());
        binding.textViewYxiUserId.setText(player.getLoginId());
        binding.textViewYxiUserId.setOnClickListener(view -> {
            StringUtil.copyToClipboard(this, "", player.getLoginId());
        });
        binding.textViewDate.setText(DateFormatUtils.formatIsoDate(player.getCreated_on(), DateFormatUtils.FORMAT_DD_MM_YYYY));
        binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(player.getMemberBalance())));
        binding.textViewYxiBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(player.getBalance())));
        binding.editTextAmount.setInputFieldType(RoundedEditTextView.InputFieldType.CURRENCY);

        binding.textViewCancel.setOnClickListener(view -> onBaseBackPressed());

        switch (actionType) {
            case TOP_UP:
                binding.editTextAmount.setHint(getString(R.string.player_search_hint_top_up));

                binding.buttonAction.setText(getString(R.string.details_button_top_up));
                binding.buttonAction.setOnClickListener(view -> yxiTopUp());
                break;

            case WITHDRAWAL:
                binding.editTextAmount.setHint(getString(R.string.player_search_hint_withdraw));

                binding.buttonAction.setText(getString(R.string.details_button_withdraw));
                binding.buttonAction.setOnClickListener(view -> yxiWithdraw());
                break;
        }
    }

    private void yxiTopUp() {
        double amount = binding.editTextAmount.getCurrencyAmount();
        if (amount == 0) {
            binding.editTextAmount.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(shop.getShop_id(), player.getMember_id(), player.getGamemember_id(), amount);
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "yxiTopUp IP: " + publicIp);
            executeApiCall(this, apiService.playerTopUp(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    Member member = response.getMember();
                    Transaction transaction = response.getPoint();
                    if (transaction != null) {
                        Bundle bundle = new Bundle();
                        bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createYxiTopUpSuccess(SearchDetailsActivity.this, player.getProvider().getIcon(), player.getProviderName(), player.getLoginId(), transaction.getAmount(), member.getBalance(), transaction.getAfter_balance())));
                        startAppActivity(new Intent(SearchDetailsActivity.this, SuccessYxiActivity.class), bundle, true, false, true);
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
        });
    }

    private void yxiWithdraw() {
        double amount = binding.editTextAmount.getCurrencyAmount();
        if (amount == 0) {
            binding.editTextAmount.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(shop.getShop_id(), player.getMember_id(), player.getGamemember_id(), amount);
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "yxiTopUp IP: " + publicIp);
            executeApiCall(this, apiService.playerWithdraw(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    Member member = response.getMember();
                    Transaction transaction = response.getPoint();
                    if (transaction != null) {
                        Bundle bundle = new Bundle();
                        bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createYxiTWithdrawalSuccess(SearchDetailsActivity.this, player.getProvider().getIcon(), player.getProviderName(), player.getLoginId(), transaction.getAmount(), member.getBalance(), transaction.getAfter_balance())));
                        startAppActivity(new Intent(SearchDetailsActivity.this, SuccessYxiActivity.class), bundle, true, false, true);
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
        });
    }
}