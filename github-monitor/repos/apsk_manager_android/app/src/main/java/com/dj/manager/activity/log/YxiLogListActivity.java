package com.dj.manager.activity.log;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.AbsListView;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.YxiLogListViewAdapter;
import com.dj.manager.databinding.ActivityYxiLogListBinding;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.model.ItemFilter;
import com.dj.manager.model.request.RequestYxiLog;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Pagination;
import com.dj.manager.model.response.Transaction;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class YxiLogListActivity extends BaseActivity {
    private ActivityYxiLogListBinding binding;
    private Manager manager;
    private ListView logListView;
    private View footerLoadingView;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private YxiLogListViewAdapter yxiLogListViewAdapter;
    private LogFilterType logFilterType = LogFilterType.SYSTEM_ALL;
    private ItemFilter selectedItemFilter;
    private boolean skipOnResumeRefresh = false;
    private int apiPage = 1;
    private boolean isLoading = false;
    private boolean hasNextPage = true;

    private final ActivityResultLauncher<Intent> logFilterLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(), result -> {
                skipOnResumeRefresh = true; // prevent onResume from refreshing
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String json = result.getData().getStringExtra("data");
                    if (json != null && !json.isEmpty()) {
                        selectedItemFilter = new Gson().fromJson(json, ItemFilter.class);
                        if (selectedItemFilter != null) {
                            logFilterType = selectedItemFilter.getLogFilterType();
                            updateFilterToolbar(binding.toolbar.getRoot(), logFilterType, logFilterType == LogFilterType.YXI_ALL);
                            resetAndLoad();
                        }
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiLogListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.yxi_log_title), R.drawable.ic_toolbar_filter, R.drawable.ic_toolbar_filter_selected, view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(selectedItemFilter));
            Intent intent = new Intent(YxiLogListActivity.this, YxiLogFilterMainActivity.class);
            intent.putExtras(bundle);
            logFilterLauncher.launch(intent);
        });
        setupUI();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (!skipOnResumeRefresh) {
            resetAndLoad();
        }
        skipOnResumeRefresh = false;
    }

    private void resetAndLoad() {
        apiPage = 1;
        hasNextPage = true;
        isLoading = false;
        yxiLogListViewAdapter.removeList();
        getYxiLogList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        logListView = binding.listViewLog;
        footerLoadingView = LayoutInflater.from(this).inflate(R.layout.footer_loading, logListView, false);
        yxiLogListViewAdapter = new YxiLogListViewAdapter(this);
        logListView.setAdapter(yxiLogListViewAdapter);
        logListView.setOnScrollListener(new AbsListView.OnScrollListener() {
            @Override
            public void onScrollStateChanged(AbsListView view, int scrollState) {
            }

            @Override
            public void onScroll(AbsListView view, int firstVisibleItem, int visibleItemCount, int totalItemCount) {
                if (totalItemCount == 0) return;
                int lastVisibleItem = firstVisibleItem + visibleItemCount;
                if (!isLoading && hasNextPage && lastVisibleItem >= totalItemCount - 1) {
                    showFooterLoading();
                    getYxiLogList();
                }
            }
        });
        yxiLogListViewAdapter.setOnItemClickListener((position, object) -> {
        });
    }

    private void showFooterLoading() {
        if (logListView.getFooterViewsCount() == 0) {
            logListView.addFooterView(footerLoadingView);
        }
    }

    private void hideFooterLoading() {
        if (logListView.getFooterViewsCount() > 0) {
            logListView.removeFooterView(footerLoadingView);
        }
    }

    private void getYxiLogList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        String shopId = null, providerId = null;
        List<String> shopIds, yxiIds;
        if (selectedItemFilter != null) {
            if (selectedItemFilter.getLogFilterType() == LogFilterType.SHOP) {
                shopIds = selectedItemFilter.getSelectedIds();
                if (shopIds != null && !shopIds.isEmpty()) {
                    shopId = shopIds.get(0);
                }
            } else if (selectedItemFilter.getLogFilterType() == LogFilterType.YXI) {
                yxiIds = selectedItemFilter.getSelectedIds();
                if (yxiIds != null && !yxiIds.isEmpty()) {
                    providerId = yxiIds.get(0);
                }
            }
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestYxiLog request = new RequestYxiLog(manager.getManager_id(), shopId, providerId, apiPage);
        executeApiCall(this, apiService.getYxiLogList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Transaction>> response) {
                isLoading = false;
                hideFooterLoading();
                List<Transaction> transactionList = response.getHistory();
                Pagination pagination = response.getHistorypagination();
                hasNextPage = pagination != null && pagination.isHasnextpage();
                if (hasNextPage) {
                    apiPage++;
                }
                if (transactionList != null && !transactionList.isEmpty()) {
                    yxiLogListViewAdapter.addList(transactionList);
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
                isLoading = false;
                hideFooterLoading();
                if (apiPage == 1) {
                    loadingPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                isLoading = false;
                hideFooterLoading();
                if (apiPage == 1) {
                    loadingPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
                return false;
            }
        }, false);
    }
}