package com.dj.user.dj.widget;

import static android.view.View.GONE;
import static android.view.View.VISIBLE;

import android.app.Dialog;
import android.os.Bundle;
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

import com.dj.user.R;
import com.dj.user.util.StringUtil;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

public class DJCustomBottomSheetDialogFragment extends BottomSheetDialogFragment {

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

    public static DJCustomBottomSheetDialogFragment newInstance(
            String title,
            String message,
            String positiveText,
            String negativeText,
            boolean isCenteredMessage,
            OnActionListener listener
    ) {
        DJCustomBottomSheetDialogFragment fragment = new DJCustomBottomSheetDialogFragment();
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
        View view = inflater.inflate(R.layout.dj_dialog_bottom_sheet, container, false);

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
            isCenteredMessage = getArguments().getBoolean(ARG_CENTERED_MESSAGE);

            titleView.setText(title);
            messageView.setText(message);
            messageView.setGravity(isCenteredMessage ? Gravity.CENTER : Gravity.START);
            messageView.setVisibility(message.isEmpty() ? GONE : VISIBLE);
            positiveBtn.setText(positiveText);
            positiveBtn.setVisibility(StringUtil.isNullOrEmpty(positiveText) ? GONE : VISIBLE);
            negativeBtn.setText(negativeText);
            negativeBtn.setVisibility(StringUtil.isNullOrEmpty(negativeText) ? GONE : VISIBLE);
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
