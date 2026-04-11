package com.dj.shop.activity.account;

import android.content.Intent;
import android.graphics.Bitmap;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.qr.FullScreenImageActivity;
import com.dj.shop.databinding.ActivityUserDetailsBinding;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.ImageUtils;

public class UserDetailsActivity extends BaseActivity {
    private ActivityUserDetailsBinding binding;
    @NonNull
    protected Shop shop;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUserDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.profile_setting_user_title), 0, null);
        setupProfileData();
        getShopProfile();
    }

    protected void setupProfileData() {
        if (shop == null) {
            return;
        }
        binding.textViewUsername.setText(shop.getShop_login());
        binding.textViewCountry.setText(shop.getCountryName());
        binding.textViewState.setText(shop.getStateName());
        binding.textViewArea.setText(shop.getAreaName());

        Bitmap bitmap = ImageUtils.generateQRCode(this);
        if (bitmap != null) {
            binding.panelQr.setVisibility(View.VISIBLE);
            binding.imageViewQr.setImageBitmap(bitmap);
            binding.imageViewQr.setOnClickListener(v -> startAppActivity(
                    new Intent(UserDetailsActivity.this, FullScreenImageActivity.class),
                    null, false, false, true
            ));
        } else {
            binding.panelQr.setVisibility(View.GONE);
        }
    }

    private void getShopProfile() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(String.valueOf(shop.getShop_id()));
        executeApiCall(this, apiService.getShopProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                Shop updatedShopData = response.getData();
                if (updatedShopData != null) {
                    CacheManager.saveObject(UserDetailsActivity.this, CacheManager.KEY_SHOP_PROFILE, updatedShopData);
                }
                shop = updatedShopData;
                setupProfileData();
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
}