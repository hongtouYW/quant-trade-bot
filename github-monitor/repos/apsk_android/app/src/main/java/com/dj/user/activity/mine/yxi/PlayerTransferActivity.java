package com.dj.user.activity.mine.yxi;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.view.animation.LinearInterpolator;
import android.widget.LinearLayout;

import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.TransferExpandableListAdapter;
import com.dj.user.databinding.ActivityPlayerTransferBinding;
import com.dj.user.model.request.RequestPlayerDetails;
import com.dj.user.model.request.RequestPlayerTransferAll;
import com.dj.user.model.request.RequestPlayerTransferPoint;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.CustomToast;
import com.dj.user.widget.PointBottomSheetDialogFragment;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class PlayerTransferActivity extends BaseActivity {

    private ActivityPlayerTransferBinding binding;
    private Member member;
    private Player player;
    private double totalPoints = 0.0;
    private TransferExpandableListAdapter transferExpandableListAdapter;
    private SwipeRefreshLayout swipeRefreshLayout;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private List<YxiProvider> groupList = new ArrayList<>();
    private Map<YxiProvider, List<YxiProvider>> childMap = new HashMap<>();
    private ObjectAnimator refreshAnimator;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPlayerTransferBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);
        AndroidBug5497Workaround.assistActivity(this);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.player_transfer_title), view -> showTransferConfirmationBottomSheet());
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getPlayerDetails();
        getProfile();
        getYxiTransferList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            player = new Gson().fromJson(json, Player.class);
        }
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
        swipeRefreshLayout = binding.swipeRefreshLayout;

        swipeRefreshLayout.setOnRefreshListener(this::getYxiTransferList);
        binding.imageViewRefresh.setOnClickListener(view -> {
            startRefreshAnimation();
            getPlayerDetails();
            getProfile();
        });
        transferExpandableListAdapter = new TransferExpandableListAdapter(this, groupList, childMap);
        binding.expandableListView.setAdapter(transferExpandableListAdapter);
        binding.expandableListView.setOnScrollListener(new android.widget.AbsListView.OnScrollListener() {
            @Override
            public void onScrollStateChanged(android.widget.AbsListView view, int scrollState) {
            }

            @Override
            public void onScroll(android.widget.AbsListView view, int firstVisibleItem, int visibleItemCount, int totalItemCount) {
                // Enable SwipeRefresh only when first item is visible and scrolled to top
                if (binding.expandableListView.getChildCount() == 0) {
                    swipeRefreshLayout.setEnabled(true);
                } else {
                    boolean enable = binding.expandableListView.getFirstVisiblePosition() == 0 &&
                            binding.expandableListView.getChildAt(0).getTop() == 0;
                    swipeRefreshLayout.setEnabled(enable);
                }
            }
        });
        transferExpandableListAdapter.setOnTransferActionListener(new TransferExpandableListAdapter.OnTransferActionListener() {
            @Override
            public void onAddClicked(YxiProvider yxiProvider, int position) {
                Player p = yxiProvider.getThePlayer();
                showTransferAmountConfirmationBottomSheet(true, position, p.getBalance());
            }

            @Override
            public void onMinusClicked(YxiProvider yxiProvider, int position) {
                Player p = yxiProvider.getThePlayer();
                showTransferAmountConfirmationBottomSheet(false, position, p.getBalance());
            }
        });
        binding.expandableListView.setOnChildClickListener((parent, v, groupPosition, childPosition, id) -> {
//                String selectedItem = childMap.get(groupList.get(groupPosition)).get(childPosition);
            return true;
        });
        binding.buttonConfirm.setOnClickListener(view -> showTransferConfirmationBottomSheet());
    }

    private void setupViewData() {
        binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
        binding.textViewPoint.setText(FormatUtils.formatAmount(player != null ? player.getBalance() : totalPoints));
    }

    private void showTransferAmountConfirmationBottomSheet(boolean isAdd, int position, double balance) {
        PointBottomSheetDialogFragment bottomSheet = PointBottomSheetDialogFragment.newInstance(isAdd, position, balance);
        bottomSheet.setOnAmountSelectedListener((position1, amount) -> transferExpandableListAdapter.updateTransferAmount(position1, amount));
        bottomSheet.show(getSupportFragmentManager(), "PointBottomSheet");
    }

    private void showTransferConfirmationBottomSheet() {
        showCustomConfirmationDialog(
                this,
                getString(R.string.player_transfer_confirm_title),
                getString(R.string.player_transfer_confirm_desc),
                "",
                getString(R.string.player_transfer_confirm_cancel),
                getString(R.string.player_transfer_confirm_okay),
                new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                        oneClickTransferYxiCredit();
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
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

    private void getPlayerDetails() {
        if (player == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerDetails request = new RequestPlayerDetails(player.getMember_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerDetails(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                player = response.getData();
                if (player != null) {
                    setupViewData();
                }
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

    private void getProfile() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(PlayerTransferActivity.this, CacheManager.KEY_USER_PROFILE, member);
                    setupViewData();
                }
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

    //    private void getYxiTransferList() {
//        dataPanel.setVisibility(View.GONE);
//        noDataPanel.setVisibility(View.GONE);
//        loadingPanel.setVisibility(View.VISIBLE);
//
//        String memberId = player != null ? player.getMember_id() : member.getMember_id();
//        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
//        RequestProfile request = new RequestProfile(memberId);
//        executeApiCall(this, apiService.getYxiTransferList(request), new ApiCallback<>() {
//            @Override
//            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
//                swipeRefreshLayout.setRefreshing(false);
//                totalPoints = 0.0;
//                List<YxiProvider> providerList = response.getData();
//                // Handle empty or null list early
//                if (providerList == null || providerList.isEmpty()) {
//                    dataPanel.setVisibility(View.GONE);
//                    noDataPanel.setVisibility(View.VISIBLE);
//                    loadingPanel.setVisibility(View.GONE);
//                    return;
//                }
//                // Calculate total points
//                totalPoints = providerList.stream()
//                        .filter(p -> p.getPlayer() != null)
//                        .flatMap(p -> p.getPlayer().stream())
//                        .mapToDouble(Player::getBalance)
//                        .sum();
//                setupViewData();
//                // Rebuild group and child data
//                groupList.clear();
//                groupList.addAll(providerList);
//                groupList.add(0, new YxiProvider(true)); // Header or special item
//                childMap.clear();
//                for (YxiProvider provider : providerList) {
//                    List<Player> players = provider.getPlayer();
//                    if (players != null && !players.isEmpty()) {
//                        childMap.put(provider, Collections.singletonList(provider));
//                    } else {
//                        childMap.put(provider, new ArrayList<>());
//                    }
//                }
//                transferExpandableListAdapter.notifyDataSetChanged();
//                dataPanel.setVisibility(View.VISIBLE);
//                noDataPanel.setVisibility(View.GONE);
//                loadingPanel.setVisibility(View.GONE);
//            }
//
//            @Override
//            public boolean onApiError(int code, String message) {
//                swipeRefreshLayout.setRefreshing(false);
//                dataPanel.setVisibility(View.GONE);
//                noDataPanel.setVisibility(View.VISIBLE);
//                loadingPanel.setVisibility(View.GONE);
//                return false;
//            }
//
//            @Override
//            public boolean onFailure(Throwable t) {
//                swipeRefreshLayout.setRefreshing(false);
//                dataPanel.setVisibility(View.GONE);
//                noDataPanel.setVisibility(View.VISIBLE);
//                loadingPanel.setVisibility(View.GONE);
//                return false;
//            }
//        }, false);
//    }
    private void getYxiTransferList() {
        // Keep current UI visible unless it's the very first load
        boolean isFirstLoad = groupList.isEmpty();

        if (isFirstLoad) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        } else {
            loadingPanel.setVisibility(View.GONE);
        }

        String memberId = player != null ? player.getMember_id() : member.getMember_id();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(memberId);

        executeApiCall(this, apiService.getYxiTransferList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
                swipeRefreshLayout.setRefreshing(false);
                List<YxiProvider> providerList = response.getData();

                if (providerList == null || providerList.isEmpty()) {
                    if (isFirstLoad) {
                        dataPanel.setVisibility(View.GONE);
                        noDataPanel.setVisibility(View.VISIBLE);
                        loadingPanel.setVisibility(View.GONE);
                    } else {
                        CustomToast.showTopToast(PlayerTransferActivity.this, "暂无数据更新");
                    }
                    return;
                }

                // Calculate total points
                totalPoints = providerList.stream()
                        .filter(p -> p.getPlayer() != null)
                        .flatMap(p -> p.getPlayer().stream())
                        .mapToDouble(Player::getBalance)
                        .sum();
                setupViewData();

                if (isFirstLoad) {
                    // First time setup
                    groupList.addAll(providerList);
                    groupList.add(0, new YxiProvider(true)); // Header or special item

                    childMap.clear();
//                    for (YxiProvider provider : providerList) {
//                        List<Player> players = provider.getPlayer();
//                        if (players != null && !players.isEmpty()) {
//                            childMap.put(provider, Collections.singletonList(provider));
//                        } else {
//                            childMap.put(provider, new ArrayList<>());
//                        }
//                    }
                    transferExpandableListAdapter.notifyDataSetChanged();
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                    loadingPanel.setVisibility(View.GONE);
                } else {
                    // Just update changed balances
                    transferExpandableListAdapter.updateProviderList(providerList);
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                swipeRefreshLayout.setRefreshing(false);
                if (isFirstLoad) {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                swipeRefreshLayout.setRefreshing(false);
                if (isFirstLoad) {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
                return false;
            }
        }, false);
    }

    private void oneClickTransferYxiCredit() {
        String memberId = player != null ? player.getMember_id() : member.getMember_id();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTransferAll request = new RequestPlayerTransferAll(memberId);
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "oneClickTransferYxiCredit IP: " + publicIp);
            executeApiCall(this, apiService.oneClickTransferYxiCredit(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    totalPoints = 0.0;
                    getPlayerDetails();
                    getProfile();
                    getYxiTransferList();
                    CustomToast.showTopToast(PlayerTransferActivity.this, "转移成功");
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
        });
    }

    private void transferPoint() {
        double amount = 0.00;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTransferPoint request = new RequestPlayerTransferPoint(player.getMember_id(), player.getGamemember_id(), player.getGamemember_id(), amount);
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "transferPoint IP: " + publicIp);
            executeApiCall(this, apiService.transferPoint(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    getPlayerDetails();
                    getYxiTransferList();
                    CustomToast.showTopToast(PlayerTransferActivity.this, "转移成功");
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
        });
    }
}