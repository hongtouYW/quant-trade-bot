package com.dj.manager.activity.user;

import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.ViewPagerAdapter;
import com.dj.manager.databinding.ActivityUserManagementBinding;
import com.dj.manager.fragment.UserFragment;
import com.google.android.material.tabs.TabLayout;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class UserManagementActivity extends BaseActivity {
    private ActivityUserManagementBinding binding;
    private LinearLayout searchPanel;
    private TabLayout userTabLayout;
    private ViewPager2 userViewPager;

    private List<TextView> tabTitleList;

    private final static int[] TAB_TITLE = {
            R.string.user_list_tab_all,
            R.string.user_list_tab_active,
            R.string.user_list_tab_blocked,
//            R.string.user_list_tab_deleted,
    };

    private UserFragment allUserFragment;
    private UserFragment activeUserFragment;
    private UserFragment blockedUserFragment;
//    private UserFragment deletedUserFragment;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUserManagementBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.user_list_title), R.drawable.ic_search, view -> {
            if (searchPanel.getVisibility() == View.VISIBLE) {
                searchPanel.setVisibility(View.GONE);
                hideKeyboard(UserManagementActivity.this);
            } else {
                searchPanel.setVisibility(View.VISIBLE);
            }
        });
        setupShopViewPager();
        setupShopTabLayout();
        setupSearchPanel();
    }

    private void setupSearchPanel() {
        searchPanel = binding.panelSearch;
        binding.editTextSearch.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void onTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void afterTextChanged(Editable editable) {
                if (editable.length() > 0) {
                    binding.imageViewClear.setVisibility(View.VISIBLE);
                } else {
                    binding.imageViewClear.setVisibility(View.GONE);
                }
                String keyword = editable.toString().trim();
                filterCurrentFragment(keyword);
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void filterCurrentFragment(String keyword) {
        int currentTab = userViewPager.getCurrentItem();
        switch (currentTab) {
            case 0:
                allUserFragment.filter(keyword);
                break;
            case 1:
                activeUserFragment.filter(keyword);
                break;
            case 2:
                blockedUserFragment.filter(keyword);
                break;
//            case 3:
//                deletedUserFragment.filter(keyword);
//                break;
        }
    }

    private void setupShopViewPager() {
        allUserFragment = new UserFragment().newInstance(this, 1);
        activeUserFragment = new UserFragment().newInstance(this, 2);
        blockedUserFragment = new UserFragment().newInstance(this, 3);
//        deletedUserFragment = new UserFragment().newInstance(this, 4);

        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(getSupportFragmentManager(), getLifecycle(), Arrays.asList(
                new Fragment[]{
                        allUserFragment,
                        activeUserFragment,
                        blockedUserFragment,
//                        deletedUserFragment
                }
        ));

        userViewPager = binding.viewPagerUser;
        userTabLayout = binding.tabLayoutUser;

        userViewPager.setOffscreenPageLimit(pagerAdapter.getItemCount());
        userViewPager.setAdapter(pagerAdapter);
        userViewPager.setUserInputEnabled(false);
        userTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                userViewPager.setCurrentItem(tab.getPosition(), false);
            }

            @Override
            public void onTabUnselected(TabLayout.Tab tab) {

            }

            @Override
            public void onTabReselected(TabLayout.Tab tab) {

            }
        });
        userViewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
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

    private void setupShopTabLayout() {
        tabTitleList = new ArrayList<>();
        for (int i = 0; i < TAB_TITLE.length; i++) {
            userTabLayout.addTab(userTabLayout.newTab().setText(TAB_TITLE[i]));

            View tabView = View.inflate(this, R.layout.content_user_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            TabLayout.Tab tabLayoutTab = userTabLayout.getTabAt(i);
            tabTitleList.add(tabTextView);
            if (tabLayoutTab != null) {
                tabLayoutTab.setCustomView(tabView);
            }
        }
        userViewPager.setCurrentItem(0);
        updateTab(0);
    }

    private void updateTab(int position) {
        hideKeyboard(this);
        for (int i = 0; i < tabTitleList.size(); i++) {
            TextView tabTextView = tabTitleList.get(i);
            tabTextView.setText(TAB_TITLE[i]);
            tabTextView.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));
            tabTextView.setBackgroundColor(ContextCompat.getColor(this, android.R.color.transparent));
        }
        tabTitleList.get(position).setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        tabTitleList.get(position).setBackgroundResource(R.drawable.bg_tab_selected);

        String keyword = binding.editTextSearch.getText().toString().trim();
        filterCurrentFragment(keyword);
    }
}