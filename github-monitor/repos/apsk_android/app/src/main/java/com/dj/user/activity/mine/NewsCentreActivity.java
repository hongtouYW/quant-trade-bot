package com.dj.user.activity.mine;

import android.graphics.Typeface;
import android.os.Bundle;
import android.view.View;
import android.widget.RelativeLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.ActivityNewsCentreBinding;
import com.dj.user.fragment.news.NewsCustomerCareFragment;
import com.dj.user.fragment.news.NewsFeedbackFragment;
import com.dj.user.fragment.news.NewsHomeFragment;
import com.dj.user.fragment.news.NewsNotificationFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class NewsCentreActivity extends BaseActivity {
    private ActivityNewsCentreBinding binding;
    private TabLayout newsCentreTabLayout;
    private ViewPager2 newsCentreViewPager;

    private List<TextView> tabTitleList;
    private List<View> tagPanelList;
    private List<TextView> tagTextList;

    private final static int[] TAB_TITLE = {
            R.string.news_centre_tab_customer_care,
            R.string.news_centre_tab_notification,
            R.string.news_centre_tab_home_notification,
            R.string.news_centre_tab_app_feedback
    };

    private NewsCustomerCareFragment newsCustomerCareFragment;
    private NewsNotificationFragment newsNotificationFragment;
    private NewsHomeFragment newsHomeFragment;
    private NewsFeedbackFragment newsFeedbackFragment;

    private int defaultTab = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityNewsCentreBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        defaultTab = getIntent().getIntExtra("tab", 0);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.news_centre_title), 0, null);
        setupNewsViewPager();
        setupNewsTabLayout();
    }

    private void setupNewsViewPager() {
        newsCustomerCareFragment = new NewsCustomerCareFragment().newInstance(this);
        newsNotificationFragment = new NewsNotificationFragment().newInstance(this);
        newsHomeFragment = new NewsHomeFragment().newInstance(this);
        newsFeedbackFragment = new NewsFeedbackFragment().newInstance(this);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        newsCustomerCareFragment,
                        newsNotificationFragment,
                        newsHomeFragment,
                        newsFeedbackFragment,
                }
        ));

        newsCentreViewPager = binding.viewPagerNewsCentre;
        newsCentreTabLayout = binding.tabLayoutNewsCentre;

        newsCentreViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        newsCentreViewPager.setAdapter(pagerAdapter);
        newsCentreViewPager.setUserInputEnabled(false);
        newsCentreViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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
        tagPanelList = new ArrayList<>();
        tagTextList = new ArrayList<>();
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(this, R.layout.content_news_centre_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            RelativeLayout tagPanel = tabView.findViewById(R.id.panel_tag);
            TextView tagTextView = tabView.findViewById(R.id.textView_tag);

            tabTextView.setText(s);

            tabTitleList.add(tabTextView);
            tagPanelList.add(tagPanel);
            tagTextList.add(tagTextView);

            // hide all tag panels by default
            tagPanel.setVisibility(View.GONE);

            TabLayout.Tab tab = newsCentreTabLayout.newTab().setCustomView(tabView);
            newsCentreTabLayout.addTab(tab);
        }
        newsCentreTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                updateTab(position);
//                updateIndicatorPosition(position);
                newsCentreViewPager.setCurrentItem(position, false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        newsCentreTabLayout.post(() -> {
            TabLayout.Tab tab = newsCentreTabLayout.getTabAt(defaultTab);
            if (tab != null) {
                tab.select();
            }
            updateTab(defaultTab);
//            updateIndicatorPosition(defaultTab);
            newsCentreViewPager.setCurrentItem(defaultTab, false);
        });
    }

//    private void updateIndicatorPosition(int position) {
//        if (position < 0 || position >= tabTitleList.size()) return;
//
//        TextView textView = tabTitleList.get(position);
//        textView.post(() -> {
//            int textWidth = textView.getWidth();
//            View tabView = ((ViewGroup) newsCentreTabLayout.getChildAt(0)).getChildAt(position);
//
//            View indicator = binding.tabIndicator;
//            LinearLayout.LayoutParams params = (LinearLayout.LayoutParams) indicator.getLayoutParams();
//            params.width = textWidth;
//            params.leftMargin = tabView.getLeft() + (tabView.getWidth() - textWidth) / 2;
//            indicator.setLayoutParams(params);
//        });
//    }

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

    public void updateTabTag(int position, int count) {
        if (position < 0 || position >= tagPanelList.size()) return;
        View panel = tagPanelList.get(position);
        TextView tagText = tagTextList.get(position);
        if (count > 0) {
            panel.setVisibility(View.VISIBLE);
            tagText.setText(String.valueOf(count));
        } else {
            panel.setVisibility(View.GONE);
        }
    }

}