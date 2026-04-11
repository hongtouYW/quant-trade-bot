package com.dj.manager.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.dj.manager.activity.shop.ShopDetailsActivity;
import com.dj.manager.activity.shop.ShopManagementActivity;
import com.dj.manager.adapter.ShopListViewAdapter;
import com.dj.manager.databinding.FragmentShopBinding;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.request.RequestStatusData;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

import retrofit2.Call;

public class ShopFragment extends Fragment {
    private FragmentShopBinding binding;
    private Context context;
    private int page = 1;
    private Manager manager;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private ShopListViewAdapter shopListViewAdapter;
    private boolean allowFilterCallback = false;

    public ShopFragment newInstance(Context context, int page) {
        ShopFragment fragment = new ShopFragment();
        fragment.context = context;
        fragment.page = page;
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
        binding = FragmentShopBinding.inflate(inflater, container, false);
        context = getContext();
        manager = CacheManager.getObject(context, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getShopList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        ListView shopListView = binding.listViewShop;
        shopListViewAdapter = new ShopListViewAdapter(context, false);
        shopListView.setAdapter(shopListViewAdapter);

        shopListViewAdapter.setFilterListener(isEmpty -> {
            if (!allowFilterCallback) return;
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
        shopListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            ((ShopManagementActivity) context).startAppActivity(new Intent(context, ShopDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    public void filter(String keyword) {
        if (shopListViewAdapter != null) {
            shopListViewAdapter.getFilter().filter(keyword);
        }
    }

    private void getShopList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        Call<BaseResponse<List<Shop>>> call;
        if (page == 2) {
            RequestStatusData request = new RequestStatusData(manager.getManager_id(), 1);
            call = apiService.getShopList(request);
        } else if (page == 3) {
            RequestStatusData request = new RequestStatusData(manager.getManager_id(), 0);
            call = apiService.getShopList(request);
        } else {
            RequestProfile request = new RequestProfile(manager.getManager_id());
            call = apiService.getShopList(request);
        }
        ((ShopManagementActivity) context).executeApiCall(context, call, new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Shop>> response) {
                List<Shop> shopList = response.getData();
                if (shopList != null && !shopList.isEmpty()) {
                    shopListViewAdapter.setData(shopList);
                    allowFilterCallback = false;
                    shopListViewAdapter.getFilter().filter("");
                    allowFilterCallback = true;

                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                    loadingPanel.setVisibility(View.GONE);
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
}
