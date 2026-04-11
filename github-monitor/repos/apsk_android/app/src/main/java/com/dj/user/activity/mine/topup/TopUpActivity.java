package com.dj.user.activity.mine.topup;

import android.content.Intent;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
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
import com.dj.user.activity.SplashScreenActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.ActivityTopUpBinding;
import com.dj.user.fragment.topup.TopUpCryptoFragment;
import com.dj.user.fragment.topup.TopUpOnlineFragment;
import com.dj.user.fragment.topup.TopUpQRFragment;
import com.dj.user.fragment.topup.TopUpWalletFragment;
import com.dj.user.util.StringUtil;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class TopUpActivity extends BaseActivity {
    private ActivityTopUpBinding binding;
    private TabLayout topUpTabLayout;
    private ViewPager2 topUpViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.top_up_tab_online,
//            R.string.top_up_tab_crypto,
//            R.string.top_up_tab_ewallet,
//            R.string.top_up_tab_qr,
    };

    private TopUpOnlineFragment topUpOnlineFragment;
    private TopUpCryptoFragment topUpCryptoFragment;
    private TopUpWalletFragment topUpWalletFragment;
    private TopUpQRFragment topUpQRFragment;

    private int defaultTab = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTopUpBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        defaultTab = getIntent().getIntExtra("tab", 0);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.top_up_title), 0, null);
        setupNewsViewPager();
        setupNewsTabLayout();
        parseIntentData();
    }

    private void parseIntentData() {
        Uri data = getIntent().getData();
        if (data != null) {
            String host = data.getHost(); // payment
            if (!StringUtil.isNullOrEmpty(host) && host.equalsIgnoreCase("payment")) {
                String type = data.getQueryParameter("method"); // online
                String id = data.getQueryParameter("id"); // credit_id
                Log.d("###", "FULL URI: " + data);
                Log.d("###", "deeplink: " + host + " - " + type + " - " + id);
                if (!StringUtil.isNullOrEmpty(type) && type.equalsIgnoreCase("online")) {
                    topUpViewPager.post(() -> {
                        if (topUpOnlineFragment != null && topUpOnlineFragment.isAdded()) {
                            topUpOnlineFragment.getPaymentStatus(id);
                        }
                    });
                }
            }
        }
    }

    public void closePage() {
        if (isTaskRoot()) {
            // This is the only activity in the task
            startAppActivity(new Intent(this, SplashScreenActivity.class),
                    null, true, true, true);
        } else {
            // There are other activities in the stack
            onBaseBackPressed();
        }
    }

    private void setupNewsViewPager() {
        topUpOnlineFragment = new TopUpOnlineFragment().newInstance(this);
//        topUpCryptoFragment = new TopUpCryptoFragment().newInstance(this);
//        topUpWalletFragment = new TopUpWalletFragment().newInstance(this);
//        topUpQRFragment = new TopUpQRFragment().newInstance(this);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        topUpOnlineFragment
//                        topUpCryptoFragment,
//                        topUpWalletFragment,
//                        topUpQRFragment
                }
        ));

        topUpViewPager = binding.viewPagerTopUp;
        topUpTabLayout = binding.tabLayoutTopUp;

        topUpViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        topUpViewPager.setAdapter(pagerAdapter);
        topUpViewPager.setUserInputEnabled(false);
        topUpViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupNewsTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = topUpTabLayout.newTab().setCustomView(tabView);
            topUpTabLayout.addTab(tab);
        }
        topUpTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                updateTab(tab.getPosition());
                updateIndicatorPosition(tab.getPosition());
                topUpViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        topUpTabLayout.post(() -> {
            TabLayout.Tab tab = topUpTabLayout.getTabAt(defaultTab);
            if (tab != null) {
                tab.select();
            }
            updateTab(defaultTab);
            updateIndicatorPosition(defaultTab);
            topUpViewPager.setCurrentItem(defaultTab, false);
        });
    }

    private void updateIndicatorPosition(int position) {
        if (position < 0 || position >= tabTitleList.size()) return;

        TextView textView = tabTitleList.get(position);
        textView.post(() -> {
            int textWidth = textView.getWidth();
            View tabView = ((ViewGroup) topUpTabLayout.getChildAt(0)).getChildAt(position);

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