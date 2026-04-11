package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.adapter.AffiliateRatioListViewAdapter;
import com.dj.user.adapter.ItemCategoryListViewAdapter;
import com.dj.user.databinding.FragmentAffiliateRatioBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemCategory;
import com.dj.user.model.ItemRatio;

import java.util.ArrayList;
import java.util.List;

public class AffiliateRatioFragment extends BaseFragment {

    private FragmentAffiliateRatioBinding binding;
    private Context context;

    public AffiliateRatioFragment newInstance(Context context) {
        AffiliateRatioFragment fragment = new AffiliateRatioFragment();
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
        binding = FragmentAffiliateRatioBinding.inflate(inflater, container, false);
        context = getContext();

        List<ItemCategory> categoryItemList = new ArrayList<>();
        categoryItemList.add(new ItemCategory("棋牌"));
        categoryItemList.add(new ItemCategory("捕鱼"));
        categoryItemList.add(new ItemCategory("电子"));
        categoryItemList.add(new ItemCategory("真人"));
        categoryItemList.add(new ItemCategory("体育"));
        categoryItemList.add(new ItemCategory("斗鸡"));
        categoryItemList.add(new ItemCategory("热门"));

        ItemCategoryListViewAdapter itemCategoryListViewAdapter = new ItemCategoryListViewAdapter(context);
        itemCategoryListViewAdapter.addList(categoryItemList);
        binding.listViewCategory.setAdapter(itemCategoryListViewAdapter);
        itemCategoryListViewAdapter.setOnItemClickListener((position, object) -> {
            itemCategoryListViewAdapter.setSelectedPosition(position);
        });

        List<ItemRatio> ratioItemList = new ArrayList<>();
        ratioItemList.add(new ItemRatio(true));
        ratioItemList.add(new ItemRatio("0", "1.00", "25.00"));
        ratioItemList.add(new ItemRatio("0", "3.00", "30.00"));
        ratioItemList.add(new ItemRatio("0", "6.00", "35.00"));
        ratioItemList.add(new ItemRatio("0", "10.00", "40.00"));
        ratioItemList.add(new ItemRatio("0", "20.00", "45.00"));
        ratioItemList.add(new ItemRatio("0", "40.00", "50.00"));
        ratioItemList.add(new ItemRatio("0", "60.00", "55.00"));
        ratioItemList.add(new ItemRatio("0", "80.00", "60.00"));
        ratioItemList.add(new ItemRatio("0", "100.00", "65.00"));
        ratioItemList.add(new ItemRatio("0", "150.00", "70.00"));
        ratioItemList.add(new ItemRatio("0", "300.00", "80.00"));
        ratioItemList.add(new ItemRatio("0", "500.00", "90.00"));
        ratioItemList.add(new ItemRatio("0", "800.00", "100.00"));
        ratioItemList.add(new ItemRatio("0", "1000.00", "150.00"));
        ratioItemList.add(new ItemRatio("0", "1500.00", "200.00"));

        AffiliateRatioListViewAdapter affiliateRatioListViewAdapter = new AffiliateRatioListViewAdapter(context);
        affiliateRatioListViewAdapter.addList(ratioItemList);
        binding.listViewRatio.setAdapter(affiliateRatioListViewAdapter);
        affiliateRatioListViewAdapter.setOnItemClickListener((position, object) -> {
        });

        return binding.getRoot();
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}