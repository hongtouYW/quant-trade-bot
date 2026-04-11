package com.dj.manager.activity.dashboard;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.SpannableString;
import android.text.Spanned;
import android.text.TextWatcher;
import android.text.style.ForegroundColorSpan;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.shop.ShopDetailsActivity;
import com.dj.manager.activity.user.UserDetailsActivity;
import com.dj.manager.adapter.ShopSearchListViewAdapter;
import com.dj.manager.adapter.UserSearchListViewAdapter;
import com.dj.manager.databinding.ActivityDashboardSearchBinding;
import com.dj.manager.model.ItemChip;
import com.dj.manager.model.request.RequestSearch;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.StringUtil;
import com.dj.manager.widget.ExpandableHeightListView;
import com.google.android.flexbox.FlexboxLayout;
import com.google.gson.Gson;

import java.util.Arrays;
import java.util.List;
import java.util.Locale;

public class DashboardSearchActivity extends BaseActivity {
    private ActivityDashboardSearchBinding binding;
    private Manager manager;
    private ItemChip selectedChip;
    private String keyword;
    private ScrollView scrollView;
    private LinearLayout memberDataPanel, shopDataPanel, noDataPanel, loadingPanel;
    private UserSearchListViewAdapter userSearchListViewAdapter;
    private ShopSearchListViewAdapter shopSearchListViewAdapter;
    private int searchRequestId = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardSearchBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.dashboard_search_title), 0, null);
        setupSearchPanel();
        setupUI();
        setupFilter();
        updateKeyword("");
    }

    private void updateKeyword(String keyword) {
        this.keyword = keyword;
        // Invalidate all pending requests when cleared
        if (keyword.isEmpty()) {
            searchRequestId++;

            userSearchListViewAdapter.removeList();
            shopSearchListViewAdapter.removeList();

            scrollView.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.GONE);
            binding.panelKeyword.setVisibility(View.GONE);
            return;
        }
        userSearchListViewAdapter.setKeyword(keyword);
        shopSearchListViewAdapter.setKeyword(keyword);

        binding.panelKeyword.setVisibility(keyword.isEmpty() ? View.GONE : View.VISIBLE);
        binding.textViewKeyword.setText(String.format(getString(R.string.template_s), keyword));
        String text = String.format(Locale.ENGLISH, getString(R.string.dashboard_search_no_result_template), keyword);
        SpannableString spannable = new SpannableString(text);
        int start = text.indexOf(keyword);
        if (start >= 0) {
            int end = start + keyword.length();
            spannable.setSpan(
                    new ForegroundColorSpan(ContextCompat.getColor(this, R.color.white_FFFFFF)),
                    start,
                    end,
                    Spanned.SPAN_EXCLUSIVE_EXCLUSIVE
            );
        }
        binding.textViewNoData.setText(spannable);
    }

    private void updateSearchResult() {
        binding.textViewMemberResult.setText(String.format(Locale.ENGLISH, getString(R.string.dashboard_search_result_count), keyword, userSearchListViewAdapter.getCount()));
        binding.textViewShopResult.setText(String.format(Locale.ENGLISH, getString(R.string.dashboard_search_result_count), keyword, shopSearchListViewAdapter.getCount()));
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
                if (editable.length() > 0) {
                    binding.imageViewClear.setVisibility(View.VISIBLE);
                } else {
                    binding.imageViewClear.setVisibility(View.GONE);
                }
                updateKeyword(editable.toString().trim());
                search();
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            userSearchListViewAdapter.removeList();
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void setupFilter() {
        FlexboxLayout flexboxLayout = binding.flexboxLayout;
        LayoutInflater inflater = LayoutInflater.from(this);
        List<ItemChip> options = Arrays.asList(
                new ItemChip(getString(R.string.dashboard_search_filter_all), "all"),
                new ItemChip(getString(R.string.dashboard_search_filter_member), "member"),
                new ItemChip(getString(R.string.dashboard_search_filter_shop), "shop")
        );
        for (ItemChip chip : options) {
            View chipView = inflater.inflate(R.layout.item_chip_filter, flexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(this, R.color.gray_D9D9D9));
            chipView.setOnClickListener(v -> {
                for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                    View child = flexboxLayout.getChildAt(i);
                    TextView textView = child.findViewById(R.id.chip_label);

                    textView.setTextColor(ContextCompat.getColor(this, R.color.gray_D9D9D9));
                }
                chipLabel.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
                selectedChip = chip;
                search();
            });
            flexboxLayout.addView(chipView);
        }
        if (flexboxLayout.getChildCount() > 0) {
            flexboxLayout.getChildAt(0).performClick();
        }
    }

    private void setupUI() {
        scrollView = binding.scrollView;
        memberDataPanel = binding.panelMemberData;
        shopDataPanel = binding.panelShopData;
        noDataPanel = binding.panelNoData;
        loadingPanel = binding.panelLoading.getRoot();

        ExpandableHeightListView memberListView = binding.listViewSearchMember;
        userSearchListViewAdapter = new UserSearchListViewAdapter(this);
        memberListView.setExpanded(true);
        memberListView.setAdapter(userSearchListViewAdapter);
        userSearchListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            startAppActivity(new Intent(DashboardSearchActivity.this, UserDetailsActivity.class),
                    bundle, false, false, false, true);
        });

        ExpandableHeightListView shopListView = binding.listViewSearchShop;
        shopSearchListViewAdapter = new ShopSearchListViewAdapter(this);
        shopListView.setExpanded(true);
        shopListView.setAdapter(shopSearchListViewAdapter);
        shopSearchListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            startAppActivity(new Intent(DashboardSearchActivity.this, ShopDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    private void search() {
        if (StringUtil.isNullOrEmpty(keyword)) {
            scrollView.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.GONE);
            return;
        }
        int currentRequestId = ++searchRequestId;
        scrollView.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestSearch request = new RequestSearch(manager.getManager_id(), selectedChip.getType(), keyword);
        executeApiCall(this, apiService.searchMemberShop(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                // Ignore outdated response
                if (currentRequestId != searchRequestId) {
                    return;
                }
                boolean isNoData = true;
                List<Member> memberList = response.getMember();
                if (memberList != null && !memberList.isEmpty()) {
                    memberDataPanel.setVisibility(View.VISIBLE);
                    userSearchListViewAdapter.replaceList(memberList);
                    isNoData = false;
                } else {
                    memberDataPanel.setVisibility(View.GONE);
                }
                List<Shop> shopList = response.getShop();
                if (shopList != null && !shopList.isEmpty()) {
                    shopDataPanel.setVisibility(View.VISIBLE);
                    shopSearchListViewAdapter.replaceList(shopList);
                    isNoData = false;
                } else {
                    shopDataPanel.setVisibility(View.GONE);
                }
                updateSearchResult();
                if (isNoData) {
                    scrollView.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                } else {
                    scrollView.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                }
                loadingPanel.setVisibility(View.GONE);
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