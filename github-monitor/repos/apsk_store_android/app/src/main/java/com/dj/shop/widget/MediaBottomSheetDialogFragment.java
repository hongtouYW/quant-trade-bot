package com.dj.shop.widget;

import android.app.Dialog;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.FrameLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

public class MediaBottomSheetDialogFragment extends BottomSheetDialogFragment {

    public interface OnMediaOptionClickListener {
        void onTakePhoto();

        void onChooseGallery();
    }

    private OnMediaOptionClickListener mediaOptionClickListener;

    public static MediaBottomSheetDialogFragment newInstance(OnMediaOptionClickListener listener) {
        MediaBottomSheetDialogFragment fragment = new MediaBottomSheetDialogFragment();
        fragment.setMediaOptionClickListener(listener);
        return fragment;
    }

    public void setMediaOptionClickListener(OnMediaOptionClickListener listener) {
        this.mediaOptionClickListener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.dialog_bottom_sheet_media, container, false);

        // Bind UI elements
        TextView galleryTextView = view.findViewById(R.id.textView_gallery);
        TextView photoTextView = view.findViewById(R.id.textView_camera);
        TextView cancelTextView = view.findViewById(R.id.textView_cancel);

        galleryTextView.setOnClickListener(v -> {
            if (mediaOptionClickListener != null) mediaOptionClickListener.onChooseGallery();
            dismiss();
        });
        photoTextView.setOnClickListener(v -> {
            if (mediaOptionClickListener != null) mediaOptionClickListener.onTakePhoto();
            dismiss();
        });
        cancelTextView.setOnClickListener(v -> dismiss());

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
//                bottomSheet.setBackground(ContextCompat.getDrawable(requireContext(), R.drawable.bg_bottom_sheet_dialog));
            }
        });
        return dialog;
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
