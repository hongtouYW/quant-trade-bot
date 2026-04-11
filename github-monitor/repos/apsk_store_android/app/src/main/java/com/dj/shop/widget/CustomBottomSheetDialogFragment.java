package com.dj.shop.widget;

import static android.view.View.GONE;
import static android.view.View.VISIBLE;

import android.app.Dialog;
import android.os.Build;
import android.os.Bundle;
import android.text.Html;
import android.text.Spanned;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.util.StringUtil;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

public class CustomBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_TITLE = "arg_title";
    private static final String ARG_MESSAGE = "arg_message";
    private static final String ARG_POSITIVE_TEXT = "arg_positive_text";
    private static final String ARG_NEGATIVE_TEXT = "arg_negative_text";
    private static final String ARG_CENTERED_MESSAGE = "arg_centered_message";

    private String title;
    private String message;
    private String positiveText;
    private String negativeText;
    private boolean isCenteredMessage;
    private OnActionListener actionListener;

    public interface OnActionListener {
        void onPositiveClick();

        void onNegativeClick();
    }

    public static CustomBottomSheetDialogFragment newInstance(
            String title,
            String message,
            String positiveText,
            String negativeText,
            boolean isCenteredMessage,
            OnActionListener listener
    ) {
        CustomBottomSheetDialogFragment fragment = new CustomBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putString(ARG_TITLE, title);
        args.putString(ARG_MESSAGE, message);
        args.putString(ARG_POSITIVE_TEXT, positiveText);
        args.putString(ARG_NEGATIVE_TEXT, negativeText);
        args.putBoolean(ARG_CENTERED_MESSAGE, isCenteredMessage);
        fragment.setArguments(args);
        fragment.setActionListener(listener);
        return fragment;
    }

    public void setActionListener(OnActionListener listener) {
        this.actionListener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.dialog_bottom_sheet, container, false);

        // Bind UI elements
        TextView titleView = view.findViewById(R.id.textView_title);
        TextView messageView = view.findViewById(R.id.textView_message);
        Button positiveBtn = view.findViewById(R.id.button_confirm);
        Button negativeBtn = view.findViewById(R.id.button_cancel);

        // Set values
        if (getArguments() != null) {
            title = getArguments().getString(ARG_TITLE);
            message = getArguments().getString(ARG_MESSAGE);
            positiveText = getArguments().getString(ARG_POSITIVE_TEXT);
            negativeText = getArguments().getString(ARG_NEGATIVE_TEXT);
            isCenteredMessage = getArguments().getBoolean(ARG_CENTERED_MESSAGE, false);

            if (!StringUtil.isNullOrEmpty(title)) {
                titleView.setText(title);
                titleView.setVisibility(VISIBLE);
            } else {
                titleView.setVisibility(GONE);
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
                    messageView.setText(spanned);
                } else {
                    messageView.setText(message);
                }
                messageView.setGravity(isCenteredMessage ? Gravity.CENTER : Gravity.START);
                messageView.setVisibility(VISIBLE);
            } else {
                messageView.setVisibility(GONE);
            }
            if (!StringUtil.isNullOrEmpty(positiveText)) {
                positiveBtn.setText(positiveText);
                positiveBtn.setVisibility(VISIBLE);
            } else {
                positiveBtn.setVisibility(GONE);
            }
            if (!StringUtil.isNullOrEmpty(negativeText)) {
                negativeBtn.setText(negativeText);
                negativeBtn.setVisibility(VISIBLE);
            } else {
                negativeBtn.setVisibility(GONE);
            }
        }
        // Click listeners
        positiveBtn.setOnClickListener(v -> {
            if (actionListener != null) actionListener.onPositiveClick();
            dismiss();
        });
        negativeBtn.setOnClickListener(v -> {
            if (actionListener != null) actionListener.onNegativeClick();
            dismiss();
        });
        return view;
    }

    private String sanitizeHtml(String html) {
        if (html == null) return null;
        // Remove <p> and </p>
        html = html.replaceAll("(?i)<p[^>]*>", "");
        html = html.replaceAll("(?i)</p>", "<br>");
        return html;
    }

    @NonNull
    @Override
    public Dialog onCreateDialog(@Nullable Bundle savedInstanceState) {
        BottomSheetDialog dialog = (BottomSheetDialog) super.onCreateDialog(savedInstanceState);
        dialog.setOnShowListener(dialogInterface -> {
            FrameLayout bottomSheet = ((BottomSheetDialog) dialogInterface)
                    .findViewById(com.google.android.material.R.id.design_bottom_sheet);
            if (bottomSheet != null) {
                BottomSheetBehavior<View> behavior = BottomSheetBehavior.from(bottomSheet);
                behavior.setState(BottomSheetBehavior.STATE_EXPANDED);
                bottomSheet.setBackground(ContextCompat.getDrawable(requireContext(), R.drawable.bg_bottom_sheet_dialog));
            }
        });
        return dialog;
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
