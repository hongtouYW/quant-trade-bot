package com.dj.user.activity.mine.transaction;

import android.graphics.Typeface;
import android.os.Bundle;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.ActivityTransactionBinding;
import com.dj.user.fragment.transaction.CreditFragment;
import com.dj.user.fragment.transaction.HistoryFragment;
import com.dj.user.fragment.transaction.PointsFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class TransactionActivity extends BaseActivity {

    private ActivityTransactionBinding binding;
    private TabLayout transactionTabLayout;
    private ViewPager2 transactionViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.transaction_tab_credit,
            R.string.transaction_tab_points,
            R.string.transaction_tab_history
    };

    private CreditFragment creditFragment;
    private PointsFragment pointFragment;
    private HistoryFragment historyFragment;

    private int defaultTab = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        defaultTab = getIntent().getIntExtra("tab", 0);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.transaction_title), 0, null);
        setupTransactionViewPager();
        setupTransactionTabLayout();
    }


    private void setupTransactionViewPager() {
        creditFragment = new CreditFragment().newInstance(this);
        pointFragment = new PointsFragment().newInstance(this);
        historyFragment = new HistoryFragment().newInstance(this);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        creditFragment,
                        pointFragment,
                        historyFragment
                }
        ));

        transactionViewPager = binding.viewPagerTransaction;
        transactionTabLayout = binding.tabLayoutTransaction;

        transactionViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        transactionViewPager.setAdapter(pagerAdapter);
        transactionViewPager.setUserInputEnabled(false);
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
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_transaction_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = transactionTabLayout.newTab().setCustomView(tabView);
            transactionTabLayout.addTab(tab);
        }
        transactionTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                updateTab(tab.getPosition());
                updateIndicatorPosition(tab.getPosition());
                transactionViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        transactionTabLayout.post(() -> {
            TabLayout.Tab tab = transactionTabLayout.getTabAt(defaultTab);
            if (tab != null) {
                tab.select();
            }
            updateTab(defaultTab);
            updateIndicatorPosition(defaultTab);
            transactionViewPager.setCurrentItem(defaultTab, false);
        });
    }

    private void updateIndicatorPosition(int position) {
        if (position < 0 || position >= tabTitleList.size()) return;

        TextView textView = tabTitleList.get(position);
        textView.post(() -> {
            int textWidth = textView.getWidth();
            View tabView = ((ViewGroup) transactionTabLayout.getChildAt(0)).getChildAt(position);

            View indicator = binding.tabIndicator;
            LinearLayout.LayoutParams params = (LinearLayout.LayoutParams) indicator.getLayoutParams();
            params.width = textWidth;
            params.leftMargin = tabView.getLeft() + (tabView.getWidth() - textWidth) / 2;
            indicator.setLayoutParams(params);
        });
    }

    private void updateTab(int position) {
        hideKeyboard(this);
        Typeface boldTypeface = ResourcesCompat.getFont(this, R.font.pingfang_sc_medium);
        Typeface normalTypeface = ResourcesCompat.getFont(this, R.font.pingfang_sc_regular);

        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07_50a));
            tabTextView.setTypeface(normalTypeface);
        }
        TextView selectedTab = tabTitleList.get(position);
        selectedTab.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
        selectedTab.setTypeface(boldTypeface);
    }
}