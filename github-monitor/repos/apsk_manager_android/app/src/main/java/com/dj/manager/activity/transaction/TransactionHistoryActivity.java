package com.dj.manager.activity.transaction;

import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.ViewPagerAdapter;
import com.dj.manager.databinding.ActivityTransactionHistoryBinding;
import com.dj.manager.fragment.TransactionFragment;
import com.dj.manager.model.response.Shop;
import com.google.android.material.tabs.TabLayout;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class TransactionHistoryActivity extends BaseActivity {
    private ActivityTransactionHistoryBinding binding;
    private LinearLayout searchPanel;
    private TabLayout transactionTabLayout;
    private ViewPager2 transactionViewPager;
    private Shop shop;
    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.shop_transaction_type_all,
            R.string.shop_transaction_type_top_up,
            R.string.shop_transaction_type_withdraw,
            R.string.shop_transaction_type_manager_settlement,
            R.string.shop_transaction_type_manager_top_up
    };

    private TransactionFragment allTransactionFragment;
    private TransactionFragment userInTransactionFragment;
    private TransactionFragment userOutTransactionFragment;
    private TransactionFragment managerInTransactionFragment;
    private TransactionFragment managerOutTransactionFragment;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionHistoryBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_transaction_title), 0, null);
        setupTransactionViewPager();
        setupTransactionTabLayout();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            shop = new Gson().fromJson(json, Shop.class);
        }
    }

    private void setupTransactionViewPager() {
        allTransactionFragment = new TransactionFragment().newInstance(this, shop, 0);
        userInTransactionFragment = new TransactionFragment().newInstance(this, shop, 1);
        userOutTransactionFragment = new TransactionFragment().newInstance(this, shop, 2);
        managerInTransactionFragment = new TransactionFragment().newInstance(this, shop, 3);
        managerOutTransactionFragment = new TransactionFragment().newInstance(this, shop, 4);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        allTransactionFragment,
                        userInTransactionFragment,
                        userOutTransactionFragment,
                        managerInTransactionFragment,
                        managerOutTransactionFragment
                }
        ));

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
        for (int i = 0; i < TAB_TITLE.length; i++) {
            transactionTabLayout.addTab(transactionTabLayout.newTab().setText(TAB_TITLE[i]));

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

    private void updateTab(int position) {
        hideKeyboard(this);
        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(this, R.color.gray_D9D9D9));
        }
        tabTitleList.get(position).setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
    }
}