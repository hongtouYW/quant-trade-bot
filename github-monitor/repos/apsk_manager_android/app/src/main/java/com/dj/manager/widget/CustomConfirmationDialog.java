package com.dj.manager.widget;

import android.app.Dialog;
import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.util.DisplayMetrics;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.TextView;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;

public class CustomConfirmationDialog extends Dialog {

    private final Context context;
    private final OnButtonClickListener positiveButtonClickListener;

    public interface OnButtonClickListener {
        void onButtonClicked();
    }

    public CustomConfirmationDialog(Context context, String title, String message, String positiveButtonText, OnButtonClickListener positiveButtonClickListener) {
        super(context);
        this.context = context;
        this.positiveButtonClickListener = positiveButtonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupConfirmationDialog(title, message, positiveButtonText);
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog(String title, String message, String positiveButtonText) {
        setContentView(R.layout.dialog_button);
        TextView titleTextView = findViewById(R.id.textView_dialog_title);
        TextView messageTextView = findViewById(R.id.textView_dialog_message);
        Button positiveDialogButton = findViewById(R.id.button_dialog_positive);

        if (!title.isEmpty()) {
            titleTextView.setText(title);
        }
        if (!message.isEmpty()) {
            messageTextView.setText(message);
        }

        if (!positiveButtonText.isEmpty()) {
            positiveDialogButton.setVisibility(View.VISIBLE);
            positiveDialogButton.setText(positiveButtonText);
            positiveDialogButton.setOnClickListener(view -> {
                if (positiveButtonClickListener != null) {
                    positiveButtonClickListener.onButtonClicked();
                }
                ((BaseActivity) context).dismissCustomConfirmationDialog();
            });
        } else {
            positiveDialogButton.setVisibility(View.GONE);
            positiveDialogButton.setOnClickListener(null);
        }
    }

    private void setupDialogWidthLarge() {
        int oneSidePadding = 32;
        if (getWindow() != null)
            getWindow().setLayout(getDeviceWidth(context) - (oneSidePadding * 2), ViewGroup.LayoutParams.WRAP_CONTENT);
    }

    private static int getDeviceWidth(Context context) {
        DisplayMetrics displayMetrics = new DisplayMetrics();
        WindowManager windowManager = (WindowManager) context.getSystemService(Context.WINDOW_SERVICE);
        if (windowManager != null) {
            windowManager.getDefaultDisplay().getMetrics(displayMetrics);
        }
        return displayMetrics.widthPixels;
    }
}