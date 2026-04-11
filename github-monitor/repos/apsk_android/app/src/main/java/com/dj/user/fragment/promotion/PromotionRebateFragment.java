package com.dj.user.fragment.promotion;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.adapter.CategoryListViewAdapter;
import com.dj.user.adapter.RebateListViewAdapter;
import com.dj.user.databinding.FragmentPromotionRebateBinding;
import com.dj.user.enums.PromotionCategory;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.Rebate;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class PromotionRebateFragment extends BaseFragment {

    private FragmentPromotionRebateBinding binding;
    private Context context;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;

    public PromotionRebateFragment newInstance(Context context) {
        PromotionRebateFragment fragment = new PromotionRebateFragment();
        fragment.context = context;
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
        binding = FragmentPromotionRebateBinding.inflate(inflater, container, false);
        context = getContext();

        setupUI();
        setupCategoryListView();
        setupRebateListView();
        return binding.getRoot();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
    }

    private void setupCategoryListView() {
        List<PromotionCategory> categoryItemList = Arrays.asList(PromotionCategory.values());
        CategoryListViewAdapter categoryListViewAdapter = new CategoryListViewAdapter(context);
        categoryListViewAdapter.addList(categoryItemList);
        binding.listViewCategory.setAdapter(categoryListViewAdapter);
        categoryListViewAdapter.setOnItemClickListener((position, object) -> {
            categoryListViewAdapter.setSelectedPosition(position);
        });
    }

    private void setupRebateListView() {
        List<Rebate> rebateList = new ArrayList<>();
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());
        rebateList.add(new Rebate());

        ListView rebateListView = binding.listViewRebate;
        RebateListViewAdapter rebateListViewAdapter = new RebateListViewAdapter(context);
        rebateListView.setAdapter(rebateListViewAdapter);
        rebateListViewAdapter.replaceList(rebateList);
        rebateListViewAdapter.setOnItemClickListener((position, object) -> {
        });

        dataPanel.setVisibility(View.VISIBLE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.GONE);
    }
}
