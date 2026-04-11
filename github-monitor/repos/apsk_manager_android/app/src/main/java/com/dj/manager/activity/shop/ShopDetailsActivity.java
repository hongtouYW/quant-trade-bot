package com.dj.manager.activity.shop;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.transaction.TransactionHistoryActivity;
import com.dj.manager.activity.user.ActionReasonActivity;
import com.dj.manager.databinding.ActivityShopDetailsBinding;
import com.dj.manager.enums.ReasonActionType;
import com.dj.manager.enums.ShopStatus;
import com.dj.manager.model.request.RequestShopProfile;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;

public class ShopDetailsActivity extends BaseActivity {
    private ActivityShopDetailsBinding binding;
    private boolean isPasswordVisible = false;
    private Manager manager;
    private Shop shop;
    private String password = "";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityShopDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_details_title), 0, null);
        initListeners();
        bindShopData();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getShopProfile();
    }

    private void parseIntentData() {
        Uri data = getIntent().getData();
        if (data != null) {
            String host = data.getHost(); // shop
            if (!StringUtil.isNullOrEmpty(host) && host.equalsIgnoreCase("shop")) {
                String id = data.getQueryParameter("id"); // shop_id
                if (!StringUtil.isNullOrEmpty(id)) {
                    shop = new Shop(Integer.parseInt(id));
                }
            }
        } else {
            String json = getIntent().getStringExtra("data");
            if (json != null) {
                shop = new Gson().fromJson(json, Shop.class);
            }
        }
    }

    private void initListeners() {
        binding.imageViewPasswordToggle.setOnClickListener(view -> {
            isPasswordVisible = !isPasswordVisible;
            if (!StringUtil.isNullOrEmpty(password)) {
                updatePasswordVisibility();
            } else {
                if (isPasswordVisible) {
                    getShopPassword();
                }
            }
        });

        binding.buttonShopPassword.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonShopPassword.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(shop));
            openActivity(ChangeShopPasswordActivity.class, bundle);
        });
        binding.buttonShopBalance.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonShopBalance.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(shop));
            openActivity(ChangeShopBalanceActivity.class, bundle);
        });
        binding.buttonShopTransaction.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonShopTransaction.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(shop));
            openActivity(TransactionHistoryActivity.class, bundle);
        });
        binding.buttonShopPermission.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonShopPermission.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(shop));
            openActivity(ShopPermissionActivity.class, bundle);
        });
        binding.buttonShopSettlement.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonShopSettlement.setOnClickListener(view -> clearShopAmount());
    }

    private void openActivity(Class<?> targetActivity, Bundle bundle) {
        Intent intent = new Intent(this, targetActivity);
        startAppActivity(intent, bundle, false, false, false, true);
    }

    private void bindShopData() {
        if (shop == null) return;

        binding.textViewLocation.setText(shop.getShopLocation());
        binding.textViewShopName.setText(shop.getShop_name());
        binding.textViewShopUsername.setText(shop.getShop_login());
        binding.textViewShopUsername.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", shop.getShop_login()));
        binding.textViewOpenBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(shop.getPrincipal())));
        binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(shop.getBalance())));

        ShopStatus status = shop.getShopStatus();
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewStatus.setTextColor(ContextCompat.getColor(this, status.getColorResId()));
        updatePasswordVisibility();

        binding.panelButtons.setAlpha(status == ShopStatus.ACTIVE ? 1.0F : 0.4F);
        binding.buttonShopSettlement.setEnabled(status == ShopStatus.ACTIVE);
        binding.buttonShopPassword.setEnabled(status == ShopStatus.ACTIVE);
        binding.buttonShopBalance.setEnabled(status == ShopStatus.ACTIVE);
        binding.buttonShopTransaction.setEnabled(status == ShopStatus.ACTIVE);
        binding.buttonShopPermission.setEnabled(status == ShopStatus.ACTIVE);

        binding.buttonOpenCloseShop.setText(status.getAction());
        binding.buttonOpenCloseShop.setTextColor(ContextCompat.getColor(this, status.getActionColorResId()));
        binding.buttonOpenCloseShop.setBackgroundResource(status.getBgResId());
        binding.buttonOpenCloseShop.setOnClickListener(view -> openCloseShop());
    }

    private void updatePasswordVisibility() {
        binding.textViewPassword.setText(isPasswordVisible ? password : getString(R.string.placeholder_password_masked));
        binding.textViewPassword.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", password));
        binding.imageViewPasswordToggle.setImageResource(
                isPasswordVisible ? R.drawable.ic_eye_on : R.drawable.ic_eye_off
        );
    }

    private void getShopProfile() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());
        executeApiCall(this, apiService.getShopProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                shop = response.getData();
                bindShopData();
                getShopPassword();
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

    private void getShopPassword() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());
        executeApiCall(this, apiService.getShopPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                password = response.getPassword();
                updatePasswordVisibility();
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

    private void clearShopAmount() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());
        executeApiCall(this, apiService.clearShopAmount(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                getShopProfile();
                CustomToast.showTopToast(ShopDetailsActivity.this, getString(R.string.shop_details_settlement_success));
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

    private void openCloseShop() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());

        if (shop.getStatus() == 1) {
            executeApiCall(this, apiService.closeShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Shop> response) {
                    shop = response.getData();
                    bindShopData();
                    CustomToast.showTopToast(ShopDetailsActivity.this, getString(R.string.shop_details_status_updated));

                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ReasonActionType.CLOSE_SHOP));
                    bundle.putString("id", shop.getShop_id());
                    startAppActivity(new Intent(ShopDetailsActivity.this, ActionReasonActivity.class),
                            bundle, false, false, false, true);
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
        } else {
            executeApiCall(this, apiService.openShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Shop> response) {
                    shop = response.getData();
                    bindShopData();
                    CustomToast.showTopToast(ShopDetailsActivity.this, getString(R.string.shop_details_status_updated));
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
}