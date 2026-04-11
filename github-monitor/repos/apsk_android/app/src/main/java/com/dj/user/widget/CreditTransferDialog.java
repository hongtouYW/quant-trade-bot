package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.model.response.Player;
import com.dj.user.util.FormatUtils;

public class CreditTransferDialog extends Dialog {

    private final Context context;
    private final OnButtonClickListener buttonClickListener;

    public interface OnButtonClickListener {
        void onStartYxiClicked(double creditAmount);

        void onConvertClicked(double creditAmount);
    }

    public CreditTransferDialog(Context context, boolean isWithinYxi, Player player, OnButtonClickListener buttonClickListener) {
        super(context);
        this.context = context;
        this.buttonClickListener = buttonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(true);
        setupConfirmationDialog(isWithinYxi, player);
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog(boolean isWithinYxi, Player player) {
        setContentView(R.layout.dialog_credit_transfer);
        TextView memberIdTextView = findViewById(R.id.textView_member_id);
        TextView balanceAvailableTextView = findViewById(R.id.textView_balance_available);
        TextView yxiTextView = findViewById(R.id.textView_yxi);
        TextView playerIdTextView = findViewById(R.id.textView_player_id);
        TextView pointsTextView = findViewById(R.id.textView_points);
        EditText creditEditText = findViewById(R.id.editText_credit);
        TextView allTextView = findViewById(R.id.textView_all);
        Button convertButton = findViewById(R.id.button_convert);
        TextView startYxiTextView = findViewById(R.id.textView_start_yxi);
        ImageView dismissImageView = findViewById(R.id.imageView_dismiss);

        memberIdTextView.setText(String.format(context.getString(R.string.yxi_withdraw_id), player.getMember_id()));
        balanceAvailableTextView.setText(FormatUtils.formatAmount(player.getBalance()));
        yxiTextView.setText(String.format(context.getString(R.string.yxi_withdraw_points), player.getYxiProviderName()));
        playerIdTextView.setText(String.format(context.getString(R.string.yxi_withdraw_id), player.getGamemember_id()));
        pointsTextView.setText(FormatUtils.formatAmount(player.getBalance()));
        allTextView.setAlpha(0.5F);
        allTextView.setEnabled(false);
        allTextView.setOnClickListener(v -> creditEditText.setText(FormatUtils.formatAmount(player.getBalance())));
        creditEditText.setText(FormatUtils.formatAmount(player.getBalance()));
        creditEditText.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;
            private String current = "";

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (editing) return;
                editing = true;

                try {
                    String raw = s.toString();
                    if (raw.isEmpty()) {
                        creditEditText.setText("");
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        creditEditText.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits) / 100.0;
                    String creditPoints = FormatUtils.formatAmount(parsed);
                    balanceAvailableTextView.setText(creditPoints);
                    pointsTextView.setText(creditPoints);
                    creditEditText.setText(creditPoints);
                    creditEditText.setSelection(creditPoints.length());
                    allTextView.setAlpha(parsed == player.getMemberBalance() ? 0.5F : 1.0F);
                    allTextView.setEnabled(parsed != player.getMemberBalance());

                } catch (Exception e) {
                    e.printStackTrace();
                }
                editing = false;
            }
        });

        if (isWithinYxi) {
            convertButton.setVisibility(ViewGroup.VISIBLE);
            startYxiTextView.setVisibility(ViewGroup.GONE);
            dismissImageView.setVisibility(ViewGroup.VISIBLE);
            convertButton.setOnClickListener(view -> {
                double parsed = FormatUtils.getEditTextAmount(creditEditText);
                if (buttonClickListener != null) {
                    buttonClickListener.onConvertClicked(parsed);
                }
                dismiss();
            });
            dismissImageView.setOnClickListener(view -> dismiss());
        } else {
            convertButton.setVisibility(ViewGroup.GONE);
            startYxiTextView.setVisibility(ViewGroup.VISIBLE);
            dismissImageView.setVisibility(ViewGroup.GONE);
            startYxiTextView.setOnClickListener(view -> {
                double parsed = FormatUtils.getEditTextAmount(creditEditText);
                if (buttonClickListener != null) {
                    buttonClickListener.onStartYxiClicked(parsed);
                }
                dismiss();
            });
        }
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