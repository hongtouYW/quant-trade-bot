package com.dj.user.activity;

import android.Manifest;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.ActivityDashboardBinding;
import com.dj.user.enums.TransactionStatus;
import com.dj.user.fragment.dashboard.CustomerServiceFragment;
import com.dj.user.fragment.dashboard.HomeFragment;
import com.dj.user.fragment.dashboard.ProfileFragment;
import com.dj.user.fragment.dashboard.PromotionFragment;
import com.dj.user.model.request.RequestPaymentStatus;
import com.dj.user.model.request.RequestUpdateFirebase;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.SingletonUtil;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;
import com.google.android.material.tabs.TabLayout;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class DashboardActivity extends BaseActivity {

    private ActivityDashboardBinding binding;
    private Member member;
    private Transaction credit;
    private ViewPager2 dashboardViewPager;
    private TabLayout dashboardTabLayout;
    private HomeFragment homeFragment;
    private PromotionFragment promotionFragment;
    //    private AffiliateFragment affiliateFragment;
    private CustomerServiceFragment customerServiceFragment;
    private ProfileFragment profileFragment;

    private List<LinearLayout> itemPanelList;
    private ImageView profileImageView;

    public static final int PAGE_HOME = 0;
    public static final int PAGE_PROMOTION = 1;
    //    private static final int PAGE_AFFILIATE = 2;
    private static final int PAGE_CUSTOMER_SERVICE = 2;
    private static final int PAGE_PROFILE = 3;
    private final static int[] ICONS = {
            R.drawable.ic_home,
            R.drawable.ic_promotion,
//            R.drawable.ic_affiliate,
            R.drawable.ic_customer_service,
            R.drawable.placeholder
    };
    private final static int[] TAB_TITLE = {
            R.string.dashboard_tab_home,
            R.string.dashboard_tab_promotion,
//            "推广赚钱",
            R.string.dashboard_tab_support,
            R.string.dashboard_tab_profile,
    };

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupDashboardViewPager();
        setupDashboardTabLayout();
        parseIntentData();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1001);
            }
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        updateFirebaseToken();
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
                    getPaymentStatus(id);
                }
            }
        }
    }


    private void setupDashboardViewPager() {
        dashboardViewPager = binding.viewPagerDashboard;
        dashboardTabLayout = binding.tabLayoutDashboard;

        homeFragment = new HomeFragment().newInstance(this);
        promotionFragment = new PromotionFragment().newInstance(this);
//        affiliateFragment = new AffiliateFragment().newInstance(this);
        customerServiceFragment = new CustomerServiceFragment().newInstance(this);
        profileFragment = new ProfileFragment().newInstance(this);
        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        homeFragment,
                        promotionFragment,
//                        affiliateFragment,
                        customerServiceFragment,
                        profileFragment
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
            tabTextView.setSelected(true);
            tabImageView.setImageResource(ICONS[i]);

            TabLayout.Tab tabLayoutTab = dashboardTabLayout.getTabAt(i);
            if (tabLayoutTab != null) {
                tabLayoutTab.setCustomView(tabView);
            }
        }
        dashboardViewPager.setCurrentItem(PAGE_HOME);
        updateTabIcon(PAGE_HOME);
    }

    public void updateProfileImage() {
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);
        if (member == null) {
            return;
        }
        String url = member.getAvatar();
        if (!StringUtil.isNullOrEmpty(url)) {
            Picasso.get().load(url).centerCrop().fit()
                    .placeholder(R.drawable.avatar_placeholder)
                    .into(profileImageView);
        }
    }

    private void updateTabIcon(int position) {
        for (int i = 0; i < itemPanelList.size(); i++) {
            View view = itemPanelList.get(i);
            view.setBackground(null);
        }
        itemPanelList.get(position).setBackgroundResource(R.drawable.bg_tab_selected);
    }

    public void switchTab(int page, int subPage) {
        TabLayout.Tab tab = dashboardTabLayout.getTabAt(page);
        if (tab != null) {
            tab.select();
        }
        dashboardViewPager.setCurrentItem(page);
        updateTabIcon(page);
        if (page == PAGE_PROMOTION && promotionFragment != null) {
            promotionFragment.switchTab(subPage);
        }
    }

    private void updateFirebaseToken() {
        String fcmToken = SingletonUtil.getInstance().getFcmToken();
        if (member == null) return;
        if (fcmToken == null || fcmToken.isEmpty()) return;
        if (fcmToken.equalsIgnoreCase(member.getDevicekey())) return;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateFirebase request = new RequestUpdateFirebase(member.getMember_id(), fcmToken);
        executeApiCall(this, apiService.updateFirebaseToken(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
            }
        }, false);
    }

    public void getPaymentStatus(String id) {
        if (member == null || StringUtil.isNullOrEmpty(id)) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPaymentStatus request = new RequestPaymentStatus(member.getMember_id(), id);
        executeApiCall(this, apiService.getDepositStatus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                List<Transaction> transactionList = response.getCredit();
                if (transactionList != null && !transactionList.isEmpty()) {
                    credit = transactionList.get(0);
                }
                if (credit != null) {
                    TransactionStatus status = TransactionStatus.fromValue(credit.getStatus());
                    if (status == TransactionStatus.SUCCESS) {
                        CustomToast.showTopToast(DashboardActivity.this, String.format(getString(R.string.top_up_success), FormatUtils.formatAmount(credit.getAmount())));
//                        TopUpWithdrawDialog topUpWithdrawDialog = new TopUpWithdrawDialog(this, true, "成功充值", "你已成功充值：", credit.getAmount(), "OK", () -> ((TopUpActivity) context).closePage());
//                        topUpWithdrawDialog.show();
                    } else {
                        Toast.makeText(DashboardActivity.this, status.getTitle(), Toast.LENGTH_SHORT).show();
                    }
                    credit = null;
                }
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
