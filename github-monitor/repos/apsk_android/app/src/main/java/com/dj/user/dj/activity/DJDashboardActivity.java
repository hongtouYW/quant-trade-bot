package com.dj.user.dj.activity;

import android.os.Bundle;
import android.view.View;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.DjActivityDashboardBinding;
import com.dj.user.dj.fragment.DJHomeFragment;
import com.dj.user.dj.fragment.DJProfileFragment;
import com.dj.user.dj.fragment.DJRankFragment;
import com.dj.user.model.response.Member;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.google.android.material.tabs.TabLayout;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class DJDashboardActivity extends BaseActivity {

    private DjActivityDashboardBinding binding;
    private Member member;

    private ViewPager2 dashboardViewPager;
    private TabLayout dashboardTabLayout;
    private DJHomeFragment djHomeFragment;
    private DJHomeFragment djHomeFragment1;
    private DJRankFragment djRankFragment;
    private DJProfileFragment djProfileFragment;

    private List<LinearLayout> itemPanelList;
    private ImageView profileImageView;

    public static final int PAGE_HOME = 0;
    private final static int[] ICONS = {
            R.drawable.ic_home,
            R.drawable.dj_ic_album,
            R.drawable.dj_ic_playlist,
            R.drawable.dj_ic_profile
    };
    private final static String[] TAB_TITLE = {
            "首页",
            "曲库",
            "榜单",
            "我的",
    };

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityDashboardBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupDashboardViewPager();
        setupDashboardTabLayout();
    }

    private void setupDashboardViewPager() {
        dashboardViewPager = binding.viewPagerDashboard;
        dashboardTabLayout = binding.tabLayoutDashboard;

        djHomeFragment = new DJHomeFragment().newInstance(this);
        djHomeFragment1 = new DJHomeFragment().newInstance(this);
        djRankFragment = new DJRankFragment().newInstance(this);
        djProfileFragment = new DJProfileFragment().newInstance(this);
        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        djHomeFragment,
                        djHomeFragment1,
                        djRankFragment,
                        djProfileFragment,
                }
        ));
        dashboardViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        dashboardViewPager.setAdapter(pagerAdapter);
        dashboardViewPager.setUserInputEnabled(false);
        dashboardTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                dashboardViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {

            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {

            }
        });
        dashboardViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
            @Override
            public void onPageScrolled(int position, float positionOffset, int positionOffsetPixels) {
                super.onPageScrolled(position, positionOffset, positionOffsetPixels);
            }

            @Override
            public void onPageSelected(int position) {
                super.onPageSelected(position);
                updateTabIcon(position);
            }

            @Override
            public void onPageScrollStateChanged(int state) {
                super.onPageScrollStateChanged(state);
            }
        });
    }

    private void setupDashboardTabLayout() {
        itemPanelList = new ArrayList<>();
        for (int i = 0; i < TAB_TITLE.length; i++) {
            dashboardTabLayout.addTab(dashboardTabLayout.newTab().setText(TAB_TITLE[i]));

            View tabView = View.inflate(this, R.layout.content_dashboard_tab, null);
            LinearLayout itemPanel = tabView.findViewById(R.id.panel_item);
            ImageView tabImageView = tabView.findViewById(R.id.imageView_tab);
            FrameLayout borderWrapper = tabView.findViewById(R.id.borderWrapper);
            ImageView profileImageView = tabView.findViewById(R.id.imageView_profile);
            ImageView profileSettingImageView = tabView.findViewById(R.id.imageView_sub);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);

            itemPanelList.add(itemPanel);
            if (i == TAB_TITLE.length - 1) {
                this.profileImageView = profileImageView;
                updateProfileImage();
                tabImageView.setVisibility(View.GONE);
                borderWrapper.setVisibility(View.VISIBLE);
                profileSettingImageView.setVisibility(View.VISIBLE);
            } else {
                tabImageView.setVisibility(View.VISIBLE);
                borderWrapper.setVisibility(View.GONE);
                profileSettingImageView.setVisibility(View.GONE);
            }
            tabTextView.setText(TAB_TITLE[i]);
            tabImageView.setImageResource(ICONS[i]);

            TabLayout.Tab tabLayoutTab = dashboardTabLayout.getTabAt(i);
            if (tabLayoutTab != null) {
                tabLayoutTab.setCustomView(tabView);
            }
        }
        dashboardViewPager.setCurrentItem(PAGE_HOME);
        updateTabIcon(PAGE_HOME);
    }

    private void updateProfileImage() {
        String url = member.getAvatar();
        if (!StringUtil.isNullOrEmpty(url)) {
            Picasso.get().load(url).centerCrop().fit().into(profileImageView);
        }
    }

    private void updateTabIcon(int position) {
        for (int i = 0; i < itemPanelList.size(); i++) {
            View view = itemPanelList.get(i);
            view.setBackground(null);
        }
        itemPanelList.get(position).setBackgroundResource(R.drawable.dj_bg_tab_selected);
    }

    public void switchTab(int page) {
        TabLayout.Tab tab = dashboardTabLayout.getTabAt(page);
        if (tab != null) {
            tab.select();
        }
        dashboardViewPager.setCurrentItem(page);
        updateTabIcon(page);
    }
}
