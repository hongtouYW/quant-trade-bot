package com.dj.manager.widget;

import android.app.Dialog;
import android.graphics.Typeface;
import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.GridView;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.manager.R;
import com.dj.manager.adapter.MonthGridItemAdapter;
import com.dj.manager.model.ItemMonth;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.kizitonwose.calendar.core.CalendarDay;
import com.kizitonwose.calendar.core.DayPosition;
import com.kizitonwose.calendar.view.CalendarView;
import com.kizitonwose.calendar.view.MonthDayBinder;
import com.kizitonwose.calendar.view.ViewContainer;

import java.time.DayOfWeek;
import java.time.LocalDate;
import java.time.YearMonth;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class CalendarBottomSheetDialogFragment extends BottomSheetDialogFragment {
    private static final String ARG_START_DATE = "start_date";
    private static final String ARG_END_DATE = "end_date";

    private LocalDate initialStartDate = null;
    private LocalDate initialEndDate = null;

    public interface DateSelectionListener {
        void onDateSelected(LocalDate startDate, LocalDate endDate);

        void onSelectionCancelled();
    }

    private DateSelectionListener listener;

    public void setDateSelectionListener(DateSelectionListener listener) {
        this.listener = listener;
    }

    private LinearLayout datesPanel, monthPanel, monthYearPanel;
    private CalendarView calendarView;
    private ImageView prevMonthImageView, nextMonthImageView, prevYearImageView, nextYearImageView;
    private TextView monthYearTextView, yearTextView, cancelTextView, resetTextView, confirmTextView;
    private LinearLayout weekDaysContainer;
    private GridView monthGridView;

    private YearMonth selectingMonth, currentMonth, startMonth, endMonth;
    private LocalDate startDate = null;
    private LocalDate endDate = null;
    private DayOfWeek firstDayOfWeek;
    private boolean isMonthSelection = false;

    public static CalendarBottomSheetDialogFragment newInstance(LocalDate startDate, LocalDate endDate) {
        CalendarBottomSheetDialogFragment fragment = new CalendarBottomSheetDialogFragment();
        Bundle args = new Bundle();
        if (startDate != null) {
            args.putString(ARG_START_DATE, startDate.toString());
        }
        if (endDate != null) {
            args.putString(ARG_END_DATE, endDate.toString());
        }
        fragment.setArguments(args);
        return fragment;
    }

    @Override
    public void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (getArguments() != null) {
            String startDateStr = getArguments().getString(ARG_START_DATE);
            String endDateStr = getArguments().getString(ARG_END_DATE);
            if (startDateStr != null) {
                initialStartDate = LocalDate.parse(startDateStr);
            }
            if (endDateStr != null) {
                initialEndDate = LocalDate.parse(endDateStr);
            }
        }
    }

    @NonNull
    @Override
    public Dialog onCreateDialog(Bundle savedInstanceState) {
        return new BottomSheetDialog(requireContext(), getTheme());
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.dialog_bottom_sheet_calendar, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);
        initViews(view);
        setupWeekDays();
        setupCalendar();
        setupListeners();
        if (initialStartDate != null) {
            startDate = initialStartDate;
        }
        if (initialEndDate != null) {
            endDate = initialEndDate;
        }
        if (startDate != null) {
//            currentMonth = YearMonth.from(startDate);
            calendarView.scrollToMonth(YearMonth.from(startDate));
            updateMonthYearText();
        }
        calendarView.notifyCalendarChanged();
    }

    private void initViews(View view) {
        datesPanel = view.findViewById(R.id.panel_dates);
        monthYearPanel = view.findViewById(R.id.panel_month_year);
        monthYearPanel.setOnClickListener(v -> setupMonthView());
        monthYearTextView = view.findViewById(R.id.textView_month_year);
        prevMonthImageView = view.findViewById(R.id.imageView_prev_month);
        nextMonthImageView = view.findViewById(R.id.imageView_next_month);
        calendarView = view.findViewById(R.id.calendar_view);
        cancelTextView = view.findViewById(R.id.textView_cancel);
        resetTextView = view.findViewById(R.id.textView_reset);
        confirmTextView = view.findViewById(R.id.textView_confirm);
        weekDaysContainer = view.findViewById(R.id.weekDaysContainer);

        monthPanel = view.findViewById(R.id.panel_month);
        yearTextView = view.findViewById(R.id.textView_year);
        prevYearImageView = view.findViewById(R.id.imageView_prev_year);
        nextYearImageView = view.findViewById(R.id.imageView_next_year);
        monthGridView = view.findViewById(R.id.gridView_month);

        currentMonth = YearMonth.now();
        startMonth = currentMonth.minusMonths(12);
        endMonth = currentMonth;
        firstDayOfWeek = getFirstDayOfWeek();
    }

    private DayOfWeek getFirstDayOfWeek() {
        java.util.Calendar calendar = java.util.Calendar.getInstance(Locale.ENGLISH);
        int firstDayOfWeek = calendar.getFirstDayOfWeek();
        switch (firstDayOfWeek) {
            case java.util.Calendar.MONDAY:
                return DayOfWeek.MONDAY;
            case java.util.Calendar.TUESDAY:
                return DayOfWeek.TUESDAY;
            case java.util.Calendar.WEDNESDAY:
                return DayOfWeek.WEDNESDAY;
            case java.util.Calendar.THURSDAY:
                return DayOfWeek.THURSDAY;
            case java.util.Calendar.FRIDAY:
                return DayOfWeek.FRIDAY;
            case java.util.Calendar.SATURDAY:
                return DayOfWeek.SATURDAY;
            case java.util.Calendar.SUNDAY:
            default:
                return DayOfWeek.SUNDAY;
        }
    }

    private void setupWeekDays() {
        weekDaysContainer.removeAllViews();
        DayOfWeek[] daysOfWeek = DayOfWeek.values();
        int firstDayIndex = firstDayOfWeek.ordinal();
        for (int i = 0; i < 7; i++) {
            DayOfWeek day = daysOfWeek[(firstDayIndex + i) % 7];
            TextView dayView = new TextView(requireContext());
            dayView.setLayoutParams(new LinearLayout.LayoutParams(
                    0,
                    LinearLayout.LayoutParams.WRAP_CONTENT,
                    1
            ));
            dayView.setGravity(android.view.Gravity.CENTER);

            Typeface typeface = ResourcesCompat.getFont(requireContext(), R.font.poppins_semi_bold);
            if (typeface != null) {
                dayView.setTypeface(typeface);
            }
            dayView.setTextColor(ContextCompat.getColor(requireContext(), R.color.gray_EBEBF5_30a));
            dayView.setTextSize(13);
            String dayName = day.name().substring(0, 3);
            dayView.setText(dayName);
            weekDaysContainer.addView(dayView);
        }
    }

    private void setupCalendar() {
//        startMonth = currentMonth.minusMonths(12);
//        endMonth = currentMonth;
        calendarView.setup(startMonth, endMonth, firstDayOfWeek);
        calendarView.scrollToMonth(currentMonth);
        calendarView.setDayBinder(new DayBinder());

        updateMonthYearText();
        updateSelectedDateText();
        updateMonthNavigationButtons();
    }

    private void setupListeners() {
        prevMonthImageView.setOnClickListener(v -> {
            currentMonth = currentMonth.minusMonths(1);
            calendarView.smoothScrollToMonth(currentMonth);
            updateMonthYearText();
        });
        nextMonthImageView.setOnClickListener(v -> {
            currentMonth = currentMonth.plusMonths(1);
            calendarView.smoothScrollToMonth(currentMonth);
            updateMonthYearText();
        });
        prevYearImageView.setOnClickListener(v -> {
            selectingMonth = selectingMonth.minusYears(1);
            updateYearText();
            updateYearNavigationButtons();
            updateMonthGrid();
        });
        nextYearImageView.setOnClickListener(v -> {
            selectingMonth = selectingMonth.plusYears(1);
            updateYearText();
            updateYearNavigationButtons();
            updateMonthGrid();
        });
        cancelTextView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onSelectionCancelled();
            }
            dismiss();
        });
        resetTextView.setOnClickListener(v -> {
            currentMonth = YearMonth.now();
            if (isMonthSelection) {
                setupMonthView();

            } else {
                startDate = null;
                endDate = null;
                setupCalendarView();
                calendarView.notifyCalendarChanged();
            }
        });
        confirmTextView.setOnClickListener(v -> {
            if (isMonthSelection) {
                currentMonth = selectingMonth;
                setupCalendarView();
            } else {
                if (listener != null) {
                    listener.onDateSelected(startDate, endDate);
                }
                dismiss();
            }
        });
        calendarView.setMonthScrollListener(month -> {
            currentMonth = month.getYearMonth();
            updateMonthYearText();
            updateMonthNavigationButtons();
            return null;
        });
    }

    private void setupCalendarView() {
        isMonthSelection = false;
        datesPanel.setVisibility(View.VISIBLE);
        monthPanel.setVisibility(View.GONE);
        setupCalendar();
    }

    private void setupMonthView() {
        isMonthSelection = true;
        datesPanel.setVisibility(View.GONE);
        monthPanel.setVisibility(View.VISIBLE);

        selectingMonth = currentMonth;
        updateMonthGrid();
        updateYearText();
        updateYearNavigationButtons();
    }

    private void updateMonthGrid() {
        String[] chineseNumerals = {
                getString(R.string.month_jan),
                getString(R.string.month_feb),
                getString(R.string.month_mar),
                getString(R.string.month_apr),
                getString(R.string.month_may),
                getString(R.string.month_jun),
                getString(R.string.month_jul),
                getString(R.string.month_aug),
                getString(R.string.month_sep),
                getString(R.string.month_oct),
                getString(R.string.month_nov),
                getString(R.string.month_dec),
        };
        List<ItemMonth> monthList = new ArrayList<>();

        YearMonth now = YearMonth.now();
        int year = selectingMonth.getYear();

        ItemMonth firstEnabledMonth = null;
        ItemMonth lastEnabledMonth = null;

        // Build month list
        for (int i = 1; i <= 12; i++) {
            YearMonth ym = YearMonth.of(year, i);
            boolean isEnabled = !(ym.isBefore(startMonth) || ym.isAfter(endMonth) || ym.isAfter(now));
            if (isEnabled) {
                if (firstEnabledMonth == null)
                    firstEnabledMonth = new ItemMonth(i, chineseNumerals[i - 1], false, true);
                lastEnabledMonth = new ItemMonth(i, chineseNumerals[i - 1], false, true);
            }
            // Preselection will be handled later
            ItemMonth item = new ItemMonth(i, chineseNumerals[i - 1], false, isEnabled);
            monthList.add(item);
        }
        // Check if selectingMonth is disabled
        YearMonth ymSelecting = selectingMonth;
        boolean isSelectingDisabled = ymSelecting.isBefore(startMonth) || ymSelecting.isAfter(endMonth) || ymSelecting.isAfter(now);
        if (isSelectingDisabled) {
            // Preselect first enabled if exists, otherwise last enabled
            if (firstEnabledMonth != null) {
                selectingMonth = YearMonth.of(year, firstEnabledMonth.getId());
                for (ItemMonth m : monthList) {
                    if (m.getId() == firstEnabledMonth.getId()) m.setSelected(true);
                }
            } else if (lastEnabledMonth != null) {
                selectingMonth = YearMonth.of(year, lastEnabledMonth.getId());
                for (ItemMonth m : monthList) {
                    if (m.getId() == lastEnabledMonth.getId()) m.setSelected(true);
                }
            }
        } else {
            // Keep existing selection if it's enabled
            for (ItemMonth m : monthList) {
                if (m.getId() == selectingMonth.getMonthValue() && m.isEnabled()) {
                    m.setSelected(true);
                }
            }
        }

        MonthGridItemAdapter adapter = new MonthGridItemAdapter(requireContext(), monthList);
        monthGridView.setAdapter(adapter);
        adapter.setOnMonthClickListener(month -> {
            if (month.isEnabled()) {
                selectingMonth = YearMonth.of(year, month.getId());
                adapter.setSelectedMonth(month.getId());
            }
        });
    }

    private void updateMonthYearText() {
        DateTimeFormatter formatter = DateTimeFormatter.ofPattern("MMMM yyyy", Locale.ENGLISH);
        monthYearTextView.setText(currentMonth.format(formatter));
    }

    private void updateYearText() {
        DateTimeFormatter formatter = DateTimeFormatter.ofPattern("yyyy", Locale.ENGLISH);
        yearTextView.setText(selectingMonth.format(formatter));
    }

    private void updateMonthNavigationButtons() {
        prevMonthImageView.setEnabled(currentMonth.isAfter(startMonth));
        prevMonthImageView.setAlpha(prevMonthImageView.isEnabled() ? 1F : 0.4F);
        nextMonthImageView.setEnabled(currentMonth.isBefore(endMonth));
        nextMonthImageView.setAlpha(nextMonthImageView.isEnabled() ? 1F : 0.4F);
    }

    private void updateYearNavigationButtons() {
        prevYearImageView.setEnabled(selectingMonth.isAfter(startMonth));
        prevYearImageView.setAlpha(prevYearImageView.isEnabled() ? 1F : 0.4F);
        nextYearImageView.setEnabled(selectingMonth.isBefore(endMonth));
        nextYearImageView.setAlpha(nextYearImageView.isEnabled() ? 1F : 0.4F);
    }

    private void updateSelectedDateText() {
        if (startDate != null && endDate != null) {
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern("MMMM dd, yyyy", Locale.ENGLISH);
            String text = String.format("Selected: %s - %s",
                    startDate.format(formatter),
                    endDate.format(formatter));
            Log.d("###", "updateSelectedDateText: " + text);
        } else if (startDate != null) {
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern("MMMM dd, yyyy", Locale.ENGLISH);
            Log.d("###", "updateSelectedDateText: " + startDate.format(formatter));
        } else {
            Log.d("###", "updateSelectedDateText: " + "No date selected");
        }
    }

    private void updateButtonStates() {
        boolean hasSelection = startDate != null;
        resetTextView.setEnabled(hasSelection);
        if (hasSelection) {
            resetTextView.setAlpha(1F);
            resetTextView.setEnabled(true);
        } else {
            resetTextView.setAlpha(0.4F);
            resetTextView.setEnabled(false);
        }
    }

    // Helper method to check if a date is between the selection
    private boolean isDateBetweenSelection(LocalDate date) {
        return startDate != null && endDate != null &&
                (date.isAfter(startDate) && date.isBefore(endDate));
    }

    // Helper method to check if an in-date is between selection
    private boolean isInDateBetweenSelection(LocalDate date) {
        return startDate != null && endDate != null &&
                isDateBetweenSelection(date);
    }

    // Helper method to check if an out-date is between selection
    private boolean isOutDateBetweenSelection(LocalDate date) {
        return startDate != null && endDate != null &&
                isDateBetweenSelection(date);
    }

    // Day binder implementation with continuous selection
    private class DayBinder implements MonthDayBinder<DayViewContainer> {
        @NonNull
        @Override
        public DayViewContainer create(@NonNull View view) {
            TextView textView = view.findViewById(R.id.calendar_day_text);
            View continuousBackgroundView = view.findViewById(R.id.continuousBackgroundView);
            View roundBackgroundView = view.findViewById(R.id.roundBackgroundView);
            return new DayViewContainer(view, textView, continuousBackgroundView, roundBackgroundView);
        }

        @Override
        public void bind(@NonNull DayViewContainer container, CalendarDay day) {
            container.textView.setText(String.valueOf(day.getDate().getDayOfMonth()));

            // Reset all backgrounds
            container.continuousBackgroundView.setBackground(null);
            container.roundBackgroundView.setBackground(null);
            container.roundBackgroundView.setVisibility(View.GONE);
            container.continuousBackgroundView.setVisibility(View.VISIBLE);

            if (day.getPosition() == DayPosition.MonthDate) {
                container.textView.setVisibility(View.VISIBLE);

                LocalDate today = LocalDate.now();
                LocalDate oneYearAgo = today.minusYears(1);
                LocalDate date = day.getDate();

                boolean isBeforeOneYearAgo = date.isBefore(oneYearAgo);
                boolean isFutureDate = date.isAfter(today);
                boolean isToday = today.equals(date);
                boolean isStartDate = date.equals(startDate);
                boolean isEndDate = date.equals(endDate);
                boolean isDatesEqual = startDate != null && startDate.equals(endDate);
                boolean isSingleSelection = (isStartDate && endDate == null) || (isDatesEqual && isStartDate);
                boolean isRangeSelection = startDate != null && endDate != null && !startDate.equals(endDate);
                boolean isBetweenSelection = isDateBetweenSelection(date);

                Typeface thinTypeface = ResourcesCompat.getFont(requireContext(), R.font.poppins_thin);
                container.textView.setTypeface(thinTypeface);
                container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.white_FFFFFF));
                container.textView.setAlpha(isFutureDate || isBeforeOneYearAgo ? 0.5f : 1.0f);
                if (!isFutureDate && !isBeforeOneYearAgo) {
                    if (isSingleSelection) {
                        // Single date selection - use round background
                        container.roundBackgroundView.setBackgroundResource(R.drawable.bg_selected_date);
                        container.roundBackgroundView.setVisibility(View.VISIBLE);
                        container.continuousBackgroundView.setVisibility(View.GONE);
                        container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.black_000000));
                    } else if (isRangeSelection && isStartDate) {
                        // Start of range
                        container.continuousBackgroundView.setBackgroundResource(R.drawable.bg_range_start);
                        container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.black_000000));
                    } else if (isRangeSelection && isEndDate) {
                        // End of range
                        container.continuousBackgroundView.setBackgroundResource(R.drawable.bg_range_end);
                        container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.black_000000));
                    } else if (isBetweenSelection) {
                        // Middle of range
                        container.continuousBackgroundView.setBackgroundResource(R.drawable.bg_range_middle);
                        container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.black_000000));
                    } else {
                        // Not selected
                        container.roundBackgroundView.setVisibility(View.GONE);
                        container.continuousBackgroundView.setVisibility(View.GONE);
                    }
                    // Set typeface
                    Typeface boldTypeface = ResourcesCompat.getFont(requireContext(), R.font.poppins_semi_bold);
                    container.textView.setTypeface(isToday ? boldTypeface : thinTypeface);
                    // Handle click on the day view
                    container.view.setOnClickListener(v -> {
                        if (startDate == null && endDate == null) {
                            // First selection - single date
                            startDate = date;
                            endDate = null;
                        } else if (startDate != null && endDate == null) {
                            // Second selection - check if we need to swap
                            if (date.equals(startDate)) {
                                // Clicked the same date again - clear selection
                                startDate = null;
                            } else if (date.isBefore(startDate)) {
                                // Selected date is before start date - swap them
                                endDate = startDate;
                                startDate = date;
                            } else {
                                // Selected date is after start date - normal range
                                endDate = date;
                            }
                        } else {
                            // Both dates already selected - start new selection
                            startDate = date;
                            endDate = null;
                        }
                        updateSelectedDateText();
                        updateButtonStates();
                        calendarView.notifyCalendarChanged(); // Refresh calendar
                    });
                } else {
                    // Not selected
                    container.roundBackgroundView.setVisibility(View.GONE);
                    container.continuousBackgroundView.setVisibility(View.GONE);
                    container.view.setOnClickListener(null);
                }
            } else {
                // Handle in-dates and out-dates for continuous selection appearance
                LocalDate date = day.getDate();
                boolean isInDateBetween = isInDateBetweenSelection(date);
                boolean isOutDateBetween = isOutDateBetweenSelection(date);

                if (isInDateBetween || isOutDateBetween) {
                    container.textView.setVisibility(View.VISIBLE);
                    container.textView.setText(String.valueOf(day.getDate().getDayOfMonth()));
                    container.continuousBackgroundView.setBackgroundResource(R.drawable.bg_range_middle);
                    container.textView.setTextColor(ContextCompat.getColor(requireContext(), R.color.white_FFFFFF));
                } else {
                    container.textView.setVisibility(View.INVISIBLE);
                }

                container.view.setOnClickListener(null);
            }
        }
    }

    // Day view container
    public static class DayViewContainer extends ViewContainer {
        public TextView textView;
        public View continuousBackgroundView;
        public View roundBackgroundView;
        public View view;

        public DayViewContainer(@NonNull View view, TextView textView, View continuousBackgroundView, View roundBackgroundView) {
            super(view);
            this.view = view;
            this.textView = textView;
            this.continuousBackgroundView = continuousBackgroundView;
            this.roundBackgroundView = roundBackgroundView;
        }
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}