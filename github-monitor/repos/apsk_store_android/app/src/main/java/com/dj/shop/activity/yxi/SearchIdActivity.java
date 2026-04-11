package com.dj.shop.activity.yxi;

import android.content.Intent;
import android.os.Bundle;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.databinding.ActivitySearchIdBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.request.RequestPlayerSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.YxiProvider;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.StringUtil;
import com.dj.shop.widget.AndroidBug5497WorkaroundBg;
import com.dj.shop.widget.RoundedEditTextView;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class SearchIdActivity extends BaseActivity {
    ActivitySearchIdBinding binding;
    ActionType actionType;
    private Shop shop;
    YxiProvider yxiProvider;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySearchIdBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);
        parseIntentData();

        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
        setupInputField();
        if (actionType != null && yxiProvider != null) {
            applyActionConfig(actionType);
        }
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
        }
        json = getIntent().getStringExtra("provider");
        if (json != null) {
            yxiProvider = new Gson().fromJson(json, YxiProvider.class);
        }
    }

    private void setupInputField() {
        binding.editTextId.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        binding.editTextId.setHint(getString(R.string.player_search_id_hint));
    }

    private void applyActionConfig(ActionType type) {
        String icon = yxiProvider.getIcon();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).centerCrop().fit().into(binding.imageViewYxi, new Callback() {
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
        binding.textViewYxiName.setText(yxiProvider.getProvider_name());
        binding.buttonSearch.setOnClickListener(view -> searchPlayer());
        binding.textViewBack.setOnClickListener(view -> onBaseBackPressed());
    }

    private void searchPlayer() {
        String id = binding.editTextId.getText();
        if (id.isEmpty()) {
            binding.editTextId.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerSearch request = new RequestPlayerSearch(shop.getShop_id(), yxiProvider.getProvider_id(), id);
        executeApiCall(this, apiService.playerSearch(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player player = response.getData();
                if (player != null) {
                    player.setProvider(yxiProvider);
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(actionType));
                    bundle.putString("player", new Gson().toJson(player));
                    startAppActivity(new Intent(SearchIdActivity.this, SearchDetailsActivity.class),
                            bundle, false, false, true);
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