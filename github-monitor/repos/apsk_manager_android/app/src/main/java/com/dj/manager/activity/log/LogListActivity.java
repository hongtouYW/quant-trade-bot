package com.dj.manager.activity.log;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.LogListViewAdapter;
import com.dj.manager.databinding.ActivityLogListBinding;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.model.ItemFilter;
import com.dj.manager.model.request.RequestSystemLog;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.SystemLog;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class LogListActivity extends BaseActivity {
    private ActivityLogListBinding binding;
    private Manager manager;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private LogListViewAdapter logListViewAdapter;
    private LogFilterType logFilterType = LogFilterType.SYSTEM_ALL;
    private boolean skipOnResumeRefresh = false;
    private ItemFilter selectedItemFilter;

    private final ActivityResultLauncher<Intent> logFilterLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(), result -> {
                skipOnResumeRefresh = true; // prevent onResume from refreshing
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String json = result.getData().getStringExtra("data");
                    if (json != null && !json.isEmpty()) {
                        selectedItemFilter = new Gson().fromJson(json, ItemFilter.class);
                        if (selectedItemFilter != null) {
                            logFilterType = selectedItemFilter.getLogFilterType();
                            updateFilterToolbar(binding.toolbar.getRoot(), logFilterType, logFilterType == LogFilterType.SYSTEM_ALL);
                            getSystemLogList();
                        }
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLogListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.log_title), R.drawable.ic_toolbar_filter, R.drawable.ic_toolbar_filter_selected, view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(selectedItemFilter));
            Intent intent = new Intent(LogListActivity.this, LogFilterMainActivity.class);
            intent.putExtras(bundle);
            logFilterLauncher.launch(intent);
        });
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (!skipOnResumeRefresh) {
            getSystemLogList();
        }
        skipOnResumeRefresh = false;
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        ListView logListView = binding.listViewLog;
        logListViewAdapter = new LogListViewAdapter(this);
        logListView.setAdapter(logListViewAdapter);
        logListViewAdapter.setOnItemClickListener((position, object) -> {
        });
    }

    private void getSystemLogList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        List<String> managerIds = null;
        List<String> shopIds = null;
        if (selectedItemFilter != null) {
            if (selectedItemFilter.getLogFilterType() == LogFilterType.SHOP) {
                shopIds = selectedItemFilter.getSelectedIds();
            } else if (selectedItemFilter.getLogFilterType() == LogFilterType.MANAGER) {
                managerIds = selectedItemFilter.getSelectedIds();
            }
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestSystemLog request = new RequestSystemLog(manager.getManager_id(), logFilterType.getType(), managerIds, shopIds);
        executeApiCall(this, apiService.getSystemLogList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<SystemLog>> response) {
                List<SystemLog> systemLogList = response.getData();
                if (systemLogList != null && !systemLogList.isEmpty()) {
                    logListViewAdapter.replaceList(systemLogList);

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