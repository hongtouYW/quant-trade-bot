package com.dj.user.activity.mine.affiliate;

import android.graphics.Typeface;
import android.os.Bundle;
import android.view.View;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.ActivityAffiliateBinding;
import com.dj.user.fragment.affiliate.AffiliateCommisionFragment;
import com.dj.user.fragment.affiliate.AffiliateDataFragment;
import com.dj.user.fragment.affiliate.AffiliateDownlineFragment;
import com.dj.user.fragment.affiliate.AffiliateKpiFragment;
import com.dj.user.fragment.affiliate.AffiliateNewFragment;
import com.dj.user.fragment.affiliate.AffiliateShareFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class AffiliateActivity extends BaseActivity {
    private ActivityAffiliateBinding binding;
    private TabLayout affiliateTabLayout;
    private ViewPager2 affiliateViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.affiliate_tab_share,
            R.string.affiliate_tab_data,
            R.string.affiliate_tab_kpi,
            R.string.affiliate_tab_commission,
            R.string.affiliate_tab_downline,
//            R.string.affiliate_tab_ratio,
            R.string.affiliate_tab_new
    };

    private AffiliateShareFragment affiliateShareFragment;
    private AffiliateDataFragment affiliateDataFragment;
    private AffiliateKpiFragment affiliateKpiFragment;
    private AffiliateCommisionFragment affiliateCommisionFragment;
    private AffiliateDownlineFragment affiliateDownlineFragment;
    //    private AffiliateRatioFragment affiliateRatioFragment;
    private AffiliateNewFragment affiliateNewFragment;
    public static final int PAGE_COMMISSION = 3;
    public static final int PAGE_DOWNLINE = 4;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityAffiliateBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.affiliate_title), 0, null);
        setupAffiliateViewPager();
        setupAffiliateTabLayout();
    }

    private void setupAffiliateViewPager() {
        affiliateShareFragment = new AffiliateShareFragment().newInstance(this);
        affiliateDataFragment = new AffiliateDataFragment().newInstance(this);
        affiliateKpiFragment = new AffiliateKpiFragment().newInstance(this);
        affiliateCommisionFragment = new AffiliateCommisionFragment().newInstance(this);
        affiliateDownlineFragment = new AffiliateDownlineFragment().newInstance(this);
//        affiliateRatioFragment = new AffiliateRatioFragment().newInstance(this);
        affiliateNewFragment = new AffiliateNewFragment().newInstance(this);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        affiliateShareFragment,
                        affiliateDataFragment,
                        affiliateKpiFragment,
                        affiliateCommisionFragment,
                        affiliateDownlineFragment,
//                        affiliateRatioFragment,
                        affiliateNewFragment
                }
        ));

        affiliateViewPager = binding.viewPagerAffiliate;
        affiliateTabLayout = binding.tabLayoutAffiliate;

        affiliateViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        affiliateViewPager.setAdapter(pagerAdapter);
        affiliateViewPager.setUserInputEnabled(false);
        affiliateViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupAffiliateTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_affiliate_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = affiliateTabLayout.newTab().setCustomView(tabView);
            affiliateTabLayout.addTab(tab);
        }
        affiliateTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                updateTab(position);
                affiliateViewPager.setCurrentItem(position, false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        affiliateTabLayout.post(() -> {
            updateTab(0);
            affiliateViewPager.setCurrentItem(0, false);
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

    public void switchTab(int page) {
        TabLayout.Tab tab = affiliateTabLayout.getTabAt(page);
        if (tab != null) {
            tab.select();
        }
        affiliateViewPager.setCurrentItem(page);
        updateTab(page);
    }
}