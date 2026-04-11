package com.dj.manager.activity.shop;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.ViewPagerAdapter;
import com.dj.manager.databinding.ActivityShopManagementBinding;
import com.dj.manager.fragment.ShopFragment;
import com.dj.manager.model.request.RequestShopProfile;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.ShopPin;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class ShopManagementActivity extends BaseActivity {
    private ActivityShopManagementBinding binding;
    private Manager manager;
    private LinearLayout searchPanel;
    private TabLayout shopTabLayout;
    private ViewPager2 shopViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.shop_list_tab_all,
            R.string.shop_list_tab_active,
            R.string.shop_list_tab_closed,
    };

    private ShopFragment allShopFragment;
    private ShopFragment activeShopFragment;
    private ShopFragment closedShopFragment;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityShopManagementBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_list_title),
                R.drawable.ic_toolbar_search, 0, view -> {
                    if (searchPanel.getVisibility() == View.VISIBLE) {
                        searchPanel.setVisibility(View.GONE);
                        shopTabLayout.setVisibility(View.GONE);
                        hideKeyboard(ShopManagementActivity.this);
                    } else {
                        searchPanel.setVisibility(View.VISIBLE);
                        shopTabLayout.setVisibility(View.VISIBLE);
                    }
                },
                R.drawable.ic_toolbar_add, view ->
                        startAppActivity(new Intent(ShopManagementActivity.this, CreateNewShopActivity.class),
                                null, false, false, false, true)
        );
        setupShopViewPager();
        setupShopTabLayout();
        setupSearchPanel();
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
                filterCurrentFragment(keyword);
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void filterCurrentFragment(String keyword) {
        int currentTab = shopViewPager.getCurrentItem();
        switch (currentTab) {
            case 0:
                allShopFragment.filter(keyword);
                break;
            case 1:
                activeShopFragment.filter(keyword);
                break;
            case 2:
                closedShopFragment.filter(keyword);
                break;
        }
    }

    private void setupShopViewPager() {
        allShopFragment = new ShopFragment().newInstance(this, 1);
        activeShopFragment = new ShopFragment().newInstance(this, 2);
        closedShopFragment = new ShopFragment().newInstance(this, 3);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        allShopFragment,
                        activeShopFragment,
                        closedShopFragment
                }
        ));

        shopViewPager = binding.viewPagerShop;
        shopTabLayout = binding.tabLayoutShop;

        shopViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        shopViewPager.setAdapter(pagerAdapter);
        shopViewPager.setUserInputEnabled(false);
        shopTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                shopViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {

            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {

            }
        });
        shopViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
            @Override
            public void onPageScrolled(int position, float positionOffset, int positionOffsetPixels) {
                super.onPageScrolled(position, positionOffset, positionOffsetPixels);
            }

            @Override
            public void onPageSelected(int position) {
                super.onPageSelected(position);
                updateTab(position);
            }

            @Override
            public void onPageScrollStateChanged(int state) {
                super.onPageScrollStateChanged(state);
            }
        });
    }

    private void setupShopTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int i = 0; i < TAB_TITLE.length; i++) {
            shopTabLayout.addTab(shopTabLayout.newTab().setText(TAB_TITLE[i]));

            View tabView = View.inflate(this, R.layout.content_shop_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            TabLayout.Tab tabLayoutTab = shopTabLayout.getTabAt(i);
            tabTitleList.add(tabTextView);
            if (tabLayoutTab != null) {
                tabLayoutTab.setCustomView(tabView);
            }
        }
        shopViewPager.setCurrentItem(0);
        updateTab(0);
    }

    private void updateTab(int position) {
        hideKeyboard(this);
        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));
            tabTextView.setBackgroundColor(ContextCompat.getColor(this, android.R.color.transparent));
        }
        tabTitleList.get(position).setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        tabTitleList.get(position).setBackgroundResource(R.drawable.bg_tab_selected);

        String keyword = binding.editTextSearch.getText().toString().trim();
        filterCurrentFragment(keyword);
    }

    public void pinUnpinShop(Shop shop) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());
        if (!shop.isPinned()) {
            executeApiCall(this, apiService.pinShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<ShopPin> response) {
                }

                @Override
                public boolean onApiError(int code, String message) {
                    return false;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    return false;
                }
            }, true);
        } else {
            executeApiCall(this, apiService.unpinShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                }

                @Override
                public boolean onApiError(int code, String message) {
                    return false;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    return false;
                }
            }, true);
        }
    }
}