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
import com.dj.user.databinding.ActivityAffiliateCodeDetailsBinding;
import com.dj.user.fragment.affiliate.AffiliateCodeFriendFragment;
import com.dj.user.fragment.affiliate.AffiliateCodeRewardFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class AffiliateCodeDetailsActivity extends BaseActivity {
    private ActivityAffiliateCodeDetailsBinding binding;
    private TabLayout affiliateCodeTabLayout;
    private ViewPager2 affiliateCodeViewPager;
    private List<TextView> tabTitleList;
    private final static int[] TAB_TITLE = {
            R.string.affiliate_code_tab_friend,
            R.string.affiliate_code_tab_reward
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityAffiliateCodeDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.affiliate_code_title), 0, null);
        setupAffiliateCodeViewPager();
        setupAffiliateCodeTabLayout();
    }

    private void setupAffiliateCodeViewPager() {
        AffiliateCodeFriendFragment affiliateCodeFriendFragment = new AffiliateCodeFriendFragment().newInstance(this);
        AffiliateCodeRewardFragment affiliateCodeRewardFragment = new AffiliateCodeRewardFragment().newInstance(this);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        affiliateCodeFriendFragment,
                        affiliateCodeRewardFragment
                }
        ));

        affiliateCodeViewPager = binding.viewPagerAffiliateCode;
        affiliateCodeTabLayout = binding.tabLayoutAffiliateCode;

        affiliateCodeViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        affiliateCodeViewPager.setAdapter(pagerAdapter);
        affiliateCodeViewPager.setUserInputEnabled(false);
        affiliateCodeViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupAffiliateCodeTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_affiliate_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = affiliateCodeTabLayout.newTab().setCustomView(tabView);
            affiliateCodeTabLayout.addTab(tab);
        }
        affiliateCodeTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                updateTab(position);
                affiliateCodeViewPager.setCurrentItem(position, false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        affiliateCodeTabLayout.post(() -> {
            updateTab(0);
            affiliateCodeViewPager.setCurrentItem(0, false);
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