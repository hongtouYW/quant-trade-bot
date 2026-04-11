package com.dj.user.fragment.transaction;

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

import com.dj.user.R;
import com.dj.user.activity.mine.transaction.TransactionActivity;
import com.dj.user.activity.mine.transaction.TransactionDetailsActivity;
import com.dj.user.activity.mine.transaction.TransactionFilterActivity;
import com.dj.user.adapter.CreditListViewAdapter;
import com.dj.user.databinding.FragmentCreditPointsBinding;
import com.dj.user.enums.TransactionType;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestTransaction;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Pagination;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class CreditFragment extends BaseFragment {

    private FragmentCreditPointsBinding binding;
    private Context context;
    private Member member;
    private View footerLoadingView;
    private ListView transactionListView;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private CreditListViewAdapter creditListViewAdapter;
    private boolean hasLoadedOnce = false;
    private int apiPage = 1;
    private boolean isLoading = false;
    private boolean hasNextPage = true;

    public CreditFragment newInstance(Context context) {
        CreditFragment fragment = new CreditFragment();
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
        binding = FragmentCreditPointsBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupTransactionList();
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
        creditListViewAdapter.removeList();
        getTransactionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        binding.imageViewFilter.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(TransactionType.CREDIT));
            ((TransactionActivity) context).startAppActivity(new Intent(context, TransactionFilterActivity.class),
                    bundle, false, false, true);
        });
    }

    private void setupTransactionList() {
        transactionListView = binding.listViewTransaction;
        footerLoadingView = LayoutInflater.from(context).inflate(R.layout.footer_loading, transactionListView, false);

        creditListViewAdapter = new CreditListViewAdapter(context);
        transactionListView.setAdapter(creditListViewAdapter);
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
        creditListViewAdapter.setOnItemClickListener((position, object) -> {
            Transaction transaction = (Transaction) object;
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(transaction));
            bundle.putString("type", new Gson().toJson(TransactionType.CREDIT));
            ((TransactionActivity) context).startAppActivity(new Intent(context, TransactionDetailsActivity.class),
                    bundle, false, false, true);
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

    private void getTransactionList() {
        if (isLoading) return;
        isLoading = true;
        if (creditListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestTransaction request = new RequestTransaction(member.getMember_id(), "credit", "", "", apiPage);
        ((TransactionActivity) context).executeApiCall(context, apiService.getTransactionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                isLoading = false;
                hideFooterLoading();

                List<Transaction> transactionList = response.getCredit();
                Pagination pagination = response.getCreditpagination();
                hasNextPage = pagination != null && pagination.isHasnextpage();
                if (hasNextPage) {
                    apiPage++;
                }
                if (transactionList != null && !transactionList.isEmpty()) {
                    creditListViewAdapter.addList(transactionList);
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

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}