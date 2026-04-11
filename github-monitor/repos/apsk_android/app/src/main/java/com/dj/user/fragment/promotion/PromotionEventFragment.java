package com.dj.user.fragment.promotion;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.DashboardActivity;
import com.dj.user.activity.mine.PromotionDetailsActivity;
import com.dj.user.adapter.CategoryListViewAdapter;
import com.dj.user.adapter.PromotionListViewAdapter;
import com.dj.user.databinding.FragmentPromotionEventBinding;
import com.dj.user.enums.PromotionCategory;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestPromotion;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Promotion;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.google.gson.Gson;

import java.util.Arrays;
import java.util.List;

public class PromotionEventFragment extends BaseFragment {

    private FragmentPromotionEventBinding binding;
    private Context context;
    private Member member;
    private PromotionListViewAdapter promotionListViewAdapter;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private PromotionCategory selectedCategory;

    public PromotionEventFragment newInstance(Context context) {
        PromotionEventFragment fragment = new PromotionEventFragment();
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
        binding = FragmentPromotionEventBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupCategoryListView();
        setupPromotionListView();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getPromotionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
    }

    private void setupCategoryListView() {
        List<PromotionCategory> categoryItemList = Arrays.asList(PromotionCategory.values());
        selectedCategory = categoryItemList.get(0);
        CategoryListViewAdapter categoryListViewAdapter = new CategoryListViewAdapter(context);
        categoryListViewAdapter.addList(categoryItemList);
        binding.listViewCategory.setAdapter(categoryListViewAdapter);
        categoryListViewAdapter.setOnItemClickListener((position, object) -> {
            selectedCategory = (PromotionCategory) object;
            categoryListViewAdapter.setSelectedPosition(position);
            getPromotionList();
        });
    }

    private void setupPromotionListView() {
        ListView promotionListView = binding.listViewPromotion;
        promotionListViewAdapter = new PromotionListViewAdapter(context);
        promotionListView.setAdapter(promotionListViewAdapter);
        promotionListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            ((DashboardActivity) context).startAppActivity(new Intent(context, PromotionDetailsActivity.class),
                    bundle, false, false, true);
        });
    }

    private void getPromotionList() {
        if (promotionListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPromotion request = new RequestPromotion(member.getMember_id(), selectedCategory.getValue());
        ((DashboardActivity) context).executeApiCall(context, apiService.getPromotionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Promotion>> response) {
                List<Promotion> promotionList = response.getData();
                if (promotionList != null && !promotionList.isEmpty()) {
                    promotionListViewAdapter.replaceList(promotionList);
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
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }
        }, false);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
