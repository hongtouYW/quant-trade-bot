package com.dj.user.activity.mine.transaction;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.AbsListView;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.BetListViewAdapter;
import com.dj.user.adapter.CreditListViewAdapter;
import com.dj.user.adapter.PointListViewAdapter;
import com.dj.user.databinding.ActivityTransactionFilterBinding;
import com.dj.user.enums.TransactionType;
import com.dj.user.model.ItemChip;
import com.dj.user.model.request.RequestTransaction;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Pagination;
import com.dj.user.model.response.Transaction;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CalendarBottomSheetDialogFragment;
import com.dj.user.widget.ExpandableHeightListView;
import com.google.android.flexbox.FlexboxLayout;
import com.google.gson.Gson;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.Locale;

public class TransactionFilterActivity extends BaseActivity implements CalendarBottomSheetDialogFragment.DateSelectionListener {

    private ActivityTransactionFilterBinding binding;
    private Member member;
    private CreditListViewAdapter creditListViewAdapter;
    private PointListViewAdapter pointListViewAdapter;
    private BetListViewAdapter betListViewAdapter;
    private TransactionType transactionType;
    private ItemChip selectedDate, customDate;
    private LocalDate currentStartDate = null;
    private LocalDate currentEndDate = null;
    private FlexboxLayout flexboxLayout;
    private View footerLoadingView;
    private ListView transactionListView;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private static final DateTimeFormatter FORMATTER = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED);

    private boolean hasLoadedOnce = false;
    private int apiPage = 1;
    private boolean isLoading = false;
    private boolean hasNextPage = true;
    private int requestVersion = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTransactionFilterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.transaction_filter_title), R.drawable.ic_delete_orange, view -> {
            resetDateSelection();
            if (flexboxLayout.getChildCount() > 0) {
                flexboxLayout.getChildAt(0).performClick();
            }
        });
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (!StringUtil.isNullOrEmpty(json)) {
            transactionType = new Gson().fromJson(json, TransactionType.class);
        }
    }

    private void resetAndLoad() {
        requestVersion++;
        apiPage = 1;
        hasNextPage = true;
        hasLoadedOnce = false;
        isLoading = false;
        if (transactionType == TransactionType.CREDIT) {
            if (creditListViewAdapter != null) {
                creditListViewAdapter.removeList();
            }
        } else if (transactionType == TransactionType.POINT) {
            if (pointListViewAdapter != null) {
                pointListViewAdapter.removeList();
            }
        } else if (transactionType == TransactionType.HISTORY) {
            if (betListViewAdapter != null) {
                betListViewAdapter.removeList();
            }
        }
        getTransactionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        transactionListView = binding.listViewTransaction;
//        transactionListView.setExpanded(true);
        footerLoadingView = LayoutInflater.from(this).inflate(R.layout.footer_loading, transactionListView, false);
        if (transactionType == TransactionType.CREDIT) {
            creditListViewAdapter = new CreditListViewAdapter(this);
            transactionListView.setAdapter(creditListViewAdapter);
            creditListViewAdapter.setOnItemClickListener((position, object) -> {
                Transaction transaction = (Transaction) object;
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(transaction));
                bundle.putString("type", new Gson().toJson(TransactionType.CREDIT));
                startAppActivity(new Intent(TransactionFilterActivity.this, TransactionDetailsActivity.class),
                        bundle, false, false, true);
            });
        } else if (transactionType == TransactionType.POINT) {
            pointListViewAdapter = new PointListViewAdapter(this);
            transactionListView.setAdapter(pointListViewAdapter);
            pointListViewAdapter.setOnItemClickListener((position, object) -> {
                Transaction transaction = (Transaction) object;
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(transaction));
                bundle.putString("type", new Gson().toJson(TransactionType.POINT));
                startAppActivity(new Intent(TransactionFilterActivity.this, TransactionDetailsActivity.class),
                        bundle, false, false, true);
            });
        } else if (transactionType == TransactionType.HISTORY) {
            betListViewAdapter = new BetListViewAdapter(this);
            transactionListView.setAdapter(betListViewAdapter);
            betListViewAdapter.setOnItemClickListener((position, object) -> {
                Transaction transaction = (Transaction) object;
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(transaction));
                bundle.putString("type", new Gson().toJson(TransactionType.HISTORY));
                startAppActivity(new Intent(TransactionFilterActivity.this, TransactionDetailsActivity.class),
                        bundle, false, false, true);
            });
        }
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

        flexboxLayout = binding.flexboxLayout;
        LayoutInflater inflater = LayoutInflater.from(this);
        LinearLayout dateSelectionPanel = binding.panelDateSelection;
        dateSelectionPanel.setOnClickListener(view -> {
            CalendarBottomSheetDialogFragment calendarSheet = CalendarBottomSheetDialogFragment.newInstance(CalendarBottomSheetDialogFragment.MODE_MIX, currentStartDate, currentEndDate, false);
            calendarSheet.setDateSelectionListener(this);
            calendarSheet.show(getSupportFragmentManager(), "CalendarBottomSheet");
        });
        binding.textViewReset.setOnClickListener(view -> resetDateSelection());

        LocalDate today = LocalDate.now();
        LocalDate yesterday = today.minusDays(1);
        LocalDate startOfWeek = today.with(java.time.DayOfWeek.MONDAY);
        LocalDate endOfWeek = today.with(java.time.DayOfWeek.SUNDAY);
        LocalDate startOfLastWeek = startOfWeek.minusWeeks(1);
        LocalDate endOfLastWeek = endOfWeek.minusWeeks(1);
        LocalDate startOfMonth = today.withDayOfMonth(1);
        LocalDate endOfMonth = today.withDayOfMonth(today.lengthOfMonth());
        LocalDate startOfLastMonth = startOfMonth.minusMonths(1);
        LocalDate endOfLastMonth = startOfMonth.minusDays(1);

        currentStartDate = today;
        currentEndDate = today;
        customDate = new ItemChip(getString(R.string.transaction_filter_date_search), true, FORMATTER.format(currentStartDate), FORMATTER.format(currentEndDate));
        updateDateSelectionTextView();

        // no restriction
        List<ItemChip> options = Arrays.asList(
                new ItemChip(getString(R.string.transaction_filter_all), false, null, null), // no restriction
                new ItemChip(getString(R.string.transaction_filter_today), false, FORMATTER.format(today), FORMATTER.format(today)),
                new ItemChip(getString(R.string.transaction_filter_yesterday), false, FORMATTER.format(yesterday), FORMATTER.format(yesterday)),
                new ItemChip(getString(R.string.transaction_filter_this_week), false, FORMATTER.format(startOfWeek), FORMATTER.format(endOfWeek)),
                new ItemChip(getString(R.string.transaction_filter_last_week), false, FORMATTER.format(startOfLastWeek), FORMATTER.format(endOfLastWeek)),
                new ItemChip(getString(R.string.transaction_filter_this_month), false, FORMATTER.format(startOfMonth), FORMATTER.format(endOfMonth)),
                new ItemChip(getString(R.string.transaction_filter_last_month), false, FORMATTER.format(startOfLastMonth), FORMATTER.format(endOfLastMonth)),
                new ItemChip(getString(R.string.transaction_filter_previous_day), false, FORMATTER.format(today.minusDays(2)), FORMATTER.format(today.minusDays(2))),
                new ItemChip(getString(R.string.transaction_filter_next_day), false, FORMATTER.format(today.plusDays(1)), FORMATTER.format(today.plusDays(1))),
                customDate
        );

        for (ItemChip chip : options) {
            View chipView = inflater.inflate(R.layout.item_chip_filter, flexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
            chipView.setOnClickListener(v -> {
                for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                    View child = flexboxLayout.getChildAt(i);
                    TextView textView = child.findViewById(R.id.chip_label);

                    textView.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
                    child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                }
                chipLabel.setTextColor(ContextCompat.getColor(this, R.color.black_000000));
                chipView.setBackgroundResource(R.drawable.bg_button_orange);

                if (chip.isShowExtraView()) {
                    dateSelectionPanel.setVisibility(View.VISIBLE);
                } else {
                    dateSelectionPanel.setVisibility(View.GONE);
                }

                selectedDate = chip;
                resetAndLoad();
            });
            flexboxLayout.addView(chipView);
        }
        if (flexboxLayout.getChildCount() > 0) {
            flexboxLayout.getChildAt(0).performClick();
        }
    }

    private void updateDateSelectionTextView() {
        DateTimeFormatter formatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);
        String text = String.format(getString(R.string.template_s_dash_space_s), currentStartDate.format(formatter), currentEndDate.format(formatter));
        binding.textViewDate.setText(text);

        DateTimeFormatter submissionFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
        customDate.setStartDate(currentStartDate.format(submissionFormatter));
        customDate.setEndDate(currentEndDate.format(submissionFormatter));
    }

    private void resetDateSelection() {
        currentStartDate = LocalDate.now();
        currentEndDate = LocalDate.now();
        updateDateSelectionTextView();
        resetAndLoad();
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
        final int currentRequestVersion = requestVersion;
        if (!hasLoadedOnce) {
            hasLoadedOnce = true;
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestTransaction request = new RequestTransaction(member.getMember_id(), transactionType.name().toLowerCase(), selectedDate.getStartDate(), selectedDate.getEndDate(), apiPage);
        executeApiCall(this, apiService.getTransactionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                if (currentRequestVersion != requestVersion) {
                    return;
                }
                isLoading = false;
                hideFooterLoading();
                Pagination pagination = null;
                List<Transaction> transactionList = new ArrayList<>();
                if (transactionType == TransactionType.CREDIT) {
                    transactionList = response.getCredit();
                    pagination = response.getCreditpagination();
                    creditListViewAdapter.addList(transactionList);

                } else if (transactionType == TransactionType.POINT) {
                    transactionList = response.getPoint();
                    pagination = response.getPointpagination();
                    pointListViewAdapter.addList(transactionList);

                } else if (transactionType == TransactionType.HISTORY) {
                    transactionList = response.getHistory();
                    pagination = response.getHistorypagination();
                    betListViewAdapter.addList(transactionList);
                }
                hasNextPage = pagination != null && pagination.isHasnextpage();
                if (hasNextPage) {
                    apiPage++;
                }
                if (transactionList != null && !transactionList.isEmpty()) {
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
    public void onDateSelected(LocalDate startDate, LocalDate endDate) {
        currentStartDate = startDate != null ? startDate : LocalDate.now();
        currentEndDate = endDate != null ? endDate : currentStartDate;

        updateDateSelectionTextView();
        getTransactionList();
    }

    @Override
    public void onSelectionCancelled() {

    }
}