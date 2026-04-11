package com.dj.manager.activity.yxi;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.Toast;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.YxiGridItemAdapter;
import com.dj.manager.databinding.ActivityYxiListBinding;
import com.dj.manager.model.ItemFilter;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.YxiProvider;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.List;

public class YxiListActivity extends BaseActivity {
    private ActivityYxiListBinding binding;
    private Manager manager;
    private Member member;
    private LinearLayout searchPanel, dataPanel, noDataPanel, loadingPanel;
    private YxiGridItemAdapter yxiGridItemAdapter;
    private ItemFilter selectedItemFilter;
    private boolean isFilter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupUI();
        setupToolbar(binding.toolbar.getRoot(), isFilter ? getString(R.string.yxi_log_filter_yxi) : getString(R.string.yxi_title), R.drawable.ic_search, view -> {
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

    private void parseIntentData() {
        isFilter = getIntent().getBooleanExtra("isFilter", false);
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            if (isFilter) {
                selectedItemFilter = new Gson().fromJson(json, ItemFilter.class);
            } else {
                member = new Gson().fromJson(json, Member.class);
            }
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        getYxiProviderList();
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
                binding.imageViewClear.setVisibility(editable.length() > 0 ? View.VISIBLE : View.GONE);
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

    private void setupUI() {
        searchPanel = binding.panelSearch;
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        binding.buttonCreate.setText(isFilter ? getString(R.string.yxi_log_filter_confirm) : getString(R.string.yxi_next));
        binding.buttonCreate.setOnClickListener(view -> {
            YxiProvider selected = yxiGridItemAdapter.getSelectedYxiProvider();
            if (selected == null) {
                Toast.makeText(this, getString(R.string.yxi_select_yxi), Toast.LENGTH_SHORT).show();
                return;
            }
            if (isFilter) {
                List<String> selectedIds = new ArrayList<>();
                selectedIds.add(selected.getProvider_id());
                Intent resultIntent = new Intent();
                resultIntent.putExtra("ids", new Gson().toJson(selectedIds));
                setResult(RESULT_OK, resultIntent);
                onBaseBackPressed();
                return;
            }
            Bundle bundle = new Bundle();
            bundle.putString("member", new Gson().toJson(member));
            bundle.putString("data", new Gson().toJson(selected));
            startAppActivity(new Intent(YxiListActivity.this, YxiPlayerListActivity.class),
                    bundle, false, false, false, true
            );
        });
    }

    private void getYxiProviderList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(manager.getManager_id());
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
}