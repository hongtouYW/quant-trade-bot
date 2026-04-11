package com.dj.user.fragment.news;

import android.content.Context;
import android.content.Intent;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.viewpager2.widget.ViewPager2;

import com.dj.user.R;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.activity.WebViewActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.adapter.ViewPagerAdapter;
import com.dj.user.databinding.FragmentNewsCustomerCareBinding;
import com.dj.user.enums.HelpCategory;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.fragment.HelpFragment;
import com.dj.user.model.ItemSupport;
import com.dj.user.model.request.RequestFAQ;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.FAQ;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.google.android.material.tabs.TabLayout;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class NewsCustomerCareFragment extends BaseFragment {

    private FragmentNewsCustomerCareBinding binding;
    private Context context;
    private Member member;
    private TabLayout helpTabLayout;
    private ViewPager2 helpViewPager;

    private List<TextView> tabTitleList;
    private HelpCategory selectedCategory;

    private final List<HelpCategory> helpCategories = Arrays.asList(
            HelpCategory.GENERAL,
//            HelpCategory.TRANSACTION,
//            HelpCategory.TRANSFER,
            HelpCategory.VIP
    );
    private final List<HelpFragment> fragments = new ArrayList<>();
    private String supportUrl, supportTitle, telegramUrl, whatsappUrl;

    public NewsCustomerCareFragment newInstance(Context context) {
        NewsCustomerCareFragment fragment = new NewsCustomerCareFragment();
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
        binding = FragmentNewsCustomerCareBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupHelpViewPager();
        setupHelpTabLayout();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getSupportUrl();
    }

    private void setupUI() {
        String icon = String.format("%s%s", getString(R.string.avatar_base_url), ((NewsCentreActivity) context).getRandomAgentImage());
        Picasso.get().load(icon).into(binding.imageViewAgent, new Callback() {
            @Override
            public void onSuccess() {
            }

            @Override
            public void onError(Exception e) {
                binding.imageViewAgent.setImageResource(R.drawable.ic_help);
            }
        });

        binding.panelSupport.removeAllViews();
        List<ItemSupport> items = new ArrayList<>();
        items.add(new ItemSupport(R.drawable.ic_live_agent, v -> {
            if (!StringUtil.isNullOrEmpty(supportUrl)) {
                Bundle bundle = new Bundle();
                bundle.putString("data", supportUrl);
                bundle.putString("title", supportTitle);
                ((NewsCentreActivity) context).startAppActivity(new Intent(context, WebViewActivity.class),
                        bundle, false, false, true);
            } else {
                Toast.makeText(context, context.getString(R.string.help_support_unavailable), Toast.LENGTH_SHORT).show();
            }
        }));
        items.add(new ItemSupport(R.drawable.ic_telegram, v -> {
            if (!StringUtil.isNullOrEmpty(telegramUrl)) {
                startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(telegramUrl)));
            } else {
                Toast.makeText(context, context.getString(R.string.help_support_unavailable), Toast.LENGTH_SHORT).show();
            }
        }));
        items.add(new ItemSupport(R.drawable.ic_wa, v -> {
            if (!StringUtil.isNullOrEmpty(whatsappUrl)) {
                startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(whatsappUrl)));
            } else {
                Toast.makeText(context, context.getString(R.string.help_support_unavailable), Toast.LENGTH_SHORT).show();
            }
        }));
//                while (items.size() < 3) {
//                    items.add(null);
//                }
        for (int i = 0; i < 3; i++) {
            addSupportButton(items.get(i), i == 2);
        }
    }

    private void addSupportButton(ItemSupport item, boolean isLast) {
        LinearLayout panel = new LinearLayout(context);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.MATCH_PARENT, 1f);
        if (!isLast) {
            params.setMarginEnd(FormatUtils.dpToPx(context, 8));
        }
        panel.setLayoutParams(params);
        panel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_button_bordered));
        panel.setGravity(Gravity.CENTER);

        if (item != null) {
            panel.setOnClickListener(item.getListener());
            ImageView icon = new ImageView(context);
            LinearLayout.LayoutParams iconParams = new LinearLayout.LayoutParams(FormatUtils.dpToPx(context, 25), FormatUtils.dpToPx(context, 25));
            icon.setLayoutParams(iconParams);
            icon.setImageResource(item.getIconRes());

            panel.addView(icon);
        } else {
            panel.setVisibility(View.INVISIBLE);
        }
        binding.panelSupport.addView(panel);
    }

    private void setupHelpViewPager() {
        for (int i = 0; i < helpCategories.size(); i++) {
            fragments.add(new HelpFragment().newInstance(context, i));
        }
        ViewPagerAdapter pagerAdapter = new ViewPagerAdapter(((NewsCentreActivity) context).getSupportFragmentManager(), getLifecycle(),
                new ArrayList<>(fragments)
        );

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
                updateIndicatorPosition(position);
                selectedCategory = helpCategories.get(position);
            }

            @Override
            public void onPageScrollStateChanged(int state) {
                super.onPageScrollStateChanged(state);
            }
        });
    }

    private void setupHelpTabLayout() {
        tabTitleList = new ArrayList<>();
        for (HelpCategory category : helpCategories) {
            View tabView = View.inflate(context, R.layout.content_tab, null);
            TextView tabTextView = tabView.findViewById(R.id.textView_tab);
            tabTextView.setText(category.getTitle());
            tabTitleList.add(tabTextView);

            TabLayout.Tab tab = helpTabLayout.newTab().setCustomView(tabView);
            helpTabLayout.addTab(tab);
        }
        helpTabLayout.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            @Override
            public void onTabSelected(TabLayout.Tab tab) {
                int position = tab.getPosition();
                updateTab(position);
                updateIndicatorPosition(position);
                helpViewPager.setCurrentItem(position, false);
                selectedCategory = helpCategories.get(position);
                getFAQList();
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
            updateIndicatorPosition(0);
            helpViewPager.setCurrentItem(0, false);
            selectedCategory = helpCategories.get(0);
            getFAQList();
        });
    }

    private void updateIndicatorPosition(int position) {
        if (position < 0 || position >= tabTitleList.size()) return;

        TextView textView = tabTitleList.get(position);
        textView.post(() -> {
            int textWidth = textView.getWidth();
            View tabView = ((ViewGroup) helpTabLayout.getChildAt(0)).getChildAt(position);

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
            tabTextView.setText(helpCategories.get(i).getTitle());
            tabTextView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07_50a));
            tabTextView.setTypeface(normalTypeface);
        }
        TextView selectedTab = tabTitleList.get(position);
        selectedTab.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
        selectedTab.setTypeface(boldTypeface);
    }

    private void updateViewData(List<FAQ> faqList) {
        if (!isAdded()) {
            return;
        }
        int position = helpViewPager.getCurrentItem();
        fragments.get(position).updateView(faqList);
    }

    private void getSupportUrl() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((NewsCentreActivity) context).executeApiCall(context, apiService.getSupportUrl(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                if (!isAdded()) {
                    return;
                }
                supportUrl = response.getSupport();
                supportTitle = getString(R.string.help_support_1);
                telegramUrl = response.getTelegramsupport();
                whatsappUrl = response.getWhatsappsupport();
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

    private void getFAQList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestFAQ request = new RequestFAQ(member.getMember_id(), selectedCategory.getValue());
        ((NewsCentreActivity) context).executeApiCall(context, apiService.getFAQList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<FAQ>> response) {
                if (!isAdded()) {
                    return;
                }
                List<FAQ> faqList = response.getData();
                updateViewData(faqList);
            }

            @Override
            public boolean onApiError(int code, String message) {
                updateViewData(null);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                updateViewData(null);
                return false;
            }
        }, false);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}