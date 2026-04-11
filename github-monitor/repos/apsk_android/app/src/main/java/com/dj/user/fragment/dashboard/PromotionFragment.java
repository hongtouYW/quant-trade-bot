package com.dj.user.fragment.dashboard;

import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.FragmentPromotionBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.fragment.promotion.PromotionEventFragment;
import com.dj.user.fragment.promotion.PromotionRedeemFragment;
import com.dj.user.fragment.promotion.PromotionVIPFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class PromotionFragment extends BaseFragment {

    private FragmentPromotionBinding binding;
    private Context context;
    private TabLayout promotionTabLayout;
    private ViewPager2 promotionViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.promotion_tab_event,
            R.string.promotion_tab_vip,
//            R.string.promotion_tab_rebate,
            R.string.promotion_tab_redeem_history,
    };

    private PromotionEventFragment promotionEventFragment;
    private PromotionVIPFragment promotionVIPFragment;
    //    private PromotionRebateFragment promotionRebateFragment;
    private PromotionRedeemFragment promotionRedeemFragment;
    private int defaultTab = 0;

    public PromotionFragment newInstance(Context context) {
        Bundle args = new Bundle();
        PromotionFragment fragment = new PromotionFragment();
        fragment.context = context;
        fragment.setArguments(args);
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context context) {
        super.onAttach(context);
        if (this.context == null) {
            PackageManager packageManager = context.getPackageManager();
            Intent intent = packageManager.getLaunchIntentForPackage(context.getPackageName());
            ComponentName componentName = intent.getComponent();
            Intent mainIntent = Intent.makeRestartActivityTask(componentName);
            context.startActivity(mainIntent);
            Runtime.getRuntime().exit(0);
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentPromotionBinding.inflate(inflater, container, false);
        context = getContext();

        setupUI();
        setupNewsViewPager();
        setupPromotionTabLayout();
        return binding.getRoot();
    }

    private void setupUI() {
        binding.imageViewBack.setOnClickListener(view -> ((DashboardActivity) context).switchTab(DashboardActivity.PAGE_HOME, 0));
    }

    private void setupNewsViewPager() {
        promotionEventFragment = new PromotionEventFragment().newInstance(context);
        promotionVIPFragment = new PromotionVIPFragment().newInstance(context);
//        promotionRebateFragment = new PromotionRebateFragment().newInstance(context);
        promotionRedeemFragment = new PromotionRedeemFragment().newInstance(context);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getChildFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        promotionEventFragment,
                        promotionVIPFragment,
//                        promotionRebateFragment,
                        promotionRedeemFragment
                }
        ));

        promotionViewPager = binding.viewPagerPromotion;
        promotionTabLayout = binding.tabLayoutPromotion;

        promotionViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        promotionViewPager.setAdapter(pagerAdapter);
        promotionViewPager.setUserInputEnabled(false);
        promotionViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
            @Override
            public void onPageScrolled(int position, float positionOffset, int positionOffsetPixels) {
                super.onPageScrolled(position, positionOffset, positionOffsetPixels);
            }

            @Override
            public void onPageSelected(int position) {
                super.onPageSelected(position);
                updateTab(position);
                updateIndicatorPosition(position);
            }

            @Override
            public void onPageScrollStateChanged(int state) {
                super.onPageScrollStateChanged(state);
            }
        });
    }

    private void setupPromotionTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int s : TAB_TITLE) {
            View tabView = View.inflate(context, R.layout.content_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = promotionTabLayout.newTab().setCustomView(tabView);
            promotionTabLayout.addTab(tab);
        }
        promotionTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                updateTab(position);
                updateIndicatorPosition(position);
                promotionViewPager.setCurrentItem(position, false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        promotionTabLayout.post(() -> {
            TabLayout.Tab tab = promotionTabLayout.getTabAt(defaultTab);
            if (tab != null) {
                tab.select();
            }
            updateTab(defaultTab);
            updateIndicatorPosition(defaultTab);
            promotionViewPager.setCurrentItem(defaultTab, false);
        });
    }

    private void updateIndicatorPosition(int position) {
        if (position < 0 || position >= tabTitleList.size()) return;

        TextView textView = tabTitleList.get(position);
        textView.post(() -> {
            int textWidth = textView.getWidth();
            View tabView = ((ViewGroup) promotionTabLayout.getChildAt(0)).getChildAt(position);

            // offset caused by the back button + its margin
            int parentPaddingLeft = ((ViewGroup) promotionTabLayout.getParent()).getPaddingLeft();
            int backOffset = parentPaddingLeft + binding.imageViewBack.getWidth() +
                    ((ViewGroup.MarginLayoutParams) binding.imageViewBack.getLayoutParams()).getMarginEnd();

            View indicator = binding.tabIndicator;
            LinearLayout.LayoutParams params = (LinearLayout.LayoutParams) indicator.getLayoutParams();
            params.width = textWidth;
            params.leftMargin = backOffset + tabView.getLeft() + (tabView.getWidth() - textWidth) / 2;
            indicator.setLayoutParams(params);
        });
    }


    private void updateTab(int position) {
        Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_medium);
        Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_regular);

        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07_50a));
            tabTextView.setTypeface(normalTypeface);
        }
        TextView selectedTab = tabTitleList.get(position);
        selectedTab.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
        selectedTab.setTypeface(boldTypeface);
    }

    public void switchTab(int page) {
        if (!isAdded()) return;
        TabLayout.Tab tab = promotionTabLayout.getTabAt(page);
        if (tab != null) {
            tab.select();
        }
        promotionViewPager.setCurrentItem(page);
        updateTab(page);
    }
}
