package com.dj.user.fragment.dashboard;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Shader;
import android.os.Bundle;
import android.text.TextPaint;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.LinearInterpolator;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.dj.user.R;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.activity.mine.topup.TopUpActivity;
import com.dj.user.activity.mine.transaction.TransactionActivity;
import com.dj.user.activity.mine.withdraw.WithdrawActivity;
import com.dj.user.activity.mine.yxi.PlayerTransferActivity;
import com.dj.user.activity.mine.yxi.YxiDetailsActivity;
import com.dj.user.activity.mine.yxi.YxiWebViewActivity;
import com.dj.user.adapter.ProfileActionGridItemAdapter;
import com.dj.user.adapter.YxiCategoryRecyclerViewAdapter;
import com.dj.user.adapter.YxiProviderRecyclerAdapter;
import com.dj.user.databinding.FragmentHomeBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemCategory;
import com.dj.user.model.ItemGrid;
import com.dj.user.model.request.RequestPlayer;
import com.dj.user.model.request.RequestPlayerLogin;
import com.dj.user.model.request.RequestPlayerTopUpWithdraw;
import com.dj.user.model.request.RequestPlayerTransferAll;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestYxiBookmark;
import com.dj.user.model.request.RequestYxiBookmarkDelete;
import com.dj.user.model.request.RequestYxiProvider;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Notification;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Slider;
import com.dj.user.model.response.YxiBookmark;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.GridSpacingItemDecoration;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;

public class HomeFragment extends BaseFragment {

    private FragmentHomeBinding binding;
    private Member member;
    private Context context;
    //    private YxiProvider currentYxiProvider;
    //    private PlayerListDialog playerListDialog;
    private YxiCategoryRecyclerViewAdapter yxiCategoryRecyclerViewAdapter;
    private YxiProviderRecyclerAdapter yxiProviderRecyclerAdapter;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private ItemCategory selectedCategory;
    private Player selectedPlayer;
    private String lastLoadedCategory = null;
    private String currentRequestId = null;
    private boolean isYxiProcessing = false;
    private int unreadNotification = 0;
    private int unreadSlider = 0;

    //    private ViewPager2 viewPager;
//    private int currentIndex = 0;
//    private Handler handler = new Handler();
//    private Runnable runnable;
    private ObjectAnimator refreshAnimator;

    private final HashMap<String, List<YxiProvider>> yxiProviderCache = new HashMap<>();

//    private final List<Integer> images = Arrays.asList(
//            R.drawable.img_home_1,
//            R.drawable.img_home_2
//    );

    public HomeFragment newInstance(Context context) {
        HomeFragment fragment = new HomeFragment();
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

//    @Override
//    public void onPause() {
//        super.onPause();
//        stopCarousel();
//    }

    @Override
    public void onResume() {
        super.onResume();
        if (member == null) {
            CacheManager.clearAll(context);
            ((DashboardActivity) context).startAppActivity(new Intent(context, LoginActivity.class),
                    null, true, true, true);
            return;
        }
//        startCarousel();
        getProfile();
        getYxiProviderList();
        getNotificationList();
//        getPlayerList(currentYxiProvider);
    }

//    @Override
//    public void onDestroy() {
//        super.onDestroy();
//        stopCarousel();
//        handler.removeCallbacks(runnable);
//    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentHomeBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
//        setupCarousel();
        setupActionGrid();
        setupCategoryListView();
        setupViewData();
        return binding.getRoot();
    }

    private void setupUI() {
        binding.panelNotification.setOnClickListener(view ->
                ((DashboardActivity) context).startAppActivity(new Intent(context, NewsCentreActivity.class),
                        null, false, false, true
                ));
        binding.panelBalance.setOnClickListener(view -> {
            oneClickTransferYxiCredit();
//            if (selectedPlayer == null) {
//                getProfile();
//            } else {
//                playerWithdraw(selectedPlayer);
//            }
        });
        binding.textViewCurrency.setText(getString(R.string.placeholder_currency_myr));
        binding.textViewCurrency.post(() -> {
            TextPaint paint = binding.textViewCurrency.getPaint();
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewCurrency.getTextSize(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewCurrency.invalidate();
        });
        binding.textViewRefresh.post(() -> {
            TextPaint paint = binding.textViewRefresh.getPaint();
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewRefresh.getTextSize(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewRefresh.invalidate();
        });

        binding.panelSlider.setVisibility(View.GONE);
        binding.textViewSlider.setSelected(true);

        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        RecyclerView yxiProviderRecyclerView = binding.recyclerViewYxiProvider;
        yxiProviderRecyclerView.setItemViewCacheSize(20);
        yxiProviderRecyclerView.setDrawingCacheEnabled(true);
        yxiProviderRecyclerView.setDrawingCacheQuality(View.DRAWING_CACHE_QUALITY_HIGH);

        int spanCount = 4;
        int horizontalSpacing = getResources().getDimensionPixelSize(R.dimen.provider_horizontal_spacing);
        int verticalSpacing = getResources().getDimensionPixelSize(R.dimen.provider_vertical_spacing);
        boolean includeEdge = true;
        yxiProviderRecyclerView.setLayoutManager(new GridLayoutManager(context, spanCount));
        yxiProviderRecyclerView.addItemDecoration(new GridSpacingItemDecoration(spanCount, horizontalSpacing, verticalSpacing, includeEdge));

        yxiProviderRecyclerAdapter = new YxiProviderRecyclerAdapter(context);
        yxiProviderRecyclerView.setAdapter(yxiProviderRecyclerAdapter);
        yxiProviderRecyclerAdapter.setOnYxiProviderClickListener(this::proceedToYxi);
    }

//    private void setupCarousel() {
//        viewPager = binding.viewPager;
//        viewPager.setAdapter(new CarouselAdapter(context, images));
//        binding.dotsIndicatorBanner.attachTo(viewPager);
//
//        viewPager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
//            @Override
//            public void onPageSelected(int position) {
//                currentIndex = position;
//            }
//        });
//        runnable = new Runnable() {
//            @Override
//            public void run() {
//                if (currentIndex == images.size()) currentIndex = 0;
//                viewPager.setCurrentItem(currentIndex++, true);
//                handler.postDelayed(this, 5000);
//            }
//        };

    /// /        startCarousel();
//    }

//    private void startCarousel() {
//        handler.postDelayed(runnable, 5000);
//    }

//    private void stopCarousel() {
//        handler.removeCallbacks(runnable);
//    }
    private void setupActionGrid() {
        List<ItemGrid> actionItems = List.of(
                new ItemGrid(1, R.drawable.ic_profile_top_up, getString(R.string.action_panel_top_up), ""),
                new ItemGrid(2, R.drawable.ic_profile_transaction, getString(R.string.action_panel_transaction), ""),
                new ItemGrid(3, R.drawable.ic_profile_withdrawal, getString(R.string.action_panel_withdraw), ""),
                new ItemGrid(4, R.drawable.ic_profile_wallet, getString(R.string.action_panel_wallet), "")
        );

        ProfileActionGridItemAdapter adapter = new ProfileActionGridItemAdapter(context, actionItems);
        binding.gridViewProfileAction.setAdapter(adapter);

        binding.gridViewProfileAction.setOnItemClickListener((parent, view, position, id) -> {
            ItemGrid clicked = actionItems.get(position);
            switch (clicked.getId()) {
                case 1:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, TopUpActivity.class),
                            null, false, false, true);
                    break;
                case 2:
                    Bundle bundle = new Bundle();
                    bundle.putInt("tab", 1);
                    ((DashboardActivity) context).startAppActivity(new Intent(context, TransactionActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, WithdrawActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, PlayerTransferActivity.class),
                            null, false, false, true);
                    break;
            }
        });
    }

    private void setupCategoryListView() {
        List<ItemCategory> categoryItemList = new ArrayList<>();
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_all, R.drawable.ic_game_all_selected, getString(R.string.home_yxi_category_all), "all"));
//        categoryItemList.add(new ItemCategory(R.drawable.ic_game_all, R.drawable.ic_game_all_selected, getString(R.string.home_yxi_category_popular), "hot"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game, R.drawable.ic_game_selected, getString(R.string.home_yxi_category_app), "app"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_slot, R.drawable.ic_game_slot_selected, getString(R.string.home_yxi_category_slots), "slot"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_live, R.drawable.ic_game_live_selected, getString(R.string.home_yxi_category_live), "live"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_sport, R.drawable.ic_game_sport_selected, getString(R.string.home_yxi_category_sports), "sport"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_fishing, R.drawable.ic_game_fishing_selected, getString(R.string.home_yxi_category_fishing), "fish"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_cock, R.drawable.ic_game_cock_selected, getString(R.string.home_yxi_category_cock), "cock"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_lottery, R.drawable.ic_game_lottery_selected, getString(R.string.home_yxi_category_lottery), "lottery"));
        categoryItemList.add(new ItemCategory(R.drawable.ic_game_card, R.drawable.ic_game_card_selected, getString(R.string.home_yxi_category_cards), "card"));

        selectedCategory = categoryItemList.get(0);
        yxiCategoryRecyclerViewAdapter = new YxiCategoryRecyclerViewAdapter(context);
        yxiCategoryRecyclerViewAdapter.addCategoryList(categoryItemList);
        LinearLayoutManager layoutManager = new LinearLayoutManager(context, LinearLayoutManager.HORIZONTAL, false);
        binding.recyclerViewYxiCategory.setLayoutManager(layoutManager);
        binding.recyclerViewYxiCategory.setAdapter(yxiCategoryRecyclerViewAdapter);
        yxiCategoryRecyclerViewAdapter.setOnItemClickListener((position, category) -> {
            selectedCategory = category;
            yxiCategoryRecyclerViewAdapter.setSelectedPosition(position);
            binding.recyclerViewYxiCategory.post(() -> {
                View child = layoutManager.findViewByPosition(position);
                if (child != null) {
                    int itemWidth = child.getWidth();
                    layoutManager.scrollToPositionWithOffset(position, binding.recyclerViewYxiCategory.getWidth() / 2 - itemWidth / 2);
                }
            });
            getYxiProviderList();
        });
//        ItemCategoryListViewAdapter itemCategoryListViewAdapter = new ItemCategoryListViewAdapter(context);
//        itemCategoryListViewAdapter.addList(categoryItemList);
//        binding.listViewCategory.setAdapter(itemCategoryListViewAdapter);
//        itemCategoryListViewAdapter.setOnItemClickListener((position, object) -> {
//            selectedCategory = (ItemCategory) object;
//            itemCategoryListViewAdapter.setSelectedPosition(position);
//            getYxiProviderList();
//        });
    }

    private void setupViewData() {
        if (member == null) {
            return;
        }
        binding.textViewUsername.setText(FormatUtils.formatMsianPhone(member.getMember_login()));
        binding.textViewUsername.setSelected(true);
        String vipName = "vip_" + member.getVip();
        int resId = getResources().getIdentifier(vipName, "drawable", context.getPackageName());
        binding.imageViewVip.setImageResource(resId);

        binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
        binding.textViewBalance.post(() -> {
            TextPaint paint = binding.textViewBalance.getPaint();
//            float width = paint.measureText(binding.textViewBalance.getText().toString());
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewBalance.getMeasuredHeight(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewBalance.invalidate();
        });
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

    private void setupNoDataOrErrorDisplay() {
        yxiProviderRecyclerAdapter.setData(null);
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.VISIBLE);
        loadingPanel.setVisibility(View.GONE);
    }

    private void proceedToYxi(YxiProvider yxiProvider, Player player) {
        if (yxiProvider.isDirectToLobby()) {
            getYxiUrl(player);
            return;
        }
        resetYxiClick();
        Bundle bundle = new Bundle();
        bundle.putString("data", new Gson().toJson(player));
        ((DashboardActivity) context).startAppActivity(new Intent(context, YxiDetailsActivity.class),
                bundle, false, false, true
        );
    }

    private void openInAppBrowser(String url, Player player) {
        resetYxiClick();
        if (StringUtil.isNullOrEmpty(url)) {
            return;
        }
        Bundle bundle = new Bundle();
        bundle.putString("data", url);
        bundle.putBoolean("isLobby", true);
        bundle.putString("player", new Gson().toJson(player));
        ((DashboardActivity) context).startAppActivity(new Intent(context, YxiWebViewActivity.class),
                bundle, false, false, true);
    }

    private void getProfile() {
        if (member == null) return;
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(context, CacheManager.KEY_USER_PROFILE, member);
                }
                setupViewData();
                ((DashboardActivity) context).updateProfileImage();
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return false;
            }
        }, false);
    }

    private void oneClickTransferYxiCredit() {
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayerTransferAll request = new RequestPlayerTransferAll(member.getMember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "oneClickTransferYxiCredit IP: " + publicIp);
            ((DashboardActivity) context).executeApiCall(context, apiService.oneClickTransferYxiCredit(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    getProfile();
                }

                @Override
                public boolean onApiError(int code, String message) {
                    stopRefreshAnimation();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    stopRefreshAnimation();
                    return true;
                }
            }, false);
        });
    }

    private void getYxiProviderList() {
        if (member == null) return;
        String categoryType = selectedCategory.getType();
        // Generate a unique request ID
        String requestId = categoryType + "_" + System.currentTimeMillis();
        currentRequestId = requestId;
        // If the same category was just loaded, skip
        if (categoryType.equalsIgnoreCase(lastLoadedCategory)) {
            return;
        }
        lastLoadedCategory = categoryType;
        // Clear UI immediately (avoid showing old data)
        yxiProviderRecyclerAdapter.setData(new ArrayList<>());
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        // Use cache if available
        List<YxiProvider> cachedList = yxiProviderCache.get(categoryType);
        if (cachedList != null && !cachedList.isEmpty()) {
            yxiProviderRecyclerAdapter.setData(cachedList);
            dataPanel.setVisibility(View.VISIBLE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.GONE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestYxiProvider request = new RequestYxiProvider(member.getMember_id(),
                categoryType.equalsIgnoreCase("all") ? null : categoryType);

        ((DashboardActivity) context).executeApiCall(context, apiService.getYxiProviderList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
                // Ignore stale responses
                if (!requestId.equals(currentRequestId)) return;

                List<YxiProvider> yxiProviderList = response.getData();
                yxiProviderCache.put(categoryType, yxiProviderList);
                yxiProviderRecyclerAdapter.setData(yxiProviderList);

                if (yxiProviderList.isEmpty()) {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                } else {
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                }
                loadingPanel.setVisibility(View.GONE);
            }

            @Override
            public boolean onApiError(int code, String message) {
                if (!requestId.equals(currentRequestId)) return false;
                setupNoDataOrErrorDisplay();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                if (!requestId.equals(currentRequestId)) return false;
                setupNoDataOrErrorDisplay();
                return false;
            }
        }, false);
    }

    private void resetYxiClick() {
        if (!isYxiProcessing) return;
        isYxiProcessing = false;
        ((DashboardActivity) context).dismissLoadingDialog();
    }

    private void proceedToYxi(YxiProvider yxiProvider) {
        if (yxiProvider == null) {
            return;
        }
        if (isYxiProcessing) {
            return;
        }
        isYxiProcessing = true;
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayer request = new RequestPlayer(member.getMember_id(), yxiProvider.getProvider_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.proceedToYxi(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                List<Player> playerList = response.getPlayer();
                if (playerList != null && !playerList.isEmpty()) {
                    Player player = playerList.get(0);
                    player.setProvider(yxiProvider);
                    if (yxiProvider.isDirectToLobby()) {
                        openInAppBrowser(response.getUrl(), player);
                        return;
                    }
                    resetYxiClick();
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(player));
                    ((DashboardActivity) context).startAppActivity(new Intent(context, YxiDetailsActivity.class),
                            bundle, false, false, true
                    );
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                resetYxiClick();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                resetYxiClick();
                return false;
            }
        }, false);
    }

    private void getPlayerList(YxiProvider yxiProvider) {
        if (yxiProvider == null) {
            return;
        }
        if (isYxiProcessing) {
            return;
        }
        isYxiProcessing = true;
//        currentYxiProvider = yxiProvider;
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayer request = new RequestPlayer(member.getMember_id(), yxiProvider.getProvider_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getPlayerList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Player>> response) {
                List<Player> playerList = response.getData();
                if (playerList != null && !playerList.isEmpty()) {
                    Player player = playerList.get(0);
                    player.setProvider(yxiProvider);
                    playerTopUp(yxiProvider, player);
                } else {
                    addPlayer(yxiProvider);
                }
//                if (playerListDialog != null && playerListDialog.isShowing()) {
//                    // Refresh existing sheet
//                    playerListDialog.updatePlayers(new ArrayList<>(playerList));
//                } else {
//                    // First time open
//                    playerListDialog = new PlayerListDialog(
//                            context, currentYxiProvider.isBookmark(), new ArrayList<>(playerList),
//                            (player, pos) -> {
//                                player.setProvider(currentYxiProvider);
//                                Bundle bundle = new Bundle();
//                                bundle.putString("data", new Gson().toJson(player));
//                                ((DashboardActivity) context).startAppActivity(new Intent(context, YxiDetailsActivity.class),
//                                        bundle, false, false, true
//                                );
//                            },
//                            () -> addPlayer(currentYxiProvider),
//                            () -> {
//                                if (currentYxiProvider.isBookmark()) {
//                                    unfavYxi(currentYxiProvider);
//                                } else {
//                                    favYxi(currentYxiProvider);
//                                }
//                            }
//                    );
//                    playerListDialog.show();
//                }
//                ((DashboardActivity) context).dismissLoadingDialog();
            }

            @Override
            public boolean onApiError(int code, String message) {
                resetYxiClick();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                resetYxiClick();
                return false;
            }
        }, false);
    }

    private void addPlayer(YxiProvider yxiProvider) {
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayer request = new RequestPlayer(member.getMember_id(), yxiProvider.getProvider_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.addPlayer(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player player = response.getData();
                player.setLogin(response.getPlayer_login());
                player.setPass(response.getPlayer_pass());
                player.setProvider(yxiProvider);
                playerTopUp(yxiProvider, player);
//                PlayerDialog playerDialog = new PlayerDialog(context,
//                        yxi.getProvider_name(), player, "充值和开启游戏",
//                        () -> {
//                            player.setProvider(currentYxiProvider);
//                            Bundle bundle = new Bundle();
//                            bundle.putString("data", new Gson().toJson(player));
//                            ((DashboardActivity) context).startAppActivity(new Intent(context, YxiDetailsActivity.class),
//                                    bundle, false, false, true
//                            );
//                        }
//                );
//                playerDialog.show();
//                getPlayerList(yxi);
            }

            @Override
            public boolean onApiError(int code, String message) {
                resetYxiClick();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                resetYxiClick();
                return false;
            }
        }, false);
    }

    private void favYxi(YxiProvider yxi) {
        if (yxi == null) {
            return;
        }
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestYxiBookmark request = new RequestYxiBookmark(member.getMember_id(), yxi.getProvider_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.favYxi(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<YxiBookmark> response) {
                YxiBookmark yxiBookmark = response.getData();
//                if (playerListDialog != null && playerListDialog.isShowing()) {
//                    playerListDialog.updateFavStatus(true);
//                }
                yxiProviderRecyclerAdapter.updateFavStatus(yxi.getProvider_id(), true, Long.parseLong(yxiBookmark.getProviderbookmark_id()));
                ((DashboardActivity) context).dismissLoadingDialog();
            }

            @Override
            public boolean onApiError(int code, String message) {
                ((DashboardActivity) context).dismissLoadingDialog();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                ((DashboardActivity) context).dismissLoadingDialog();
                return false;
            }
        }, false);
    }

    private void unfavYxi(YxiProvider yxi) {
        if (yxi == null) {
            return;
        }
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestYxiBookmarkDelete request = new RequestYxiBookmarkDelete(member.getMember_id(), yxi.getProviderbookmark_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.unfavYxi(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<YxiBookmark> response) {
//                if (playerListDialog != null && playerListDialog.isShowing()) {
//                    playerListDialog.updateFavStatus(false);
//                }
                yxiProviderRecyclerAdapter.updateFavStatus(yxi.getProvider_id(), false, null);
                ((DashboardActivity) context).dismissLoadingDialog();
            }

            @Override
            public boolean onApiError(int code, String message) {
                ((DashboardActivity) context).dismissLoadingDialog();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                ((DashboardActivity) context).dismissLoadingDialog();
                return false;
            }
        }, false);
    }

    private void playerTopUp(YxiProvider yxiProvider, Player player) {
        if (player == null) {
            return;
        }
        selectedPlayer = player;
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerTopUp IP: " + publicIp);
            if (publicIp == null) {
                resetYxiClick();
                return;
            }
            ((DashboardActivity) context).executeApiCall(context, apiService.playerTopUpAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    List<Player> playerList = response.getPlayer();
                    if (playerList != null && !playerList.isEmpty()) {
                        Player toppedUpPlayer = playerList.get(0);
                        player.setBalance(toppedUpPlayer.getBalanceStr());
                    }
                    proceedToYxi(yxiProvider, player);
                }

                @Override
                public boolean onApiError(int code, String message) {
                    proceedToYxi(yxiProvider, player);
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    proceedToYxi(yxiProvider, player);
                    return false;
                }
            }, false);
        });
    }

    private void playerWithdraw(Player player) {
        if (player == null) {
            return;
        }
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerWithdraw IP: " + publicIp);
            ((DashboardActivity) context).executeApiCall(context, apiService.playerWithdrawAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    getProfile();
                }

                @Override
                public boolean onApiError(int code, String message) {
                    stopRefreshAnimation();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    stopRefreshAnimation();
                    return true;
                }
            }, false);
        });
    }

    private void getYxiUrl(Player player) {
        if (player == null) {
            return;
        }
        ((DashboardActivity) context).showFullScreenLoadingDialog(context);
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayerLogin request = new RequestPlayerLogin(player.getMember_id(), player.getGamemember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getYxiUrl(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<String> response) {
                resetYxiClick();
                openInAppBrowser(response.getUrl(), player);
            }

            @Override
            public boolean onApiError(int code, String message) {
                resetYxiClick();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                resetYxiClick();
                return false;
            }
        }, false);
    }

    private void updateNotificationIcon() {
        int totalUnread = unreadNotification + unreadSlider;
        binding.textViewBadge.setText(totalUnread > 99 ? "99+" : String.valueOf(totalUnread));
        binding.textViewBadge.setVisibility(totalUnread > 0 ? View.VISIBLE : View.GONE);
//        binding.imageViewNotification.setImageResource(
//                hasUnread
//                        ? R.drawable.ic_notification_circled_unread
//                        : R.drawable.ic_notification_circled
//        );
    }

    private void getNotificationList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getNotificationList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Notification>> response) {
                List<Notification> notificationList = response.getData();
                unreadNotification = 0;

                if (notificationList != null) {
                    for (Notification n : notificationList) {
                        if (!n.getIs_read()) {
                            unreadNotification += 1;
                        }
                    }
                }
                getSliderList();
            }

            @Override
            public boolean onApiError(int code, String message) {
                unreadNotification = 0;
                getSliderList();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                unreadNotification = 0;
                getSliderList();
                return false;
            }
        }, false);
    }

    private void getSliderList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getSliderList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Slider>> response) {
                List<Slider> sliderList = response.getData();
                unreadSlider = 0;
                if (sliderList != null) {
                    for (Slider s : sliderList) {
                        if (!s.isRead()) {
                            unreadSlider += 1;
                        }
                    }
                }
                updateNotificationIcon();
            }

            @Override
            public boolean onApiError(int code, String message) {
                unreadSlider = 0;
                updateNotificationIcon();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                unreadSlider = 0;
                updateNotificationIcon();
                return false;
            }
        }, false);
    }
}
