package com.dj.shop.activity.dashboard;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.adapter.SearchListViewAdapter;
import com.dj.shop.databinding.ActivityDashboardSearchBinding;
import com.dj.shop.model.request.RequestMemberSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Member;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;

import java.util.ArrayList;
import java.util.List;

public class DashboardSearchActivity extends BaseActivity {

    ActivityDashboardSearchBinding binding;
    private Shop shop;
    SearchListViewAdapter searchListViewAdapter;
    LinearLayout dataPanel, noDataPanel, loadingPanel;
    private int searchRequestId = 0;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardSearchBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.dashboard_search_title), 0, null);
        setupUI();
        setupSearchPanel();
        setupListView();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
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
                    // Invalidate all pending requests
                    searchRequestId++;

                    searchListViewAdapter.removeList();
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                    return;
                }
                searchListViewAdapter.setKeyword(editable.toString());
                searchMemberList();
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            searchListViewAdapter.removeList();
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void setupListView() {
        ListView searchListView = binding.listViewSearch;
        searchListViewAdapter = new SearchListViewAdapter(this);
        searchListViewAdapter.setOnItemClickListener((position, object) -> {
            Member member = (Member) object;
            Bundle bundle = new Bundle();
            bundle.putString("data", member.getMember_id());
            startAppActivity(new Intent(DashboardSearchActivity.this, DashboardSearchDetailsActivity.class),
                    bundle, false, false, true);
        });
        searchListView.setAdapter(searchListViewAdapter);
    }

    protected void searchMemberList() {
        String search = binding.editTextSearch.getText().toString();
        if (search.isEmpty()) {
            searchListViewAdapter.replaceList(new ArrayList<>());
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.VISIBLE);
            loadingPanel.setVisibility(View.GONE);
            return;
        }
        int currentRequestId = ++searchRequestId;
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberSearch request = new RequestMemberSearch(shop.getShop_id(), search);
        executeApiCall(this, apiService.searchMemberList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Member>> response) {
                // Ignore outdated response
                if (currentRequestId != searchRequestId) {
                    return;
                }
                List<Member> memberList = response.getData();
                if (memberList != null && !memberList.isEmpty()) {
                    searchListViewAdapter.replaceList(memberList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                }
                loadingPanel.setVisibility(View.GONE);
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