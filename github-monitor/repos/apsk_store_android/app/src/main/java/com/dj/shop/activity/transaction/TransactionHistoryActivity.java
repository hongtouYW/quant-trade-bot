package com.dj.shop.activity.transaction;

import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.adapter.ViewPagerAdapter;
import com.dj.shop.databinding.ActivityTransactionHistoryBinding;
import com.dj.shop.fragment.HistoryFragment;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.CacheManager;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.List;

public class TransactionHistoryActivity extends BaseActivity {
    ActivityTransactionHistoryBinding binding;
    Shop shop;
    private LinearLayout searchPanel;
    private TabLayout transactionTabLayout;
    ViewPager2 transactionViewPager;
    private List<TextView> tabTitleList;

    private int[] tabTitles;

    private HistoryFragment historyFragment;
    private HistoryFragment userInTransactionFragment;
    private HistoryFragment userOutTransactionFragment;
    private HistoryFragment settlementTransactionFragment;
    private HistoryFragment allTransactionFragment;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionHistoryBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.transaction_title),
                R.drawable.ic_toolbar_search, view -> {
                    if (searchPanel.getVisibility() == View.VISIBLE) {
                        searchPanel.setVisibility(View.GONE);
                        hideKeyboard(TransactionHistoryActivity.this);
                    } else {
                        searchPanel.setVisibility(View.VISIBLE);
                    }
                });
        setupTabTitles();
        setupTransactionViewPager();
        setupTransactionTabLayout();
        setupSearchPanel();
    }

    private void setupTabTitles() {
        if (shop.isReadClear()) {
            tabTitles = new int[]{
                    R.string.transaction_tab_user,
                    R.string.transaction_tab_top_up,
                    R.string.transaction_tab_withdraw,
                    R.string.transaction_tab_manager_settlement,
                    R.string.transaction_tab_all
            };
        } else {
            tabTitles = new int[]{
                    R.string.transaction_tab_user,
                    R.string.transaction_tab_top_up,
                    R.string.transaction_tab_withdraw,
                    R.string.transaction_tab_all,
            };
        }
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

    protected void filterCurrentFragment(@Nullable String keyword) {
        int currentTab = transactionViewPager.getCurrentItem();
        if (shop.isReadClear()) {
            switch (currentTab) {
                case 0:
                    if (historyFragment != null) {
                        historyFragment.filter(keyword);
                    }
                    break;
                case 1:
                    if (userInTransactionFragment != null) {
                        userInTransactionFragment.filter(keyword);
                    }
                    break;
                case 2:
                    if (userOutTransactionFragment != null) {
                        userOutTransactionFragment.filter(keyword);
                    }
                    break;
                case 3:
                    if (settlementTransactionFragment != null) {
                        settlementTransactionFragment.filter(keyword);
                    }
                    break;
                default:
                    if (allTransactionFragment != null) {
                        allTransactionFragment.filter(keyword);
                    }
                    break;
            }
        } else {
            switch (currentTab) {
                case 0:
                    if (historyFragment != null) {
                        historyFragment.filter(keyword);
                    }
                    break;
                case 1:
                    if (userInTransactionFragment != null) {
                        userInTransactionFragment.filter(keyword);
                    }
                    break;
                case 2:
                    if (userOutTransactionFragment != null) {
                        userOutTransactionFragment.filter(keyword);
                    }
                    break;
                default:
                    if (allTransactionFragment != null) {
                        allTransactionFragment.filter(keyword);
                    }
                    break;
            }
        }
    }

    private void setupTransactionViewPager() {
        List<Fragment> fragmentList = new ArrayList<>();
        if (shop.isReadClear()) {
            historyFragment = new HistoryFragment().newInstance(this, 0);
            userInTransactionFragment = new HistoryFragment().newInstance(this, 1);
            userOutTransactionFragment = new HistoryFragment().newInstance(this, 2);
            settlementTransactionFragment = new HistoryFragment().newInstance(this, 3);
            allTransactionFragment = new HistoryFragment().newInstance(this, 4);

            fragmentList.add(historyFragment);
            fragmentList.add(userInTransactionFragment);
            fragmentList.add(userOutTransactionFragment);
            fragmentList.add(settlementTransactionFragment);
            fragmentList.add(allTransactionFragment);

        } else {
            historyFragment = new HistoryFragment().newInstance(this, 0);
            userInTransactionFragment = new HistoryFragment().newInstance(this, 1);
            userOutTransactionFragment = new HistoryFragment().newInstance(this, 2);
            allTransactionFragment = new HistoryFragment().newInstance(this, 3);

            fragmentList.add(historyFragment);
            fragmentList.add(userInTransactionFragment);
            fragmentList.add(userOutTransactionFragment);
            fragmentList.add(allTransactionFragment);
        }
        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), fragmentList);
        transactionViewPager = binding.viewPagerTransaction;
        transactionTabLayout = binding.tabLayoutTransaction;

        transactionViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        transactionViewPager.setAdapter(pagerAdapter);
        transactionViewPager.setUserInputEnabled(false);
        transactionTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                transactionViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {

            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {

            }
        });
        transactionViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupTransactionTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int i = 0; i < tabTitles.length; i++) {
            transactionTabLayout.addTab(transactionTabLayout.newTab().setText(tabTitles[i]));

            View tabView = View.inflate(this, R.layout.content_transaction_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            TabLayout.Tab tabLayoutTab = transactionTabLayout.getTabAt(i);
            tabTitleList.add(tabTextView);
            if (tabLayoutTab != null) {
                tabLayoutTab.setCustomView(tabView);
            }
        }
        transactionViewPager.setCurrentItem(0);
        updateTab(0);
    }

    protected void updateTab(int position) {
        hideKeyboard(this);
        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(getString(tabTitles[i]));
            tabTextView.setTextColor(ContextCompat.getColor(this, R.color.gray_D9D9D9));
        }
        tabTitleList.get(position).setTextColor(ContextCompat.getColor(this, R.color.gold_FFCB22));

        String keyword = binding.editTextSearch.getText().toString().trim();
        filterCurrentFragment(keyword);
    }
}