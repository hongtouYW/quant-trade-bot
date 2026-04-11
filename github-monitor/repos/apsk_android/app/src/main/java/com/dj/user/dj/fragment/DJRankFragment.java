package com.dj.user.dj.fragment;

import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.adapter.SubViewPagerAdapter;
import com.dj.user.databinding.DjFragmentRankBinding;
import com.dj.user.dj.activity.DJDashboardActivity;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemCategory;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class DJRankFragment extends BaseFragment {

    private DjFragmentRankBinding binding;
    private Member member;
    private Context context;
    private ItemCategory selectedType;
    private ViewPager2 viewPager;
    private int currentIndex = 0;
    private TabLayout typeTabLayout;
    private ViewPager2 typeViewPager;
    private List<TextView> tabTitleList;

    private final static String[] TAB_TITLE = {
            "串烧",
            "单曲",
            "播放历史"
    };
    private final static int TAB_REMIX = 0;
    private final static int TAB_SINGLE = 1;
    private final static int TAB_HISTORY = 2;
    private int selectedTab = TAB_REMIX;
    private DJRankSongListFragment remixSongListFragment;
    private DJRankSongListFragment singleSongListFragment;
    private DJRankSongListFragment historySongListFragment;

    private List<Integer> images = Arrays.asList(
            R.drawable.img_home_1,
            R.drawable.img_home_2

    );

    public DJRankFragment newInstance(Context context) {
        DJRankFragment fragment = new DJRankFragment();
        fragment.context = context;
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
    public void onPause() {
        super.onPause();
    }

    @Override
    public void onResume() {
        super.onResume();
        getProfile();
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = DjFragmentRankBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupYxiViewPager();
        setupYxiTabLayout();
        return binding.getRoot();
    }

    private void setupUI() {
    }

    private void setupYxiViewPager() {
        remixSongListFragment = new DJRankSongListFragment().newInstance(context, TAB_REMIX);
        singleSongListFragment = new DJRankSongListFragment().newInstance(context, TAB_SINGLE);
        historySongListFragment = new DJRankSongListFragment().newInstance(context, TAB_HISTORY);

        SubViewPagerAdapter pagerAdapter = new SubViewPagerAdapter(this, Arrays.asList(
                new Fragment[]{
                        remixSongListFragment,
                        singleSongListFragment,
                        historySongListFragment
                }
        ));
        typeViewPager = binding.viewPagerSong;
        typeTabLayout = binding.tabLayoutSong;

        typeViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        typeViewPager.setAdapter(pagerAdapter);
        typeViewPager.setUserInputEnabled(false);
        typeViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupYxiTabLayout() {
        tabTitleList = new ArrayList<>();
        for (String s : TAB_TITLE) {
            View tabView = View.inflate(context, R.layout.content_affiliate_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(s);
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = typeTabLayout.newTab().setCustomView(tabView);
            typeTabLayout.addTab(tab);
        }
        typeTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                updateTab(tab.getPosition());
                typeViewPager.setCurrentItem(tab.getPosition(), false);
                selectedTab = tab.getPosition();
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {
            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {
            }
        });
        // Ensure the layout is ready before setting the first selection
        typeTabLayout.post(() -> {
            updateTab(TAB_REMIX);
            typeViewPager.setCurrentItem(TAB_REMIX, false);
        });
    }

    private void updateTab(int position) {
        Typeface boldTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_medium);
        Typeface normalTypeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_regular);

        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(context, R.color.yellow_FFFC86_50a));
            tabTextView.setTypeface(normalTypeface);
        }
        TextView selectedTab = tabTitleList.get(position);
        selectedTab.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
        selectedTab.setTypeface(boldTypeface);
    }

    private void getProfile() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DJDashboardActivity) context).executeApiCall(context, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(context, CacheManager.KEY_USER_PROFILE, member);
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
        }, false);
    }
}
