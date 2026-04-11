package com.dj.shop.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.AbsListView;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.dj.shop.R;
import com.dj.shop.activity.dashboard.DashboardSearchDetailsActivity;
import com.dj.shop.activity.transaction.ActionUserDetailsActivity;
import com.dj.shop.activity.transaction.TransactionDetailsActivity;
import com.dj.shop.activity.transaction.TransactionHistoryActivity;
import com.dj.shop.adapter.HistoryListViewAdapter;
import com.dj.shop.databinding.FragmentUserBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.enums.TransactionType;
import com.dj.shop.model.request.RequestHistory;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.History;
import com.dj.shop.model.response.Pagination;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;
import com.google.gson.Gson;

import java.util.List;

public class HistoryFragment extends Fragment {
    private FragmentUserBinding binding;
    private Context context;
    private int page = 0;
    private Shop shop;
    private View footerLoadingView;
    private ListView userListView;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private HistoryListViewAdapter historyListViewAdapter;
    private boolean allowFilterCallback = false;
    private boolean hasLoadedOnce = false;
    private int apiPage = 1;
    private boolean isLoading = false;
    private boolean hasNextPage = true;

    public HistoryFragment newInstance(Context context, int page) {
        HistoryFragment fragment = new HistoryFragment();
        fragment.context = context;
        fragment.page = page;
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
        binding = FragmentUserBinding.inflate(inflater, container, false);
        context = getContext();
        shop = CacheManager.getObject(context, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (!hasLoadedOnce) {
            hasLoadedOnce = true;
            resetAndLoad();
        }
    }

    private void resetAndLoad() {
        apiPage = 1;
        hasNextPage = true;
        isLoading = false;
        historyListViewAdapter.removeList();
        getHistoryList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        userListView = binding.listViewUser;
        footerLoadingView = LayoutInflater.from(context).inflate(R.layout.footer_loading, userListView, false);
        historyListViewAdapter = new HistoryListViewAdapter(context);
        userListView.setAdapter(historyListViewAdapter);
        userListView.setOnScrollListener(new AbsListView.OnScrollListener() {
            @Override
            public void onScrollStateChanged(AbsListView view, int scrollState) {
            }

            @Override
            public void onScroll(AbsListView view, int firstVisibleItem, int visibleItemCount, int totalItemCount) {
                if (totalItemCount == 0) return;
                int lastVisibleItem = firstVisibleItem + visibleItemCount;
                if (!isLoading && hasNextPage && lastVisibleItem >= totalItemCount - 1) {
                    showFooterLoading();
                    getHistoryList();
                }
            }
        });
        historyListViewAdapter.setFilterListener(isEmpty -> {
            if (!allowFilterCallback) return;
            dataPanel.setVisibility(isEmpty ? View.GONE : View.VISIBLE);
            noDataPanel.setVisibility(isEmpty ? View.VISIBLE : View.GONE);
            loadingPanel.setVisibility(View.GONE);
        });
        historyListViewAdapter.setOnItemClickListener((position, object) -> {
            History history = (History) object;
            Bundle bundle = new Bundle();
            TransactionType transactionType = history.getTransactionType();
            if (transactionType == TransactionType.USER || transactionType == TransactionType.RANDOM_USER) {
//                bundle.putString("data", new Gson().toJson(ActionType.DETAILS_USER_ID));
                bundle.putString("data", history.getMember_id());
                ((TransactionHistoryActivity) context).startAppActivity(new Intent(context, DashboardSearchDetailsActivity.class),
                        bundle, false, false, true);
            } else if (transactionType == TransactionType.PLAYER_USER) {
                bundle.putString("data", new Gson().toJson(ActionType.DETAILS_YXI_ID));
                bundle.putString("id", history.getGamemember_id());
                bundle.putString("yxi_id", history.getProvider_id());
                ((TransactionHistoryActivity) context).startAppActivity(new Intent(context, ActionUserDetailsActivity.class),
                        bundle, false, false, true);
            } else {
                Transaction transaction = new Transaction();
                transaction.setTransactiontype(history.getTransactiontype());
                transaction.setType(history.getType());
                transaction.setAmount(history.getAmountStr());
                transaction.setStatus(history.getStatus());
                transaction.setCreated_on(history.getCreated_on());
                transaction.setProviderId(history.getProvider_id());
                transaction.setTitle(history.getTitle());
                String searchId = history.getMember_id();
                String displayId = FormatUtils.formatMsianPhone(!StringUtil.isNullOrEmpty(history.getPhone()) ? history.getPhone() : history.getMember_name());
                if (history.getTransactiontype().equalsIgnoreCase("player") || history.getTransactiontype().equalsIgnoreCase("game")) {
                    searchId = history.getGamemember_id();
                    displayId = history.getName();
                } else if (history.getTransactiontype().equalsIgnoreCase("shop")) {
                    displayId = history.getManager_name();
                }
                transaction.setDisplayId(displayId);
                transaction.setSearchId(searchId);
                transaction.setPaymentMethod(history.getPayment_name());
                bundle.putString("data", new Gson().toJson(transaction));
                ((TransactionHistoryActivity) context).startAppActivity(new Intent(context, TransactionDetailsActivity.class), bundle, false, false, true);
            }
        });
    }

    private void showFooterLoading() {
        if (userListView.getFooterViewsCount() == 0) {
            userListView.addFooterView(footerLoadingView);
        }
    }

    private void hideFooterLoading() {
        if (userListView.getFooterViewsCount() > 0) {
            userListView.removeFooterView(footerLoadingView);
        }
    }

    public void filter(String keyword) {
        if (historyListViewAdapter != null) {
            historyListViewAdapter.getFilter().filter(keyword);
        }
    }

    private void getHistoryList() {
        if (isLoading) return;
        isLoading = true;
        if (historyListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        String[] types = new String[]{
                "register",
                "deposit",
                "withdraw",
                "clear"
        };
        switch (page) {
            case 0:
                types = new String[]{"register"};
                break;
            case 1:
                types = new String[]{"deposit"};
                break;
            case 2:
                types = new String[]{"withdraw"};
                break;
            case 3:
                if (shop.isReadClear()) {
                    types = new String[]{"clear"};
                } else {
                    types = new String[]{
                            "register",
                            "deposit",
                            "withdraw"
                    };
                }
                break;
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestHistory request = new RequestHistory(shop.getShop_id(), types, apiPage);
        ((TransactionHistoryActivity) context).executeApiCall(context, apiService.getHistoryList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<History>> response) {
                isLoading = false;
                hideFooterLoading();

                List<History> historyList = response.getData();
                Pagination pagination = response.getPagination();
                hasNextPage = pagination != null && pagination.isHasnextpage();
                if (hasNextPage) {
                    apiPage++;
                }

                if (historyList != null && !historyList.isEmpty()) {
                    historyListViewAdapter.setData(historyList);
//                    String keyword = getKeyword();
                    allowFilterCallback = false;
                    historyListViewAdapter.getFilter().filter("");
                    allowFilterCallback = true;

                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                } else if (apiPage == 1) {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                }
                loadingPanel.setVisibility(View.GONE);
            }

            @Override
            public boolean onApiError(int code, String message) {
                isLoading = false;
                hideFooterLoading();
                loadingPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                isLoading = false;
                hideFooterLoading();
                loadingPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }
        }, false);
    }
}
