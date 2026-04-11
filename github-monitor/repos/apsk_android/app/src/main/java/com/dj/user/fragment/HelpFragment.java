package com.dj.user.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.mine.setting.HelpDetailsActivity;
import com.dj.user.adapter.HelpExpandableListAdapter;
import com.dj.user.databinding.FragmentHelpBinding;
import com.dj.user.model.response.FAQ;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class HelpFragment extends BaseFragment {

    private FragmentHelpBinding binding;
    private Context context;
    private int page = 0;

    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private HelpExpandableListAdapter adapter;

    private List<String> groupList = new ArrayList<>();
    private Map<String, List<FAQ>> childMap = new HashMap<>();

    public HelpFragment newInstance(Context context, int page) {
        HelpFragment fragment = new HelpFragment();
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
        binding = FragmentHelpBinding.inflate(inflater, container, false);
        context = getContext();

        setupUI();
        return binding.getRoot();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        adapter = new HelpExpandableListAdapter(context, groupList, childMap);
        binding.expandableListView.setAdapter(adapter);
        binding.expandableListView.setOnChildClickListener((parent, v, groupPosition, childPosition, id) -> {
            if (groupList != null && groupPosition >= 0 && groupPosition < groupList.size()) {
                List<FAQ> childList = childMap != null ? childMap.get(groupList.get(groupPosition)) : null;
                if (childList != null && childPosition >= 0 && childPosition < childList.size()) {
                    FAQ selectedItem = childList.get(childPosition);
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(selectedItem));
                    ((BaseActivity) context).startAppActivity(new Intent(context, HelpDetailsActivity.class),
                            bundle, false, false, true
                    );
                }
            }
            return true;
        });
    }

    public void updateView(@Nullable List<FAQ> faqList) {
        if (binding == null) {
            return;
        }
        // Hide loading panel first
        loadingPanel.setVisibility(View.GONE);
        if (faqList == null || faqList.isEmpty()) {
            // No data
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.VISIBLE);
            groupList.clear();
            childMap.clear();
            if (adapter != null) adapter.notifyDataSetChanged();
            return;
        }
        // Has data
        noDataPanel.setVisibility(View.GONE);
        dataPanel.setVisibility(View.VISIBLE);
        // Build expandable list data
        groupList.clear();
        childMap.clear();
        for (FAQ faq : faqList) {
            groupList.add(faq.getTitle());
            childMap.put(faq.getTitle(), faq.getChildren());
        }
        // Refresh adapter
        if (adapter != null) {
            adapter.notifyDataSetChanged();
        }
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}