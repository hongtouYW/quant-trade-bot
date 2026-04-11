package com.dj.shop.activity.yxi;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.Toast;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.success.SuccessYxiActivity;
import com.dj.shop.adapter.YxiGridItemAdapter;
import com.dj.shop.databinding.ActivityYxiListBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.model.request.RequestPlayerNew;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.YxiProvider;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;
import com.google.gson.Gson;

import java.util.List;

public class YxiListActivity extends BaseActivity {
    ActivityYxiListBinding binding;
    LinearLayout searchPanel, dataPanel, noDataPanel, loadingPanel;
    YxiGridItemAdapter yxiGridItemAdapter;
    private ActionType actionType;
    private Shop shop;
    YxiProvider selectedYxiProvider;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        setupUI();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.yxi_title), R.drawable.ic_toolbar_search, view -> {
            if (searchPanel.getVisibility() == View.VISIBLE) {
                searchPanel.setVisibility(View.GONE);
                hideKeyboard(this);
            } else {
                searchPanel.setVisibility(View.VISIBLE);
            }
        });
        setupSearchPanel();
        setupGridView();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (actionType == ActionType.CREATE_USER) {
            getShopProfile();
        }
        getYxiProviderList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
        }
    }

    private void setupUI() {
        searchPanel = binding.panelSearch;
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        binding.buttonCreate.setText(actionType == ActionType.CREATE_USER ? getString(R.string.yxi_create_player) : getString(R.string.yxi_next));
        binding.buttonCreate.setOnClickListener(view -> {
            selectedYxiProvider = yxiGridItemAdapter.getSelectedYxiProvider();
            switch (actionType) {
                case TOP_UP:
                case WITHDRAWAL:
                    if (selectedYxiProvider == null) {
                        Toast.makeText(this, getString(R.string.yxi_select_yxi), Toast.LENGTH_SHORT).show();
                        return;
                    }

                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(actionType));
                    bundle.putString("provider", new Gson().toJson(selectedYxiProvider));
                    startAppActivity(new Intent(YxiListActivity.this, SearchIdActivity.class), bundle, false, false, true);
                    break;
                case CREATE_USER:
                    createNewPlayer();
                    break;
            }
        });
    }

    private void setupSearchPanel() {
        binding.editTextSearch.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void onTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void afterTextChanged(Editable editable) {
                if (editable.length() > 0) {
                    binding.imageViewClear.setVisibility(View.VISIBLE);
                } else {
                    binding.imageViewClear.setVisibility(View.GONE);
                }

                String keyword = editable.toString().trim().toLowerCase();
                yxiGridItemAdapter.getFilter().filter(keyword);
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void setupGridView() {
        yxiGridItemAdapter = new YxiGridItemAdapter(this);
        binding.gridViewYxi.setAdapter(yxiGridItemAdapter);
        yxiGridItemAdapter.setFilterListener(isEmpty -> {
            if (isEmpty) {
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
            } else {
                dataPanel.setVisibility(View.VISIBLE);
                noDataPanel.setVisibility(View.GONE);
                loadingPanel.setVisibility(View.GONE);
            }
        });
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
                    CacheManager.saveObject(YxiListActivity.this, CacheManager.KEY_SHOP_PROFILE, updatedShopData);
                    shop = updatedShopData;
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
        }, false);
    }

    private void getYxiProviderList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(shop.getShop_id());
        executeApiCall(this, apiService.getYxiProviderList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
                List<YxiProvider> yxiProviderList = response.getData();
                if (yxiProviderList != null && !yxiProviderList.isEmpty()) {
                    yxiGridItemAdapter.setData(yxiProviderList);

                    String keyword = binding.editTextSearch.getText().toString().trim();
                    yxiGridItemAdapter.getFilter().filter(keyword);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
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
        }, false);
    }

    private void createNewPlayer() {
        if (selectedYxiProvider == null) {
            Toast.makeText(this, getString(R.string.yxi_select_yxi), Toast.LENGTH_SHORT).show();
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerNew request = new RequestPlayerNew(shop.getShop_id(), selectedYxiProvider.getProvider_id());
        executeApiCall(this, apiService.createNewPlayer(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player player = response.getData();
                String memberLogin = !StringUtil.isNullOrEmpty(response.getMember_login()) ? response.getMember_login() : "-";
                String memberPassword = !StringUtil.isNullOrEmpty(response.getMember_pass()) ? response.getMember_pass() : "-";
                String playerLogin = !StringUtil.isNullOrEmpty(response.getPlayer_login()) ? response.getPlayer_login() : "-";
                String playerPassword = !StringUtil.isNullOrEmpty(response.getPlayer_pass()) ? response.getPlayer_pass() : "-";
                if (player != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createNewYxiUserSuccess(YxiListActivity.this, FormatUtils.formatMsianPhone(memberLogin), memberPassword, selectedYxiProvider.getIcon(), selectedYxiProvider.getProvider_id(), player.getGamemember_id(), selectedYxiProvider.getProvider_name(), playerLogin, playerPassword)));
                    startAppActivity(new Intent(YxiListActivity.this, SuccessYxiActivity.class), bundle, false, false, true);
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