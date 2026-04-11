package com.dj.manager.activity.shop;

import android.os.Bundle;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityShopPermissionBinding;
import com.dj.manager.model.request.RequestUpdateShopPermission;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;

public class ShopPermissionActivity extends BaseActivity {
    private ActivityShopPermissionBinding binding;
    private Manager manager;
    private Shop shop;
    private RequestUpdateShopPermission request;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityShopPermissionBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_permission_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            shop = new Gson().fromJson(json, Shop.class);
            if (shop != null && manager != null) {
                request = new RequestUpdateShopPermission(manager.getManager_id(), shop.getShop_id(), shop.getCan_deposit(), shop.getCan_withdraw(), shop.getCan_create(), shop.getCan_block(), shop.getCan_income(), shop.getRead_clear(), shop.getCan_view_credential(), shop.getNo_withdrawal_fee());
            }
        }
    }

    private void setupUI() {
        bindSwitch(binding.switchCompatTopUp, shop.getCan_deposit(), request::setCan_deposit);
        bindSwitch(binding.switchCompatWithdraw, shop.getCan_withdraw(), request::setCan_withdraw);
        bindSwitch(binding.switchCompatCreate, shop.getCan_create(), request::setCan_create);
        bindSwitch(binding.switchCompatBlock, shop.getCan_block(), request::setCan_block);
        bindSwitch(binding.switchCompatIncome, shop.getCan_income(), request::setCan_income);
        bindSwitch(binding.switchCompatSettlement, shop.getRead_clear(), request::setRead_clear);
        bindSwitch(binding.switchCompatViewCredientials, shop.getCan_view_credential(), request::setCan_view_credential);

        binding.switchCompatWithdrawalFee.setChecked(shop.getNo_withdrawal_fee() == 0);
        binding.switchCompatWithdrawalFee.setOnCheckedChangeListener((buttonView, isChecked) -> {
            request.setNo_withdrawal_fee(isChecked ? 0 : 1);
            updatePermission();
        });
    }

    private void bindSwitch(androidx.appcompat.widget.SwitchCompat switchCompat, int value, java.util.function.IntConsumer setter) {
        switchCompat.setChecked(value == 1);
        switchCompat.setOnCheckedChangeListener((buttonView, isChecked) -> {
            setter.accept(isChecked ? 1 : 0);
            updatePermission();
        });
    }

    private void updatePermission() {
        if (request == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.updateShopPermission(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                shop = response.getData();
                CustomToast.showTopToast(ShopPermissionActivity.this, getString(R.string.shop_permission_updated));
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