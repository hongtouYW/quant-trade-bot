package com.dj.user.widget;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.app.Dialog;
import android.content.Context;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.view.ViewGroup;
import android.view.Window;
import android.view.animation.LinearInterpolator;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.util.FormatUtils;

public class YxiActionDialog extends Dialog {

    private final Context context;
    private ImageView refreshImageView;
    private ObjectAnimator refreshAnimator;
    private final OnButtonClickListener buttonClickListener;

    public interface OnButtonClickListener {
        void onRefreshClicked();

        void onTransferClicked();

        void onWithdrawClicked();

        void onExitClicked();
    }

    public YxiActionDialog(Context context, OnButtonClickListener buttonClickListener) {
        super(context);
        this.context = context;
        this.buttonClickListener = buttonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupConfirmationDialog();
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog() {
        setContentView(R.layout.dialog_yxi_action);
        LinearLayout refreshPanel = findViewById(R.id.panel_refresh);
        refreshImageView = findViewById(R.id.imageView_refresh);
        LinearLayout transferPanel = findViewById(R.id.panel_transfer);
        LinearLayout withdrawPanel = findViewById(R.id.panel_withdraw);
        TextView exitTextView = findViewById(R.id.textView_exit);
        ImageView dismissImageView = findViewById(R.id.imageView_dismiss);

        refreshPanel.setOnClickListener(view -> {
            startRefreshAnimation();
            if (buttonClickListener != null) {
                buttonClickListener.onRefreshClicked();
            }
        });
        transferPanel.setOnClickListener(view -> {
            if (buttonClickListener != null) {
                buttonClickListener.onTransferClicked();
            }
        });
        withdrawPanel.setOnClickListener(view -> {
            if (buttonClickListener != null) {
                buttonClickListener.onWithdrawClicked();
            }
        });
        exitTextView.setOnClickListener(view -> {
            if (buttonClickListener != null) {
                buttonClickListener.onExitClicked();
            }
            dismiss();
        });
        dismissImageView.setOnClickListener(view -> dismiss());
    }

    private void startRefreshAnimation() {
        if (refreshAnimator == null) {
            refreshAnimator = ObjectAnimator.ofFloat(refreshImageView, "rotation", 0f, 360f);
            refreshAnimator.setDuration(300);
            refreshAnimator.setRepeatCount(ValueAnimator.INFINITE);
            refreshAnimator.setInterpolator(new LinearInterpolator());
        }
        refreshAnimator.start();
    }

    public void stopRefreshAnimation() {
        if (refreshAnimator != null && refreshAnimator.isRunning()) {
            refreshAnimator.cancel();
            refreshImageView.setRotation(0f);
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