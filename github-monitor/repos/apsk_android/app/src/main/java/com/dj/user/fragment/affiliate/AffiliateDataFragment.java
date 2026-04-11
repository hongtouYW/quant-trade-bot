package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.os.Bundle;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.user.R;
import com.dj.user.activity.mine.affiliate.AffiliateActivity;
import com.dj.user.adapter.AffiliateDataGridItemAdapter;
import com.dj.user.databinding.FragmentAffiliateDataBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemChip;
import com.dj.user.model.ItemGrid;
import com.dj.user.model.request.RequestSummaryData;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.SummaryData;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class AffiliateDataFragment extends BaseFragment {

    private FragmentAffiliateDataBinding binding;
    private Context context;
    private Member member;
    private SummaryData summaryData;
    private List<ItemChip> options = new ArrayList<>();
    private List<TextView> buttonList = new ArrayList<>();
    private int selectedIndex = 0;
    private ItemChip selectedDate;
    private static final DateTimeFormatter FORMATTER = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED);

    public AffiliateDataFragment newInstance(Context context) {
        AffiliateDataFragment fragment = new AffiliateDataFragment();
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
        binding = FragmentAffiliateDataBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupFilterButtons();
        setupDownlineGrid();
        setupTeamGrid();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getSummaryData();
    }

    private void setupFilterButtons() {
        LinearLayout buttonContainer = binding.panelFilter;
        buttonList.clear();
        buttonContainer.removeAllViews();

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
        options = Arrays.asList(
                new ItemChip(getString(R.string.affiliate_data_tab_today), false, FORMATTER.format(today), FORMATTER.format(today)),
                new ItemChip(getString(R.string.affiliate_data_tab_ytd), false, FORMATTER.format(yesterday), FORMATTER.format(yesterday)),
                new ItemChip(getString(R.string.affiliate_data_tab_this_week), false, FORMATTER.format(startOfWeek), FORMATTER.format(endOfWeek)),
                new ItemChip(getString(R.string.affiliate_data_tab_last_week), false, FORMATTER.format(startOfLastWeek), FORMATTER.format(endOfLastWeek)),
                new ItemChip(getString(R.string.affiliate_data_tab_this_month), false, FORMATTER.format(startOfMonth), FORMATTER.format(endOfMonth)),
                new ItemChip(getString(R.string.affiliate_data_tab_last_month), false, FORMATTER.format(startOfLastMonth), FORMATTER.format(endOfLastMonth))
        );
        selectedDate = options.get(0);

        for (int i = 0; i < options.size(); i++) {
            ItemChip option = options.get(i);
            TextView textView = new TextView(context);
            textView.setText(option.getLabel());
            textView.setGravity(Gravity.CENTER);
            textView.setTypeface(ResourcesCompat.getFont(context, R.font.pingfang_sc_regular));
            textView.setTextSize(TypedValue.COMPLEX_UNIT_SP, 12);
            textView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            textView.setPaddingRelative(FormatUtils.dpToPx(context, 13), 0, FormatUtils.dpToPx(context, 13), 0);

            LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.MATCH_PARENT
            );
            params.setMargins(0, 0, FormatUtils.dpToPx(context, 8), 0);
            textView.setLayoutParams(params);
            textView.setBackgroundResource(R.drawable.bg_filter);

            int index = i;
            textView.setOnClickListener(v -> {
                selectedIndex = index;
                selectedDate = option;
                updateFilterStyles();
                updateSectionTitles();
                getSummaryData();
            });
            buttonList.add(textView);
            buttonContainer.addView(textView);
        }
        selectedIndex = 0;
        updateFilterStyles();
        updateSectionTitles();
    }

    private void updateSectionTitles() {
        String date = options.get(selectedIndex).getLabel();
        binding.textViewDownline.setText(String.format(context.getString(R.string.affiliate_data_downline_template), date));
//        binding.textViewTeam.setText(String.format(context.getString(R.string.affiliate_data_downline_template), date));
    }

    private void updateFilterStyles() {
        for (int i = 0; i < buttonList.size(); i++) {
            TextView textView = buttonList.get(i);
            if (i == selectedIndex) {
                textView.setBackgroundResource(R.drawable.bg_filter_selected);
                textView.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
            } else {
                textView.setBackgroundResource(R.drawable.bg_filter);
                textView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            }
        }
    }

    private void setupDownlineGrid() {
        List<ItemGrid> actionItems = List.of(
                new ItemGrid(context.getString(R.string.affiliate_data_new_downline), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_first_top_up_amount), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_first_top_up_head_count), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_top_up_amount), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_top_up_count), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_withdraw_amount), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_withdraw_count), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_bet_amount), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_redeem_reward), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_downline_win_loss), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_downline_kpi), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_total_commision), context.getString(R.string.placeholder_amount), true)
        );
        if (summaryData != null) {
            actionItems = List.of(
                    new ItemGrid(context.getString(R.string.affiliate_data_new_downline), String.valueOf(summaryData.getNewDirect())),
                    new ItemGrid(context.getString(R.string.affiliate_data_first_top_up_amount), FormatUtils.formatAmount(summaryData.getFirstBonusAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_first_top_up_head_count), String.valueOf(summaryData.getFirstBonusCount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_top_up_amount), FormatUtils.formatAmount(summaryData.getDepositAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_top_up_count), String.valueOf(summaryData.getDepositCount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_withdraw_amount), FormatUtils.formatAmount(summaryData.getWithdrawAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_withdraw_count), String.valueOf(summaryData.getWithdrawCount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_bet_amount), FormatUtils.formatAmount(summaryData.getTotalBetAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_redeem_reward), FormatUtils.formatAmount(summaryData.getRewardAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_downline_win_loss), FormatUtils.formatAmount(summaryData.getWinLossAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_downline_kpi), FormatUtils.formatAmount(summaryData.getSalesAmount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_total_commision), FormatUtils.formatAmount(summaryData.getCommissionAmount()), true)
            );
        }
        AffiliateDataGridItemAdapter downlineDataGridItemAdapter = new AffiliateDataGridItemAdapter(context, actionItems);
        binding.gridViewDownline.setAdapter(downlineDataGridItemAdapter);
        binding.gridViewDownline.setExpanded(true);
        binding.gridViewDownline.setOnItemClickListener((parent, view, position, id) -> {
        });
    }

    private void setupTeamGrid() {
        List<ItemGrid> actionItems1 = List.of(
                new ItemGrid(context.getString(R.string.affiliate_data_team_head_count), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_downline_head_count), context.getString(R.string.placeholder_count)),
//                new ItemGrid(context.getString(R.string.affiliate_data_other_head_count), context.getString(R.string.placeholder_count)),
                new ItemGrid(context.getString(R.string.affiliate_data_total_kpi), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_downline_kpi), context.getString(R.string.placeholder_amount)),
//                new ItemGrid(context.getString(R.string.affiliate_data_other_kpi), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_total_commision), context.getString(R.string.placeholder_amount), true),
                new ItemGrid(context.getString(R.string.affiliate_data_downline_commision), context.getString(R.string.placeholder_amount), true),
//                new ItemGrid(context.getString(R.string.affiliate_data_other_commision), context.getString(R.string.placeholder_amount), true),
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_commision), context.getString(R.string.placeholder_amount), true),
                new ItemGrid(context.getString(R.string.affiliate_data_redeemed), context.getString(R.string.placeholder_amount), true)
//                new ItemGrid(context.getString(R.string.affiliate_data_to_redeem), context.getString(R.string.placeholder_amount), true),
        );
        List<ItemGrid> actionItems2 = List.of(
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_top_up), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_withdraw), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_redeem), context.getString(R.string.placeholder_amount))
        );
        List<ItemGrid> actionItems3 = List.of(
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_bet), context.getString(R.string.placeholder_amount)),
                new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_win_loss), context.getString(R.string.placeholder_amount))
        );
        if (summaryData != null) {
            actionItems1 = List.of(
                    new ItemGrid(context.getString(R.string.affiliate_data_team_head_count), String.valueOf(summaryData.getTeamCount())),
                    new ItemGrid(context.getString(R.string.affiliate_data_downline_head_count), String.valueOf(summaryData.getDownlineCount())),
//                        new ItemGrid(context.getString(R.string.affiliate_data_other_head_count), context.getString(R.string.placeholder_count)),
                    new ItemGrid(context.getString(R.string.affiliate_data_total_kpi), FormatUtils.formatAmount(summaryData.getTeamSales())),
                    new ItemGrid(context.getString(R.string.affiliate_data_downline_kpi), FormatUtils.formatAmount(summaryData.getDownlineSales())),
//                        new ItemGrid(context.getString(R.string.affiliate_data_other_kpi), context.getString(R.string.placeholder_amount)),
                    new ItemGrid(context.getString(R.string.affiliate_data_total_commision), FormatUtils.formatAmount(summaryData.getTeamCommission()), true),
                    new ItemGrid(context.getString(R.string.affiliate_data_downline_commision), FormatUtils.formatAmount(summaryData.getDownlineCommission()), true),
//                        new ItemGrid(context.getString(R.string.affiliate_data_other_commision), context.getString(R.string.placeholder_amount), true),
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_commision), FormatUtils.formatAmount(summaryData.getAccumulatedCommission()), true),
                    new ItemGrid(context.getString(R.string.affiliate_data_redeemed), FormatUtils.formatAmount(summaryData.getTeamRedeem()), true)
//                        new ItemGrid(context.getString(R.string.affiliate_data_to_redeem), context.getString(R.string.placeholder_amount), true),
            );
            actionItems2 = List.of(
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_top_up), FormatUtils.formatAmount(summaryData.getAccumulatedTopUp())),
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_withdraw), FormatUtils.formatAmount(summaryData.getAccumulatedWithdraw())),
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_redeem), FormatUtils.formatAmount(summaryData.getAccumulatedRedeem()))
            );
            actionItems3 = List.of(
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_bet), FormatUtils.formatAmount(summaryData.getAccumulatedBet())),
                    new ItemGrid(context.getString(R.string.affiliate_data_accumulated_downline_win_loss), FormatUtils.formatAmount(summaryData.getAccumulatedWinLoss()))
            );
        }
        AffiliateDataGridItemAdapter team1DataGridItemAdapter = new AffiliateDataGridItemAdapter(context, actionItems1);
        binding.gridViewTeam1.setAdapter(team1DataGridItemAdapter);
        binding.gridViewTeam1.setExpanded(true);
        binding.gridViewTeam1.setOnItemClickListener((parent, view, position, id) -> {
        });
        AffiliateDataGridItemAdapter team2DataGridItemAdapter = new AffiliateDataGridItemAdapter(context, actionItems2);
        binding.gridViewTeam2.setAdapter(team2DataGridItemAdapter);
        binding.gridViewTeam2.setExpanded(true);
        binding.gridViewTeam2.setOnItemClickListener((parent, view, position, id) -> {
        });
        AffiliateDataGridItemAdapter team3DataGridItemAdapter = new AffiliateDataGridItemAdapter(context, actionItems3);
        binding.gridViewTeam3.setAdapter(team3DataGridItemAdapter);
        binding.gridViewTeam3.setExpanded(true);
        binding.gridViewTeam3.setOnItemClickListener((parent, view, position, id) -> {
        });
    }

    private void getSummaryData() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestSummaryData request = new RequestSummaryData(member.getMember_id(), selectedDate.getStartDate(), selectedDate.getEndDate());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getSummaryData(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<SummaryData> response) {
                summaryData = response.getData();
                setupDownlineGrid();
                setupTeamGrid();
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

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}