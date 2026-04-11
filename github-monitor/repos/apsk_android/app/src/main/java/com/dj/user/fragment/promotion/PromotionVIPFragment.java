package com.dj.user.fragment.promotion;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.Context;
import android.content.Intent;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.LinearInterpolator;
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
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.FragmentPromotionVipBinding;
import com.dj.user.enums.VIPType;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.fragment.VIPFragment;
import com.dj.user.model.request.RequestBonusRedemption;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Remain;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.widget.CustomToast;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.Locale;

public class PromotionVIPFragment extends BaseFragment {

    private FragmentPromotionVipBinding binding;
    private Context context;
    private Member member;
    private TabLayout vipTabLayout;
    private ViewPager2 vipViewPager;
    private VIPType selectedType = VIPType.GENERAL;
    private List<TextView> tabTitleList;
    private final List<VIPType> vipTypes = Arrays.asList(
            VIPType.GENERAL,
            VIPType.WEEKLY,
            VIPType.MONTHLY
//            VIPType.VIP
    );

    private VIPFragment normalVIPFragment;
    private VIPFragment weekVIPFragment;
    private VIPFragment monthVIPFragment;
    //    private VIPFragment privilegeVIPFragment;
    private ObjectAnimator refreshAnimator;

    public PromotionVIPFragment newInstance(Context context) {
        PromotionVIPFragment fragment = new PromotionVIPFragment();
        fragment.context = context;
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context ctx) {
        super.onAttach(ctx);
        if (context == null) {
            context = ctx;
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentPromotionVipBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupVIPViewPager();
        setupVIPTabLayout();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);
        if (member == null) {
            CacheManager.clearAll(context);
            ((DashboardActivity) context).startAppActivity(new Intent(context, LoginActivity.class),
                    null, true, true, true);
            return;
        }
        setupUI();
        getRedeemDistance();
    }

    private void setupUI() {
        binding.textViewOneClick.setOnClickListener(view -> {
            if (selectedType == VIPType.GENERAL) {
                redeemGeneralBonus();
            } else if (selectedType == VIPType.WEEKLY) {
                redeemWeeklyBonus();
            } else if (selectedType == VIPType.MONTHLY) {
                redeemMonthlyBonus();
            }
        });
        binding.panelDistance.setOnClickListener(view -> getRedeemDistance());

        int vip = member != null ? member.getVip() : 0;
        String fromVipName = String.format(Locale.ENGLISH, "vip_%d", vip);
        int fromResId = context.getResources().getIdentifier(fromVipName, "drawable", context.getPackageName());
        binding.imageViewVipFrom.setImageResource(fromResId);

        String toVipName = String.format(Locale.ENGLISH, "vip_%d", vip + 1);
        int toResId = context.getResources().getIdentifier(toVipName, "drawable", context.getPackageName());
        binding.imageViewVipTo.setImageResource(toResId);
    }

    private void setupVIPViewPager() {
        normalVIPFragment = new VIPFragment().newInstance(context, VIPType.GENERAL, 0);
        weekVIPFragment = new VIPFragment().newInstance(context, VIPType.WEEKLY, 1);
        monthVIPFragment = new VIPFragment().newInstance(context, VIPType.MONTHLY, 2);
//        privilegeVIPFragment = new VIPFragment().newInstance(context, VIPType.VIP, 3);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getChildFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        normalVIPFragment,
                        weekVIPFragment,
                        monthVIPFragment
//                        privilegeVIPFragment
                }
        ));

        vipViewPager = binding.viewPagerVip;
        vipTabLayout = binding.tabLayoutVip;

        vipViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        vipViewPager.setAdapter(pagerAdapter);
        vipViewPager.setUserInputEnabled(false);
        vipViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupVIPTabLayout() {
        tabTitleList = new ArrayList<>();
        for (VIPType type : vipTypes) {
            View tabView = View.inflate(context, R.layout.content_vip_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(type.getTitle());
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = vipTabLayout.newTab().setCustomView(tabView);
            vipTabLayout.addTab(tab);
        }
        vipTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                selectedType = vipTypes.get(position);
                updateTab(position);
                updateIndicatorPosition(position);
                vipViewPager.setCurrentItem(position, false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        vipTabLayout.post(() -> {
            updateTab(0);
            updateIndicatorPosition(0);
            vipViewPager.setCurrentItem(0, false);
        });
    }

    private void updateIndicatorPosition(int position) {
        if (position < 0 || position >= tabTitleList.size()) return;

        TextView textView = tabTitleList.get(position);
        textView.post(() -> {
            int textWidth = textView.getWidth();
            View tabView = ((ViewGroup) vipTabLayout.getChildAt(0)).getChildAt(position);

            View indicator = binding.tabIndicator;
            LinearLayout.LayoutParams params = (LinearLayout.LayoutParams) indicator.getLayoutParams();
            params.width = textWidth;
            params.leftMargin = tabView.getLeft() + (tabView.getWidth() - textWidth) / 2;
            indicator.setLayoutParams(params);
        });
    }

    private void updateTab(int position) {
        Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_medium);
        Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_regular);

        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(vipTypes.get(i).getTitle());
            tabTextView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07_50a));
            tabTextView.setTypeface(normalTypeface);
        }
        TextView selectedTab = tabTitleList.get(position);
        selectedTab.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
        selectedTab.setTypeface(boldTypeface);
    }

    private void startRefreshAnimation() {
        if (refreshAnimator == null) {
            refreshAnimator = ObjectAnimator.ofFloat(binding.imageViewRefresh, "rotation", 0f, 360f);
            refreshAnimator.setDuration(300);
            refreshAnimator.setRepeatCount(ValueAnimator.INFINITE);
            refreshAnimator.setInterpolator(new LinearInterpolator());
        }
        refreshAnimator.start();
    }

    private void stopRefreshAnimation() {
        if (refreshAnimator != null && refreshAnimator.isRunning()) {
            refreshAnimator.cancel();
            binding.imageViewRefresh.setRotation(0f);
        }
    }

    private void getRedeemDistance() {
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getRedeemDistance(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                double distance = 0.00;
                Remain remain = response.getRemain();
                if (remain != null) {
                    distance = remain.getGeneral();
                }
                binding.textViewDistance.setText(FormatUtils.formatAmount(distance));
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                binding.textViewDistance.setText(getString(R.string.placeholder_amount));
                stopRefreshAnimation();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                binding.textViewDistance.setText(getString(R.string.placeholder_amount));
                stopRefreshAnimation();
                return false;
            }
        }, false);
    }

    private void redeemGeneralBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestBonusRedemption request = new RequestBonusRedemption(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemGeneralBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }

    private void redeemWeeklyBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemWeeklyBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }

    private void redeemMonthlyBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemMonthlyBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}
