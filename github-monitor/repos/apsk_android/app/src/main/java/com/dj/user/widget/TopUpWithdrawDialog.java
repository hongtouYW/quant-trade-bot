package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.Button;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.util.FormatUtils;

public class TopUpWithdrawDialog extends Dialog {

    private final Context context;
    private final OnButtonClickListener positiveButtonClickListener;

    public interface OnButtonClickListener {
        void onButtonClicked();
    }

    public TopUpWithdrawDialog(Context context, boolean isTopUp, String title, String desc, double amount, String positiveButtonText, OnButtonClickListener positiveButtonClickListener) {
        super(context);
        this.context = context;
        this.positiveButtonClickListener = positiveButtonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupConfirmationDialog(isTopUp, title, desc, amount, positiveButtonText);
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog(boolean isTopUp, String title, String desc, double amount, String positiveButtonText) {
        setContentView(R.layout.dialog_top_up_withdraw);
        TextView titleTextView = findViewById(R.id.textView_title);
        TextView descTextView = findViewById(R.id.textView_desc);
        TextView amountTextView = findViewById(R.id.textView_amount);
        Button positiveDialogButton = findViewById(R.id.button_dialog_positive);

        titleTextView.setText(title);
        descTextView.setText(desc);
        amountTextView.setText(String.format("%s RM%s", isTopUp ? "+" : "", FormatUtils.formatAmount(amount)));
        if (!positiveButtonText.isEmpty()) {
            positiveDialogButton.setVisibility(View.VISIBLE);
            positiveDialogButton.setText(positiveButtonText);
            positiveDialogButton.setOnClickListener(view -> {
                if (positiveButtonClickListener != null) {
                    positiveButtonClickListener.onButtonClicked();
                }
                dismiss();
            });
        } else {
            positiveDialogButton.setVisibility(View.GONE);
            positiveDialogButton.setOnClickListener(null);
        }
    }

    private void setupDialogWidthLarge() {
        int oneSidePadding = FormatUtils.dpToPx(context, 10);
        int deviceWidth = FormatUtils.getDeviceWidth(context);
//        int maxWidthPx = FormatUtils.dpToPx(context, 340); // 340dp converted to pixels
        int dialogWidth = deviceWidth - (oneSidePadding * 2);
//        if (dialogWidth > maxWidthPx) {
//            dialogWidth = maxWidthPx; // limit width to 340dp
//        }
        if (getWindow() != null) {
            getWindow().setLayout(dialogWidth, ViewGroup.LayoutParams.WRAP_CONTENT);
        }
    }
}