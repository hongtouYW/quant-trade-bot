package com.dj.manager.activity.log;

import android.content.Intent;
import android.os.Bundle;
import android.widget.ListView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.yxi.YxiListActivity;
import com.dj.manager.adapter.LogFilterMainListViewAdapter;
import com.dj.manager.databinding.ActivityLogFilterMainBinding;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.model.ItemFilter;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.Locale;

public class YxiLogFilterMainActivity extends BaseActivity {
    private ActivityLogFilterMainBinding binding;
    private ItemFilter selectedItemFilter;
    private LogFilterMainListViewAdapter logFilterMainListViewAdapter;
    private final ActivityResultLauncher<Intent> logFilterLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String jsonIds = result.getData().getStringExtra("ids");
                    if (jsonIds != null && !jsonIds.isEmpty()) {
                        String[] idsArray = new Gson().fromJson(jsonIds, String[].class);
                        List<String> selectedIds = idsArray != null ? new ArrayList<>(Arrays.asList(idsArray)) : new ArrayList<>();
                        if (!selectedIds.isEmpty()) {
                            selectedItemFilter = logFilterMainListViewAdapter.getSelectedItem();
                            selectedItemFilter.setDesc(String.format(Locale.ENGLISH, getString(R.string.log_filter_chosen_template), selectedIds.size(), getString(selectedItemFilter.getLogFilterType().getDesc())));
                            selectedItemFilter.setSelectedIds(selectedIds);

                            Intent resultIntent = new Intent();
                            resultIntent.putExtra("data", new Gson().toJson(selectedItemFilter));
                            setResult(RESULT_OK, resultIntent);
                            logFilterMainListViewAdapter.notifyDataSetChanged();
                        }
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLogFilterMainBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.log_filter_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            selectedItemFilter = new Gson().fromJson(json, ItemFilter.class);
        }
    }

    private void setupUI() {
        List<ItemFilter> itemFilterList = new ArrayList<>();
        itemFilterList.add(new ItemFilter(LogFilterType.YXI_ALL, "", true));
        itemFilterList.add(new ItemFilter(LogFilterType.SHOP, getString(R.string.yxi_log_filter_choose_shop), false));
        itemFilterList.add(new ItemFilter(LogFilterType.YXI, getString(R.string.yxi_log_filter_choose_yxi), false));
        ListView logMainFilterListView = binding.listViewLogFilterMain;
        logFilterMainListViewAdapter = new LogFilterMainListViewAdapter(this, true);
        logMainFilterListView.setAdapter(logFilterMainListViewAdapter);
        logFilterMainListViewAdapter.setOnItemClickListener((position, object) -> {
            selectedItemFilter = (ItemFilter) object;
            Intent resultIntent = new Intent();
            resultIntent.putExtra("data", new Gson().toJson(selectedItemFilter));
            setResult(RESULT_OK, resultIntent);
        });
        if (selectedItemFilter != null) {
            for (int i = 0; i < itemFilterList.size(); i++) {
                ItemFilter item = itemFilterList.get(i);
                item.setSelected(false);
                if (item.getLogFilterType() == selectedItemFilter.getLogFilterType()) {
                    itemFilterList.set(i, selectedItemFilter);
                }
            }
        }
        logFilterMainListViewAdapter.replaceList(itemFilterList);
    }

    public void navigateToLogFilter(ItemFilter itemFilter) {
        if (itemFilter.getLogFilterType() == LogFilterType.YXI_ALL) {
            return;
        }
        if (itemFilter.getLogFilterType() == LogFilterType.SHOP) {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(itemFilter));
            bundle.putBoolean("isMultiSelection", false);
            Intent intent = new Intent(YxiLogFilterMainActivity.this, LogFilterActivity.class);
            intent.putExtras(bundle);
            logFilterLauncher.launch(intent);
            return;
        }
        Bundle bundle = new Bundle();
        bundle.putBoolean("isFilter", true);
        Intent intent = new Intent(YxiLogFilterMainActivity.this, YxiListActivity.class);
        intent.putExtras(bundle);
        logFilterLauncher.launch(intent);
    }
}