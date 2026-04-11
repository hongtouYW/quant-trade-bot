package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.os.Build;
import android.text.Html;
import android.text.Spanned;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.Button;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;

public class CustomConfirmationDialog extends Dialog {

    private final Context context;
    private final OnButtonClickListener buttonClickListener;

    public interface OnButtonClickListener {
        void onPositiveButtonClicked();

        void onNegativeButtonClicked();
    }

    public CustomConfirmationDialog(Context context, String title, String message, String note, String negativeText, String positiveButtonText, boolean isCenteredMessage, OnButtonClickListener buttonClickListener) {
        super(context);
        this.context = context;
        this.buttonClickListener = buttonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupConfirmationDialog(title, message, note, negativeText, positiveButtonText, isCenteredMessage);
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog(String title, String message, String note, String negativeText, String positiveButtonText, boolean isCenteredMessage) {
        setContentView(R.layout.dialog_button);
        TextView titleTextView = findViewById(R.id.textView_dialog_title);
        View dividerView = findViewById(R.id.view_divider);
        TextView messageTextView = findViewById(R.id.textView_dialog_message);
        TextView noteTextView = findViewById(R.id.textView_dialog_note);
        TextView negativeTextView = findViewById(R.id.textView_dialog_negative);
        Button positiveDialogButton = findViewById(R.id.button_dialog_positive);

        if (!StringUtil.isNullOrEmpty(title)) {
            titleTextView.setText(title);
            titleTextView.setVisibility(View.VISIBLE);
            dividerView.setVisibility(View.VISIBLE);
        } else {
            titleTextView.setVisibility(View.GONE);
            dividerView.setVisibility(View.GONE);
        }
        if (!StringUtil.isNullOrEmpty(message)) {
            boolean isHtml = message.contains("<") && message.contains(">");
            if (isHtml) {
                String cleanHtml = sanitizeHtml(message);
                Spanned spanned;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                    spanned = Html.fromHtml(cleanHtml, Html.FROM_HTML_MODE_LEGACY);
                } else {
                    spanned = Html.fromHtml(cleanHtml);
                }
                messageTextView.setText(spanned);
            } else {
                messageTextView.setText(message);
            }
            messageTextView.setGravity(isCenteredMessage ? Gravity.CENTER : Gravity.START);
            messageTextView.setVisibility(View.VISIBLE);
        } else {
            messageTextView.setVisibility(View.GONE);
        }
        if (!StringUtil.isNullOrEmpty(note)) {
            noteTextView.setText(note);
            noteTextView.setVisibility(View.VISIBLE);
        } else {
            noteTextView.setVisibility(View.GONE);
        }
        if (!StringUtil.isNullOrEmpty(negativeText)) {
            negativeTextView.setVisibility(View.VISIBLE);
            negativeTextView.setText(negativeText);
            negativeTextView.setOnClickListener(view -> {
                if (buttonClickListener != null) {
                    buttonClickListener.onNegativeButtonClicked();
                }
                ((BaseActivity) context).dismissCustomConfirmationDialog();
            });
        } else {
            negativeTextView.setVisibility(View.GONE);
            negativeTextView.setOnClickListener(null);
        }
        if (!StringUtil.isNullOrEmpty(positiveButtonText)) {
            positiveDialogButton.setVisibility(View.VISIBLE);
            positiveDialogButton.setText(positiveButtonText);
            positiveDialogButton.setOnClickListener(view -> {
                if (buttonClickListener != null) {
                    buttonClickListener.onPositiveButtonClicked();
                }
                ((BaseActivity) context).dismissCustomConfirmationDialog();
            });
        } else {
            positiveDialogButton.setVisibility(View.GONE);
            positiveDialogButton.setOnClickListener(null);
        }
    }

    private String sanitizeHtml(String html) {
        if (html == null) return null;
        // Remove <p> and </p>
        html = html.replaceAll("(?i)<p[^>]*>", "");
        html = html.replaceAll("(?i)</p>", "<br>");
        return html;
    }

    private void setupDialogWidthLarge() {
        int oneSidePadding = FormatUtils.dpToPx(context, 10);
        int deviceWidth = FormatUtils.getDeviceWidth(context);
        int maxWidthPx = FormatUtils.dpToPx(context, 340); // 340dp converted to pixels
        int dialogWidth = deviceWidth - (oneSidePadding * 2);
        boolean isLandscape = context.getResources().getConfiguration().orientation == Configuration.ORIENTATION_LANDSCAPE;
        if (isLandscape && dialogWidth > maxWidthPx) {
            dialogWidth = maxWidthPx; // limit width only in landscape
        }
        if (getWindow() != null) {
            getWindow().setLayout(dialogWidth, ViewGroup.LayoutParams.WRAP_CONTENT);
        }
    }
}