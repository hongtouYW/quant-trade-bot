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
import com.dj.shop.activity.transaction.TransactionDetailsActivity;
import com.dj.shop.activity.transaction.TransactionHistoryActivity;
import com.dj.shop.adapter.TransactionListViewAdapter;
import com.dj.shop.databinding.FragmentTransactionBinding;
import com.dj.shop.model.request.RequestTransaction;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Pagination;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class TransactionFragment extends Fragment {
    private FragmentTransactionBinding binding;
    private Context context;
    private int page = 0;
    private Shop shop;
    private View footerLoadingView;
    private ListView transactionListView;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private TransactionListViewAdapter transactionListViewAdapter;
    private boolean allowFilterCallback = false;
    private boolean hasLoadedOnce = false;
    private int apiPage = 1;
    private boolean isLoading = false;
    private boolean hasNextPage = true;

    public TransactionFragment newInstance(Context context, int page) {
        TransactionFragment fragment = new TransactionFragment();
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
        binding = FragmentTransactionBinding.inflate(inflater, container, false);
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
        transactionListViewAdapter.clear();
        getTransactionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        transactionListView = binding.listViewTransaction;
        footerLoadingView = LayoutInflater.from(context).inflate(R.layout.footer_loading, transactionListView, false);
        transactionListViewAdapter = new TransactionListViewAdapter(context);
        transactionListView.setAdapter(transactionListViewAdapter);
        transactionListView.setOnScrollListener(new AbsListView.OnScrollListener() {
            @Override
            public void onScrollStateChanged(AbsListView view, int scrollState) {
            }

            @Override
            public void onScroll(AbsListView view, int firstVisibleItem, int visibleItemCount, int totalItemCount) {
                if (totalItemCount == 0) return;
                int lastVisibleItem = firstVisibleItem + visibleItemCount;
                if (!isLoading && hasNextPage && lastVisibleItem >= totalItemCount - 1) {
                    showFooterLoading();
                    getTransactionList();
                }
            }
        });
        transactionListViewAdapter.setFilterListener(isEmpty -> {
            if (!allowFilterCallback) return;
            dataPanel.setVisibility(isEmpty ? View.GONE : View.VISIBLE);
            noDataPanel.setVisibility(isEmpty ? View.VISIBLE : View.GONE);
            loadingPanel.setVisibility(View.GONE);
        });
        transactionListViewAdapter.setOnItemClickListener((position, object) -> {
            Transaction transaction = (Transaction) object;
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(transaction));
            ((TransactionHistoryActivity) context).startAppActivity(new Intent(context, TransactionDetailsActivity.class), bundle, false, false, true);
        });
    }

    private void showFooterLoading() {
        if (transactionListView.getFooterViewsCount() == 0) {
            transactionListView.addFooterView(footerLoadingView);
        }
    }

    private void hideFooterLoading() {
        if (transactionListView.getFooterViewsCount() > 0) {
            transactionListView.removeFooterView(footerLoadingView);
        }
    }

    public void filter(String keyword) {
        if (transactionListViewAdapter != null) {
            transactionListViewAdapter.getFilter().filter(keyword);
        }
    }

    private void getTransactionList() {
        if (isLoading) return;
        isLoading = true;
        if (transactionListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        String type = "all";
        switch (page) {
            case 1:
                type = "deposit";
                break;
            case 2:
                type = "withdraw";
                break;
            case 3:
                type = "clear";
                break;
        }
        RequestTransaction request = new RequestTransaction(shop.getShop_id(), type, apiPage);
        ((TransactionHistoryActivity) context).executeApiCall(context, apiService.getTransactionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Transaction>> response) {
                isLoading = false;
                hideFooterLoading();

                List<Transaction> transactionList = response.getData();
                Pagination pagination = response.getPagination();
                hasNextPage = pagination != null && pagination.isHasnextpage();
                if (hasNextPage) {
                    apiPage++;
                }

                if (transactionList != null && !transactionList.isEmpty()) {
                    transactionListViewAdapter.setData(transactionList);
//                    String keyword = getKeyword();
                    allowFilterCallback = false;
                    transactionListViewAdapter.getFilter().filter("");
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
