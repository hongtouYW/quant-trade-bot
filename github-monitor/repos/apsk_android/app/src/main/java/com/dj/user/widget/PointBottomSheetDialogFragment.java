package com.dj.user.widget;

import android.annotation.SuppressLint;
import android.content.Context;
import android.os.Bundle;
import android.os.Handler;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.model.ItemChip;
import com.dj.user.util.FormatUtils;
import com.google.android.flexbox.FlexboxLayout;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

import java.text.DecimalFormat;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class PointBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_ADD = "arg_add";
    private static final String ARG_POSITION = "arg_position";
    private static final String ARG_CURRENT = "arg_current";
    private int position = 0;
    private double current = 0.00;
    private boolean isAdd = false;
    private Handler handler = new Handler();
    private boolean isHolding = false;

    public interface OnAmountSelectedListener {
        void onAmountSelected(int position, double amount);
    }

    private OnAmountSelectedListener listener;

    public void setOnAmountSelectedListener(OnAmountSelectedListener listener) {
        this.listener = listener;
    }

    public static PointBottomSheetDialogFragment newInstance(boolean isAdd, int position, double current) {
        PointBottomSheetDialogFragment fragment = new PointBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putBoolean(ARG_ADD, isAdd);
        args.putDouble(ARG_POSITION, position);
        args.putDouble(ARG_CURRENT, current);
        fragment.setArguments(args);
        return fragment;
    }

    @Override
    public void onStart() {
        super.onStart();
        BottomSheetDialog dialog = (BottomSheetDialog) getDialog();
        if (dialog != null) {
            // Keep wrap_content height
            View bottomSheet = dialog.findViewById(com.google.android.material.R.id.design_bottom_sheet);
            if (bottomSheet != null) {
                bottomSheet.getLayoutParams().height = ViewGroup.LayoutParams.WRAP_CONTENT;
                bottomSheet.requestLayout();
                BottomSheetBehavior<View> behavior = BottomSheetBehavior.from(bottomSheet);
                behavior.setPeekHeight(BottomSheetBehavior.PEEK_HEIGHT_AUTO);
                behavior.setSkipCollapsed(true);
                behavior.setState(BottomSheetBehavior.STATE_COLLAPSED);
            }
            // Let keyboard push up the bottom sheet
            dialog.getWindow().setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);
        }
    }


    @SuppressLint("ClickableViewAccessibility")
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_point, container, false);
        Context context = requireContext();

        TextView titleTextView = view.findViewById(R.id.textView_title);
        FlexboxLayout pointFlexboxLayout = view.findViewById(R.id.flexboxLayout_point);
        EditText pointEditText = view.findViewById(R.id.editText_point);
        TextView addTextView = view.findViewById(R.id.textView_add);
        TextView minusTextView = view.findViewById(R.id.textView_minus);
        TextView currentTextView = view.findViewById(R.id.textView_current_point);
        Button doneButton = view.findViewById(R.id.button_done);

        if (getArguments() != null) {
            isAdd = getArguments().getBoolean(ARG_ADD, false);
            position = getArguments().getInt(ARG_POSITION, 0);
            current = getArguments().getDouble(ARG_CURRENT, 0.00);
        }
        titleTextView.setText("输入转移金额"); // isAdd ? "输入转移金额 (+)" : "输入转移金额 (-)");
        currentTextView.setText(String.format("目前Points %s", FormatUtils.formatAmount(current)));

        pointEditText.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;

            @Override
            public void afterTextChanged(Editable s) {
                if (editing) return;
                editing = true;
                try {
                    String raw = s.toString().trim();
                    if (raw.isEmpty()) {
                        resetChips(context, pointFlexboxLayout);
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        resetChips(context, pointFlexboxLayout);
                        pointEditText.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits) / 100.0;
                    DecimalFormat formatter = new DecimalFormat("###,##0.00");
                    String formatted = formatter.format(parsed);

                    pointEditText.setText(formatted);
                    pointEditText.setSelection(formatted.length());
                    String plainValue = String.valueOf((int) parsed);
                    for (int i = 0; i < pointFlexboxLayout.getChildCount(); i++) {
                        View child = pointFlexboxLayout.getChildAt(i);
                        TextView chipLabel = child.findViewById(R.id.chip_label);
                        if (chipLabel.getText().toString().equalsIgnoreCase(plainValue)) {
                            // Highlight this chip
                            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
                            child.setBackgroundResource(R.drawable.bg_button_orange);
                        } else {
                            // Reset chip
                            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
                            child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                        }
                    }
                } catch (Exception e) {
                    Log.e("###", "afterTextChanged: ", e);
                }
                editing = false;
            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {

            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {

            }
        });

        String[] amountTypeList = new String[]{"50", "100", "250", "500", "MAX"};
        List<ItemChip> options = new ArrayList<>();
        for (String amount : amountTypeList) {
            double value = amount.equalsIgnoreCase("max") ? current : Double.parseDouble(amount);
            options.add(new ItemChip(amount, FormatUtils.formatAmount(value)));
        }
        LayoutInflater chipInflater = LayoutInflater.from(context);
        for (ItemChip chip : options) {
            View chipView = chipInflater.inflate(R.layout.item_chip_filter, pointFlexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            chipView.setOnClickListener(v -> {
                for (int i = 0; i < pointFlexboxLayout.getChildCount(); i++) {
                    View child = pointFlexboxLayout.getChildAt(i);
                    TextView textView = child.findViewById(R.id.chip_label);

                    textView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
                    child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                }
                chipLabel.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
                chipView.setBackgroundResource(R.drawable.bg_button_orange);

                pointEditText.setText(chip.getValue());
            });
            pointFlexboxLayout.addView(chipView);
        }

        addTextView.setOnClickListener(v -> {
            ((BaseActivity) context).hideKeyboard((BaseActivity) context);
            adjustAmount(pointEditText, 0.01);
        });
        addTextView.setOnTouchListener((v, event) -> handleHoldStop(event));
        addTextView.setOnLongClickListener(v -> {
            startHold(pointEditText, 0.01);
            return true;
        });
        minusTextView.setOnClickListener(v -> {
            ((BaseActivity) context).hideKeyboard((BaseActivity) context);
            adjustAmount(pointEditText, -0.01);
        });
        minusTextView.setOnLongClickListener(v -> {
            startHold(pointEditText, -0.01);
            return true;
        });
        minusTextView.setOnTouchListener((v, event) -> handleHoldStop(event));

        doneButton.setOnClickListener(v -> {
            ((BaseActivity) context).hideKeyboard((BaseActivity) context);
            double value = 0.0;
            try {
                String raw = pointEditText.getText().toString().replace(",", "");
                value = Double.parseDouble(raw.isEmpty() ? "0" : raw);
            } catch (NumberFormatException e) {
                Log.e("###", "adjustAmount: ", e);
            }
            if (listener != null) {
                listener.onAmountSelected(position, value);
            }
            dismiss();
        });
        return view;
    }

    private void resetChips(Context context, FlexboxLayout flexboxLayout) {
        for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
            View child = flexboxLayout.getChildAt(i);
            TextView chipLabel = child.findViewById(R.id.chip_label);
            chipLabel.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
        }
    }

    private boolean handleHoldStop(MotionEvent event) {
        if (event.getAction() == MotionEvent.ACTION_UP || event.getAction() == MotionEvent.ACTION_CANCEL) {
            isHolding = false;
        }
        return false;
    }

    private void startHold(EditText editText, double delta) {
        isHolding = true;
        handler.post(new Runnable() {
            @Override
            public void run() {
                if (!isHolding) return;
                boolean reachedLimit = adjustAmount(editText, delta);
                if (!reachedLimit) handler.postDelayed(this, 100);
            }
        });
    }

    private boolean adjustAmount(EditText editText, double delta) {
        double value = 0.0;
        try {
            String raw = editText.getText().toString().replace(",", "");
            value = Double.parseDouble(raw.isEmpty() ? "0" : raw);
        } catch (NumberFormatException e) {
            Log.e("###", "adjustAmount: ", e);
        }
        value += delta;
        if (value < 0) {
            value = 0;
        }
        if (isAdd && value > current) {
            value = current;
        }
        editText.setText(String.format(Locale.ENGLISH, "%.2f", value));
        editText.setSelection(editText.getText().length());

        // stop loop when hitting bounds
        return (value <= 0) || (isAdd && value >= current);
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
