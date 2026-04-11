package com.dj.shop.widget;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ListView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.adapter.FeedbackTypeListViewAdapter;
import com.dj.shop.model.response.FeedbackType;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

import java.util.ArrayList;

public class FeedbackTypeBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_TITLE = "arg_title";
    private static final String ARG_LIST = "arg_list";

    private String title;
    private ArrayList<FeedbackType> feedbackTypeList;
    private OnFeedbackTypeSelectedListener listener;

    public interface OnFeedbackTypeSelectedListener {
        void onFeedbackTypeSelected(FeedbackType feedbackType, int position);
    }

    public static FeedbackTypeBottomSheetDialogFragment newInstance(String title, ArrayList<FeedbackType> list, OnFeedbackTypeSelectedListener listener) {
        FeedbackTypeBottomSheetDialogFragment fragment = new FeedbackTypeBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putString(ARG_TITLE, title);
        args.putSerializable(ARG_LIST, list);
        fragment.setArguments(args);
        fragment.setFeedbackTypeSelectedListener(listener);
        return fragment;
    }

    public void setFeedbackTypeSelectedListener(OnFeedbackTypeSelectedListener listener) {
        this.listener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_feedback_type, container, false);
        TextView titleView = view.findViewById(R.id.textView_title);
        ListView listView = view.findViewById(R.id.listView_options);

        if (getArguments() != null) {
            title = getArguments().getString(ARG_TITLE);
            feedbackTypeList = (ArrayList<FeedbackType>) getArguments().getSerializable(ARG_LIST);
        }

        titleView.setText(title);
        FeedbackTypeListViewAdapter adapter = new FeedbackTypeListViewAdapter(requireContext());
        adapter.replaceList(feedbackTypeList);
        adapter.setOnItemClickListener((position, feedbackType) -> {
            for (int i = 0; i < feedbackTypeList.size(); i++) {
                feedbackTypeList.get(i).setSelected(i == position);
            }
            adapter.notifyDataSetChanged();
            if (listener != null) {
                listener.onFeedbackTypeSelected((FeedbackType) feedbackType, position);
            }
            dismiss();
        });

        listView.setAdapter(adapter);
        return view;
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
