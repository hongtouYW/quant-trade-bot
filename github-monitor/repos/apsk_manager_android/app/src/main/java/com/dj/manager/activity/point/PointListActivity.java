package com.dj.manager.activity.point;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.PointListViewAdapter;
import com.dj.manager.databinding.ActivityPointBinding;
import com.dj.manager.model.request.RequestPointFilter;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Point;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class PointListActivity extends BaseActivity {
    private ActivityPointBinding binding;
    private Manager manager;
    private RequestPointFilter request;
    private LinearLayout searchPanel;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private PointListViewAdapter pointListViewAdapter;
    private boolean skipOnResumeRefresh = false;

    private final ActivityResultLauncher<Intent> filterLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(), result -> {
                skipOnResumeRefresh = true; // prevent onResume from refreshing
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String json = result.getData().getStringExtra("data");
                    boolean hasFilter = result.getData().getBooleanExtra("hasFilter", false);
                    updateButtonsToolbar(binding.toolbar.getRoot(), hasFilter);
                    if (json != null && !json.isEmpty()) {
                        request = new Gson().fromJson(json, RequestPointFilter.class);
                        getPointList();
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPointBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.point_history_title),
                R.drawable.ic_toolbar_filter, R.drawable.ic_toolbar_filter_selected, view -> {
                    Intent intent = new Intent(this, PointFilterActivity.class);
                    if (request != null) {
                        intent.putExtra("data", new Gson().toJson(request));
                    }
                    filterLauncher.launch(intent);
                },
                R.drawable.ic_toolbar_search, view -> {
                    if (searchPanel.getVisibility() == View.VISIBLE) {
                        searchPanel.setVisibility(View.GONE);
                        hideKeyboard(PointListActivity.this);
                    } else {
                        searchPanel.setVisibility(View.VISIBLE);
                    }
                }
        );
        setupUI();
        setupSearchPanel();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (!skipOnResumeRefresh) {
            getPointList();
        }
        skipOnResumeRefresh = false;
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        ListView pointListView = binding.listViewPoint;
        pointListViewAdapter = new PointListViewAdapter(this);
        pointListView.setAdapter(pointListViewAdapter);

        pointListViewAdapter.setFilterListener(isEmpty -> {
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
        pointListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            startAppActivity(new Intent(this, PointsDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    private void setupSearchPanel() {
        searchPanel = binding.panelSearch;
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
                String keyword = editable.toString().trim();
                filter(keyword);
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    public void filter(String keyword) {
        if (pointListViewAdapter != null) {
            pointListViewAdapter.getFilter().filter(keyword);
        }
    }

    private void getPointList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        if (request == null) {
            request = new RequestPointFilter(manager.getManager_id());
        }
        executeApiCall(this, apiService.getPointList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Point>> response) {
                List<Point> pointList = response.getData();
                if (pointList != null && !pointList.isEmpty()) {
                    pointListViewAdapter.setData(pointList);
//                    String keyword = getKeyword();
                    pointListViewAdapter.getFilter().filter("");
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