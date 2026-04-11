package com.dj.shop.widget;

import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.ListView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.adapter.CustomAdapter;
import com.dj.shop.model.Selectable;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

import java.lang.reflect.Type;
import java.util.ArrayList;

public class SelectionBottomSheetDialogFragment<T extends Selectable> extends BottomSheetDialogFragment {

    private static final String ARG_TITLE = "arg_title";
    private static final String ARG_LIST = "arg_list";
    private static final String ARG_CLASS = "arg_class";

    private String title;
    private ArrayList<T> dataList;
    private OnItemSelectedListener<T> listener;
    private CustomAdapter<T> adapter;
    private Class<T> clazz;

    public interface OnItemSelectedListener<T> {
        void onItemSelected(T item, int position);
    }

    public static <T extends Selectable> SelectionBottomSheetDialogFragment<T> newInstance(
            String title,
            ArrayList<T> list,
            CustomAdapter<T> adapter,
            OnItemSelectedListener<T> listener,
            Class<T> clazz
    ) {
        SelectionBottomSheetDialogFragment<T> fragment = new SelectionBottomSheetDialogFragment<>();
        Bundle args = new Bundle();
        args.putString(ARG_TITLE, title);
        args.putString(ARG_LIST, new Gson().toJson(list));
        args.putString(ARG_CLASS, clazz.getName());
        fragment.setArguments(args);
        fragment.setAdapter(adapter);
        fragment.setOnItemSelectedListener(listener);
        fragment.setClazz(clazz);
        return fragment;
    }

    public void setOnItemSelectedListener(OnItemSelectedListener<T> listener) {
        this.listener = listener;
    }

    public void setAdapter(CustomAdapter<T> adapter) {
        this.adapter = adapter;
    }

    public void setClazz(Class<T> clazz) {
        this.clazz = clazz;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_selection, container, false);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        TextView titleView = view.findViewById(R.id.textView_title);
        ListView listView = view.findViewById(R.id.listView_options);

        if (getArguments() != null) {
            title = getArguments().getString(ARG_TITLE);
            String jsonList = getArguments().getString(ARG_LIST);
            String className = getArguments().getString(ARG_CLASS);

            try {
                clazz = (Class<T>) Class.forName(className);
                Type type = TypeToken.getParameterized(ArrayList.class, clazz).getType();
                dataList = new Gson().fromJson(jsonList, type);
            } catch (ClassNotFoundException e) {
                Log.e("###", "onCreateView: ", e);
                dataList = new ArrayList<>();
            }
        }

        dismissImageView.setOnClickListener(v -> dismiss());
        titleView.setText(title);
        adapter.replaceList(dataList);
        adapter.setOnItemClickListener((position, item) -> {
            if (listener != null) {
                listener.onItemSelected((T) item, position);
            }
            dismiss();
        });

        listView.setAdapter(adapter);
        if (dataList != null) {
            int selectedIndex = -1;
            for (int i = 0; i < dataList.size(); i++) {
                if (dataList.get(i).isSelected()) {
                    selectedIndex = i;
                    break;
                }
            }
            if (selectedIndex >= 0) {
                int finalSelectedIndex = selectedIndex;
                listView.post(() -> listView.setSelection(finalSelectedIndex));
            }
        }
        return view;
    }

    @Override
    public void onStart() {
        super.onStart();

        View view = getView();
        if (view != null) {
            View parent = (View) view.getParent();
            BottomSheetBehavior<View> behavior = BottomSheetBehavior.from(parent);
            behavior.setState(BottomSheetBehavior.STATE_EXPANDED);
            behavior.setDraggable(false);
            behavior.setSkipCollapsed(true);
        }
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
