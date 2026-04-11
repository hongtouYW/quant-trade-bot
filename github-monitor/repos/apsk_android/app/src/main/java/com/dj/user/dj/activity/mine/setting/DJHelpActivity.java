package com.dj.user.dj.activity.mine.setting;

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
import com.dj.user.databinding.DjActivityHelpBinding;
import com.dj.user.dj.fragment.DJHelpFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class DJHelpActivity extends BaseActivity {

    private DjActivityHelpBinding binding;
    private TabLayout helpTabLayout;
    private ViewPager2 helpViewPager;

    private List<TextView> tabTitleList;

    private final static String[] TAB_TITLE = {
            "常见问题",
            "交易操作",
            "转移",
            "VIP等级讲解",
            "推广赚钱讲解",
            "代理讲解"
    };

    private DJHelpFragment faqFragment;
    private DJHelpFragment operateFragment;
    private DJHelpFragment transferFragment;
    private DJHelpFragment vipExplanationFragment;
    private DJHelpFragment affiliateFragment;
    private DJHelpFragment agentFragment;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityHelpBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "帮助", 0, null);
        setupUI();
        setupHelpViewPager();
        setupHelpTabLayout();
    }

    private void setupUI() {
    }

    private void setupHelpViewPager() {
        faqFragment = new DJHelpFragment().newInstance(this, 0);
        operateFragment = new DJHelpFragment().newInstance(this, 1);
        transferFragment = new DJHelpFragment().newInstance(this, 2);
        vipExplanationFragment = new DJHelpFragment().newInstance(this, 3);
        affiliateFragment = new DJHelpFragment().newInstance(this, 4);
        agentFragment = new DJHelpFragment().newInstance(this, 5);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        faqFragment,
                        operateFragment,
                        transferFragment,
                        vipExplanationFragment,
                        affiliateFragment,
                        agentFragment
                }
        ));

        helpViewPager = binding.viewPagerHelp;
        helpTabLayout = binding.tabLayoutHelp;

        helpViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        helpViewPager.setAdapter(pagerAdapter);
        helpViewPager.setUserInputEnabled(false);
        helpViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupHelpTabLayout() {
        tabTitleList = new ArrayList<>();
        for (String s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_affiliate_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = helpTabLayout.newTab().setCustomView(tabView);
            helpTabLayout.addTab(tab);
        }
        helpTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                updateTab(tab.getPosition());
                helpViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        helpTabLayout.post(() -> {
            updateTab(0);
            helpViewPager.setCurrentItem(0, false);
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