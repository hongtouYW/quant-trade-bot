package com.dj.manager.activity.point;

import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityPointFilterBinding;
import com.dj.manager.model.ItemChip;
import com.dj.manager.model.request.RequestPointFilter;
import com.dj.manager.model.response.Manager;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.widget.CalendarBottomSheetDialogFragment;
import com.google.android.flexbox.FlexboxLayout;
import com.google.gson.Gson;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;

public class PointFilterActivity extends BaseActivity implements CalendarBottomSheetDialogFragment.DateSelectionListener {
    private ActivityPointFilterBinding binding;
    private Manager manager;
    private RequestPointFilter request;
    private final Map<String, String> selectedFilters = new HashMap<>();
    private LocalDate currentStartDate = null;
    private LocalDate currentEndDate = null;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPointFilterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.point_filter_title), getString(R.string.point_filter_reset), R.color.white_FFFFFF, view -> resetAllFilters());
        setupFilters();
        setupActionButtons();
        applyInitialFilters();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            request = new Gson().fromJson(json, RequestPointFilter.class);
        } else {
            request = new RequestPointFilter(manager != null ? manager.getManager_id() : "");
        }
    }

    private void applyInitialFilters() {
        if (request == null) return;
        // Map list type
        String listType = request.getList();
        if (listType != null) {
            selectChipByType(binding.flexboxLayoutList, listType);
            updateTransactionChips(listType);
            selectedFilters.put("list", listType);
        } else {
            resetFilterSection(binding.flexboxLayoutList);
        }
        // Map transaction type
        String transactionType = request.getTransaction();
        if (transactionType != null) {
            selectChipByType(binding.flexboxLayoutType, transactionType);
            selectedFilters.put("transaction", transactionType);
        } else {
            resetFilterSection(binding.flexboxLayoutType);
        }
        // Map status
        String status = request.getStatus();
        if (status != null) {
            selectChipByType(binding.flexboxLayoutStatus, status);
            selectedFilters.put("status", status);
        } else {
            resetFilterSection(binding.flexboxLayoutStatus);
        }
        // Map date range
        String startDate = request.getStartdate();
        String endDate = request.getEnddate();
        if (startDate != null || endDate != null) {
            handleDateFromRequest(startDate, endDate);
        } else {
            resetFilterSection(binding.flexboxLayoutDate);
            resetCalendarPanel();
        }
    }

    private void selectChipByType(FlexboxLayout flexboxLayout, String type) {
        for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
            View chipView = flexboxLayout.getChildAt(i);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            // Get the chip type from tag or other identifier
            Object tag = chipView.getTag();
            String chipType = (tag != null) ? tag.toString() :
                    chipLabel.getText().toString().toLowerCase();

            if (chipType.equals(type)) {
                updateChipSelection(flexboxLayout, chipView);
                break;
            }
        }
    }

    private void handleDateFromRequest(String startDateStr, String endDateStr) {
        try {
            DateTimeFormatter parser = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
            if (startDateStr != null) {
                currentStartDate = LocalDate.parse(startDateStr, parser);
            }
            if (endDateStr != null) {
                currentEndDate = LocalDate.parse(endDateStr, parser);
            }
            if (currentStartDate != null && currentEndDate != null) {
                DateTimeFormatter displayFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);

                if (currentStartDate.equals(currentEndDate)) {
                    // Single day selection
                    binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
                    binding.textViewDate.setText(currentStartDate.format(displayFormatter));
                    binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
                } else {
                    // Date range selection
                    String displayText = String.format(getString(R.string.template_s_s_dashed),
                            currentStartDate.format(displayFormatter),
                            currentEndDate.format(displayFormatter));
                    binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
                    binding.textViewDate.setText(displayText);
                    binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
                }

                deselectAllChipsInDateSection(binding.flexboxLayoutDate);
                String dateRange = currentStartDate.format(parser) + "|" + currentEndDate.format(parser);
                selectedFilters.put("date", dateRange);

            } else if (currentStartDate != null) {
                // Only start date provided (shouldn't normally happen, but handle it)
                DateTimeFormatter displayFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);
                binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
                binding.textViewDate.setText(currentStartDate.format(displayFormatter));
                binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
                deselectAllChipsInDateSection(binding.flexboxLayoutDate);

                DateTimeFormatter storageFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
                selectedFilters.put("date", currentStartDate.format(storageFormatter));
            }
        } catch (Exception e) {
            Log.e("###", "handleDateFromRequest: ", e);
            resetCalendarPanel();
        }
    }

    private void setupFilters() {
        setupFilterSection(binding.flexboxLayoutList, Arrays.asList(
                new ItemChip(getString(R.string.point_filter_list_all), "all"),
                new ItemChip(getString(R.string.point_filter_list_shop), "shop"),
                new ItemChip(getString(R.string.point_filter_list_game), "game")
        ), "list");

        setupFilterSection(binding.flexboxLayoutType, Arrays.asList(
                new ItemChip(getString(R.string.point_filter_transaction_all), "all"),
                new ItemChip(getString(R.string.point_filter_transaction_bonus), "bonus"),
                new ItemChip(getString(R.string.point_filter_transaction_reward), "reward"),
                new ItemChip(getString(R.string.point_filter_transaction_top_up), "reload"),
                new ItemChip(getString(R.string.point_filter_transaction_withdraw), "withdraw")
        ), "transaction");

        setupFilterSection(binding.flexboxLayoutStatus, Arrays.asList(
                new ItemChip(getString(R.string.point_filter_status_all), "all"),
                new ItemChip(getString(R.string.point_filter_status_success), "1"),
                new ItemChip(getString(R.string.point_filter_status_fail), "0")
        ), "status");
        setupFilterSection(binding.flexboxLayoutDate, Arrays.asList(
                new ItemChip(getString(R.string.point_filter_date_all), "all")
        ), "date");
    }

    private void updateTransactionChips(String listType) {
        List<ItemChip> transactionOptions;
        if (listType.equalsIgnoreCase("shop")) {// Only allow all, reload, withdraw
            transactionOptions = Arrays.asList(
                    new ItemChip(getString(R.string.point_filter_transaction_all), "all"),
                    new ItemChip(getString(R.string.point_filter_transaction_top_up), "reload"),
                    new ItemChip(getString(R.string.point_filter_transaction_withdraw), "withdraw")
            );
        } else {
            transactionOptions = Arrays.asList(
                    new ItemChip(getString(R.string.point_filter_transaction_all), "all"),
                    new ItemChip(getString(R.string.point_filter_transaction_bonus), "bonus"),
                    new ItemChip(getString(R.string.point_filter_transaction_reward), "reward"),
                    new ItemChip(getString(R.string.point_filter_transaction_top_up), "reload"),
                    new ItemChip(getString(R.string.point_filter_transaction_withdraw), "withdraw")
            );
        }
        setupFilterSection(binding.flexboxLayoutType, transactionOptions, "transaction");
    }

    private void setupFilterSection(FlexboxLayout flexboxLayout, List<ItemChip> options, String filterKey) {
        LayoutInflater inflater = LayoutInflater.from(this);
        flexboxLayout.removeAllViews();

        for (ItemChip chip : options) {
            View chipView = inflater.inflate(R.layout.item_chip_filter_point, flexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
            chipLabel.setBackgroundResource(R.drawable.bg_chip);

            chipView.setTag(chip.getType());
            chipView.setOnClickListener(v -> {
                if (filterKey.equalsIgnoreCase("date")) {
                    handleDateChipSelection(flexboxLayout, chipView, chip);
                } else {
                    boolean canProceed = true;
                    if (filterKey.equalsIgnoreCase("list")) {
                        updateTransactionChips(chip.getType());
                    } else if (filterKey.equalsIgnoreCase("transaction")) {
                        String listFilter = selectedFilters.getOrDefault("list", "all");
                        if (listFilter != null) {
                            if (listFilter.equalsIgnoreCase("all") && !chip.getType().equalsIgnoreCase("all")) {
                                canProceed = false;
                                Toast.makeText(this, R.string.point_filter_choose_list_first, Toast.LENGTH_SHORT).show();
                            }
                        }
                    }
                    if (canProceed) {
                        updateChipSelection(flexboxLayout, chipView);
                        selectedFilters.put(filterKey, chip.getType());
                    }
                }
            });

            flexboxLayout.addView(chipView);
        }
        if (flexboxLayout.getChildCount() > 0) {
            flexboxLayout.getChildAt(0).performClick();
        }
    }

    private void handleDateChipSelection(FlexboxLayout flexboxLayout, View selectedChip, ItemChip chip) {
        updateChipSelection(flexboxLayout, selectedChip);
        if (chip.getType().equals("all")) {
            resetCalendarPanel();
        }
    }

    private void updateChipSelection(FlexboxLayout flexboxLayout, View selectedChip) {
        for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
            View child = flexboxLayout.getChildAt(i);
            TextView textView = child.findViewById(R.id.chip_label);
            textView.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
            textView.setBackgroundResource(R.drawable.bg_chip);
        }
        TextView selectedTextView = selectedChip.findViewById(R.id.chip_label);
        selectedTextView.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        selectedTextView.setBackgroundResource(R.drawable.bg_chip_selected);
    }

    private void deselectAllChipsInDateSection(FlexboxLayout flexboxLayout) {
        for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
            View child = flexboxLayout.getChildAt(i);
            TextView textView = child.findViewById(R.id.chip_label);
            textView.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
            textView.setBackgroundResource(R.drawable.bg_chip);
        }
        selectedFilters.remove("date");
    }

    private void resetCalendarPanel() {
        binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip);
        binding.textViewDate.setText(R.string.point_filter_date_choose);
        binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        selectedFilters.put("date", "all");
        currentStartDate = null;
        currentEndDate = null;
    }

    private void setupActionButtons() {
        binding.panelCalendar.setOnClickListener(view -> {
            CalendarBottomSheetDialogFragment calendarSheet = CalendarBottomSheetDialogFragment.newInstance(currentStartDate, currentEndDate);
            calendarSheet.setDateSelectionListener(this);
            calendarSheet.show(getSupportFragmentManager(), "CalendarBottomSheet");
        });
        binding.buttonConfirm.setOnClickListener(view -> applyFilters(true));
    }

    private void resetAllFilters() {
        resetFilterSection(binding.flexboxLayoutList);
        resetFilterSection(binding.flexboxLayoutType);
        resetFilterSection(binding.flexboxLayoutStatus);
        resetFilterSection(binding.flexboxLayoutDate);
        resetCalendarPanel();
        selectedFilters.clear();
        currentStartDate = null;
        currentEndDate = null;
        if (manager != null) {
            request = new RequestPointFilter(manager.getManager_id());
        }
        applyFilters(false);
    }

    private void resetFilterSection(FlexboxLayout flexboxLayout) {
        if (flexboxLayout.getChildCount() > 0) {
            for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                View chipView = flexboxLayout.getChildAt(i);
                Object tag = chipView.getTag();
                if (tag != null && tag.equals("all")) {
                    chipView.performClick();
                    break;
                }
            }
            if (!selectedFilters.containsValue("all")) {
                flexboxLayout.getChildAt(0).performClick();
            }
        }
    }

    private void applyFilters(boolean dismiss) {
        String listFilter = selectedFilters.getOrDefault("list", "all");
        String transactionFilter = selectedFilters.getOrDefault("transaction", "all");
        String statusFilter = selectedFilters.getOrDefault("status", "all");
        String dateFilter = selectedFilters.getOrDefault("date", "all");

        request = new RequestPointFilter(manager.getManager_id());
        request.setList("all".equals(listFilter) ? null : listFilter);
        request.setTransaction("all".equals(transactionFilter) ? null : transactionFilter);
        request.setStatus("all".equals(statusFilter) ? null : statusFilter);

        boolean hasFilter = !"all".equals(listFilter) ||
                !"all".equals(transactionFilter) ||
                !"all".equals(statusFilter) ||
                (!"all".equals(dateFilter) && currentStartDate != null);
        if (!"all".equals(dateFilter) && currentStartDate != null) {
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
            request.setStartdate(currentStartDate.format(formatter));
            if (currentEndDate != null) {
                request.setEnddate(currentEndDate.format(formatter));
            } else {
                request.setEnddate(currentStartDate.format(formatter));
            }
        }

        Intent resultIntent = new Intent();
        String filterJson = new Gson().toJson(request);
        resultIntent.putExtra("data", filterJson);
        resultIntent.putExtra("hasFilter", hasFilter);
        setResult(RESULT_OK, resultIntent);
        if (dismiss) {
            onBaseBackPressed();
        }
    }

    @Override
    public void onDateSelected(LocalDate startDate, LocalDate endDate) {
        currentStartDate = startDate;
        currentEndDate = endDate;

        if (startDate != null && endDate != null) {
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);

            if (startDate.equals(endDate)) {
                // Single day selection
                binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
                binding.textViewDate.setText(startDate.format(formatter));
                binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
            } else {
                // Date range selection
                String text = String.format(getString(R.string.template_s_s_dashed), startDate.format(formatter), endDate.format(formatter));
                binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
                binding.textViewDate.setText(text);
                binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
            }
            deselectAllChipsInDateSection(binding.flexboxLayoutDate);

            DateTimeFormatter storageFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
            String dateRange = startDate.format(storageFormatter) + "|" + endDate.format(storageFormatter);
            selectedFilters.put("date", dateRange);

        } else if (startDate != null) {
            // Single date selection - treat as start date only
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);
            binding.panelCalendar.setBackgroundResource(R.drawable.bg_chip_selected);
            binding.textViewDate.setText(startDate.format(formatter));
            binding.textViewDate.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
            deselectAllChipsInDateSection(binding.flexboxLayoutDate);

            DateTimeFormatter storageFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
            selectedFilters.put("date", startDate.format(storageFormatter));

        } else {
            resetCalendarPanel();
            resetFilterSection(binding.flexboxLayoutDate);
        }
    }

    @Override
    public void onSelectionCancelled() {

    }
}