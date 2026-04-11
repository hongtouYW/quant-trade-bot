package com.dj.user.widget;

import android.app.Dialog;
import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.TextView;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.model.response.Player;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;

public class PlayerDialog extends Dialog {

    private final Context context;
    private final OnButtonClickListener positiveButtonClickListener;

    public interface OnButtonClickListener {
        void onButtonClicked();
    }

    public PlayerDialog(Context context, String yxiName, Player player, String positiveButtonText, OnButtonClickListener positiveButtonClickListener) {
        super(context);
        this.context = context;
        this.positiveButtonClickListener = positiveButtonClickListener;

        if (getWindow() != null) {
            getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
        }
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setCancelable(false);
        setCanceledOnTouchOutside(false);
        setupConfirmationDialog(yxiName, player, positiveButtonText);
        setupDialogWidthLarge();
    }

    private void setupConfirmationDialog(String yxiName, Player player, String positiveButtonText) {
        setContentView(R.layout.dialog_player);
        ImageView linkImageView = findViewById(R.id.imageView_link);
        TextView yxiNameTextView = findViewById(R.id.textView_yxi);
        TextView loginTextView = findViewById(R.id.textView_login);
        ImageView copyImageView = findViewById(R.id.imageView_copy);
        EditText passwordEditText = findViewById(R.id.editText_password);
        ImageView passwordToggleImageView = findViewById(R.id.imageView_password_toggle);
        Button positiveDialogButton = findViewById(R.id.button_dialog_positive);
        TextView backTextView = findViewById(R.id.textView_back);

        if (!yxiName.isEmpty()) {
            yxiNameTextView.setText(yxiName);
        }
        if (player != null) {
            loginTextView.setText(player.getLoginId());
            copyImageView.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", player.getLogin()));
            passwordEditText.setText(player.getPass());
            passwordToggleImageView.setOnClickListener(view -> ((BaseActivity) context).togglePasswordVisibility(passwordEditText, passwordToggleImageView));
        }
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
        backTextView.setOnClickListener(view -> dismiss());
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