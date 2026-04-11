package com.dj.user.widget;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.GridView;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.adapter.AvatarGridViewAdapter;
import com.dj.user.model.response.Avatar;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

import java.util.ArrayList;

public class AvatarBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_TITLE = "arg_title";
    private static final String ARG_LIST = "arg_list";

    private String title;
    private ArrayList<Avatar> avatarArrayList;
    private OnAvatarSelectedListener listener;

    public interface OnAvatarSelectedListener {
        void onAvatarSelected(Avatar avatar);
    }

    public static AvatarBottomSheetDialogFragment newInstance(String title, ArrayList<Avatar> list, OnAvatarSelectedListener listener) {
        AvatarBottomSheetDialogFragment fragment = new AvatarBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putString(ARG_TITLE, title);
        args.putSerializable(ARG_LIST, list);
        fragment.setArguments(args);
        fragment.setAvatarSelectedListener(listener);
        return fragment;
    }

    public void setAvatarSelectedListener(OnAvatarSelectedListener listener) {
        this.listener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_grid, container, false);
        TextView titleView = view.findViewById(R.id.textView_title);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        ImageView confirmImageView = view.findViewById(R.id.imageView_confirm);
        GridView gridView = view.findViewById(R.id.gridView_avatars);

        if (getArguments() != null) {
            title = getArguments().getString(ARG_TITLE);
            avatarArrayList = (ArrayList<Avatar>) getArguments().getSerializable(ARG_LIST);
        }

        titleView.setText(title);
        AvatarGridViewAdapter adapter = new AvatarGridViewAdapter(requireContext(), avatarArrayList);
        gridView.setOnItemClickListener((parent, v, position, id) -> {
            adapter.setSelected(position);
        });
        dismissImageView.setOnClickListener(v -> dismiss());
        confirmImageView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onAvatarSelected(adapter.getSelected());
            }
            dismiss();
        });

        gridView.setAdapter(adapter);
        return view;
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
