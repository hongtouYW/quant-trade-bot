package com.dj.manager.activity.log;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.TextView;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.LogFilterListViewAdapter;
import com.dj.manager.databinding.ActivityLogFilterBinding;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.enums.SelectionMode;
import com.dj.manager.model.ItemBinder;
import com.dj.manager.model.ItemFilter;
import com.dj.manager.model.request.RequestSystemLog;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.SystemLogFilter;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;
import java.util.Locale;

public class LogFilterActivity extends BaseActivity {
    private ActivityLogFilterBinding binding;
    private Manager manager;
    private ItemFilter selectedItemFilter;
    private LogFilterType logFilterType;
    private LinearLayout searchPanel, dataPanel, noDataPanel, loadingPanel;
    private TextView sectionTitleTextView;
    private ListView logFilterListView;
    private boolean isMultiSelection;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLogFilterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(logFilterType.getTitle()), R.drawable.ic_toolbar_search, view -> {
            if (searchPanel.getVisibility() == View.VISIBLE) {
                searchPanel.setVisibility(View.GONE);
                sectionTitleTextView.setVisibility(View.VISIBLE);
                hideKeyboard(LogFilterActivity.this);
            } else {
                searchPanel.setVisibility(View.VISIBLE);
                sectionTitleTextView.setVisibility(View.GONE);
            }
        });
        setupUI();
        setupSearchPanel();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getSystemLogFilterList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            selectedItemFilter = new Gson().fromJson(json, ItemFilter.class);
            logFilterType = selectedItemFilter.getLogFilterType();
        }
        isMultiSelection = getIntent().getBooleanExtra("isMultiSelection", true);
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
        sectionTitleTextView = binding.textViewSectionTitle;
        logFilterListView = binding.listViewLogFilter;
        binding.buttonConfirm.setOnClickListener(view -> {
            LogFilterListViewAdapter<?> adapter = (LogFilterListViewAdapter<?>) logFilterListView.getAdapter();
            if (adapter != null) {
                List<String> selectedIds = adapter.getSelectedIds();
                Intent resultIntent = new Intent();
                resultIntent.putExtra("ids", new Gson().toJson(selectedIds));
                setResult(RESULT_OK, resultIntent);
            }
            onBaseBackPressed();
        });
        updateTitle(0);
    }

    private void setupSearchPanel() {
        searchPanel = binding.panelSearch;
        binding.editTextSearch.setHint(logFilterType == LogFilterType.SHOP ? getString(R.string.log_filter_shop_search_hint) : getString(R.string.log_filter_manager_search_hint));
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
                if (searchPanel.getVisibility() == View.VISIBLE) {
                    String keyword = editable.toString().trim();
                    LogFilterListViewAdapter<?> adapter = (LogFilterListViewAdapter<?>) logFilterListView.getAdapter();
                    if (adapter != null) {
                        adapter.getFilter().filter(keyword);
                    }
                }
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    public void updateTitle(int count) {
        sectionTitleTextView.setText(String.format(Locale.ENGLISH, logFilterType == LogFilterType.SHOP ? getString(R.string.log_filter_shop_chosen) : getString(R.string.log_filter_manager_chosen), count));
    }

    private void getSystemLogFilterList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestSystemLog request = new RequestSystemLog(manager.getManager_id(), logFilterType.getType());
        executeApiCall(this, apiService.getSystemLogFilterList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<SystemLogFilter> response) {
                boolean hasData = false;
                SystemLogFilter systemLogFilter = response.getData();
                if (systemLogFilter != null) {
                    if (logFilterType == LogFilterType.SHOP) {
                        List<Shop> shopList = systemLogFilter.getShop();
                        if (shopList != null && !shopList.isEmpty()) {
                            boolean isAllSelected = false;
                            if (selectedItemFilter != null && selectedItemFilter.getSelectedIds() != null) {
                                int matchedCount = 0;
                                for (Shop shop : shopList) {
                                    if (selectedItemFilter.getSelectedIds().contains(shop.getShop_id())) {
                                        shop.setSelected(true);
                                        matchedCount++;
                                    }
                                }
                                isAllSelected = matchedCount == shopList.size();
                                updateTitle(selectedItemFilter.getSelectedIds().size());
                            }
                            if (isMultiSelection) {
                                shopList.add(0, new Shop(0, getString(R.string.log_filter_all), isAllSelected));
                            }
                            LogFilterListViewAdapter<Shop> logFilterListViewAdapter =
                                    new LogFilterListViewAdapter<>(LogFilterActivity.this, new ItemBinder<>() {
                                        public String getId(Shop s) {
                                            return s.getShop_id();
                                        }

                                        public String getTitle(Shop s) {
                                            return s.getShop_name();
                                        }

                                        public boolean isSelected(Shop s) {
                                            return s.isSelected();
                                        }

                                        public void setSelected(Shop s, boolean selected) {
                                            s.setSelected(selected);
                                        }
                                    });
                            if (!isMultiSelection) {
                                logFilterListViewAdapter.setSelectionMode(SelectionMode.SINGLE);
                            }
                            logFilterListView.setAdapter(logFilterListViewAdapter);
                            logFilterListViewAdapter.replaceList(shopList);
                            logFilterListViewAdapter.setFilterListener(isEmpty -> {
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
                            logFilterListViewAdapter.setOnItemClickListener((position, object) -> {
                            });
                            hasData = true;
                        }
                    } else if (logFilterType == LogFilterType.MANAGER) {
                        List<Manager> managerList = systemLogFilter.getManager();
                        if (managerList != null && !managerList.isEmpty()) {
                            boolean isAllSelected = false;
                            if (selectedItemFilter != null && selectedItemFilter.getSelectedIds() != null) {
                                int matchedCount = 0;
                                for (Manager manager : managerList) {
                                    if (selectedItemFilter.getSelectedIds().contains(manager.getManager_id())) {
                                        manager.setSelected(true);
                                        matchedCount++;
                                    }
                                }
                                isAllSelected = matchedCount == managerList.size();
                                updateTitle(selectedItemFilter.getSelectedIds().size());
                            }
                            managerList.add(0, new Manager(0, getString(R.string.log_filter_all), isAllSelected));
                            LogFilterListViewAdapter<Manager> logFilterListViewAdapter =
                                    new LogFilterListViewAdapter<>(LogFilterActivity.this, new ItemBinder<>() {
                                        public String getId(Manager s) {
                                            return s.getManager_id();
                                        }

                                        public String getTitle(Manager s) {
                                            return s.getManager_name();
                                        }

                                        public boolean isSelected(Manager s) {
                                            return s.isSelected();
                                        }

                                        public void setSelected(Manager s, boolean selected) {
                                            s.setSelected(selected);
                                        }
                                    });
                            logFilterListView.setAdapter(logFilterListViewAdapter);
                            logFilterListViewAdapter.replaceList(managerList);
                            logFilterListViewAdapter.setFilterListener(isEmpty -> {
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
                            logFilterListViewAdapter.setOnItemClickListener((position, object) -> {
                            });
                            hasData = true;
                        }
                    }
                }
                if (hasData) {
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