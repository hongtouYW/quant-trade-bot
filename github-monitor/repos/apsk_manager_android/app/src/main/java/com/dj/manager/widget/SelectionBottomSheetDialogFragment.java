package com.dj.manager.widget;

import android.content.res.Resources;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.manager.R;
import com.dj.manager.adapter.CustomAdapter;
import com.dj.manager.model.Selectable;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

import java.lang.reflect.Type;
import java.util.ArrayList;

public class SelectionBottomSheetDialogFragment<T extends Selectable> extends BottomSheetDialogFragment {

    private static final String ARG_TITLE = "arg_title";
    private static final String ARG_SEARCH = "arg_search";
    private static final String ARG_LIST = "arg_list";
    private static final String ARG_CLASS = "arg_class";

    private String title;
    private boolean isSearchAllowed;
    private ArrayList<T> originalList = new ArrayList<>();
    private ArrayList<T> filteredList = new ArrayList<>();
    private OnItemSelectedListener<T> listener;
    private CustomAdapter<T> adapter;
    private Class<T> clazz;

    public interface OnItemSelectedListener<T> {
        void onItemSelected(T item, int position);
    }

    public static <T extends Selectable> SelectionBottomSheetDialogFragment<T> newInstance(
            String title,
            boolean isSearchAllowed,
            ArrayList<T> list,
            CustomAdapter<T> adapter,
            OnItemSelectedListener<T> listener,
            Class<T> clazz
    ) {
        SelectionBottomSheetDialogFragment<T> fragment = new SelectionBottomSheetDialogFragment<>();
        Bundle args = new Bundle();
        args.putString(ARG_TITLE, title);
        args.putBoolean(ARG_SEARCH, isSearchAllowed);
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

    protected void filterList(String keyword) {
        if (originalList == null || adapter == null) return;
        filteredList.clear();
        if (keyword.isEmpty()) {
            filteredList.addAll(originalList);
        } else {
            for (T item : originalList) {
                String text = item.getSearchableText().toLowerCase();
                if (text.contains(keyword)) {
                    filteredList.add(item);
                }
            }
        }
        adapter.replaceList(filteredList);
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_selection, container, false);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        TextView titleView = view.findViewById(R.id.textView_title);
        LinearLayout searchPanel = view.findViewById(R.id.panel_search);
        EditText searchEditText = view.findViewById(R.id.editText_search);
        ImageView clearImageView = view.findViewById(R.id.imageView_clear);
        ListView listView = view.findViewById(R.id.listView_options);

        if (getArguments() != null) {
            title = getArguments().getString(ARG_TITLE);
            isSearchAllowed = getArguments().getBoolean(ARG_SEARCH, false);
            String jsonList = getArguments().getString(ARG_LIST);
            String className = getArguments().getString(ARG_CLASS);

            try {
                clazz = (Class<T>) Class.forName(className);
                Type type = TypeToken.getParameterized(ArrayList.class, clazz).getType();
                originalList = new Gson().fromJson(jsonList, type);
            } catch (ClassNotFoundException e) {
                Log.e("###", "onCreateView: ", e);
                originalList = new ArrayList<>();
            }
        }

        dismissImageView.setOnClickListener(v -> dismiss());
        titleView.setText(title);
        if (isSearchAllowed) {
            searchPanel.setVisibility(View.VISIBLE);
            searchEditText.addTextChangedListener(new TextWatcher() {
                @Override
                public void beforeTextChanged(CharSequence charSequence, int i, int i1, int i2) {

                }

                @Override
                public void onTextChanged(CharSequence charSequence, int i, int i1, int i2) {

                }

                @Override
                public void afterTextChanged(Editable editable) {
                    if (editable.length() > 0) {
                        clearImageView.setVisibility(View.VISIBLE);
                    } else {
                        clearImageView.setVisibility(View.GONE);
                    }
                    String keyword = editable.toString().trim();
                    filterList(keyword);
                }
            });
            clearImageView.setOnClickListener(v -> {
                searchEditText.setText("");
                clearImageView.setVisibility(View.GONE);
            });
        } else {
            searchPanel.setVisibility(View.GONE);
        }
        if (originalList != null) {
            filteredList.clear();
            filteredList.addAll(originalList);
        }
        adapter.replaceList(filteredList);
        adapter.setOnItemClickListener((position, item) -> {
            if (listener != null) {
                listener.onItemSelected((T) item, position);
            }
            dismiss();
        });

        listView.setAdapter(adapter);
        if (filteredList != null) {
            int selectedIndex = -1;
            for (int i = 0; i < filteredList.size(); i++) {
                if (filteredList.get(i).isSelected()) {
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
        if (view == null) return;
        View parent = (View) view.getParent();
        if (parent == null) return;

        BottomSheetBehavior<View> behavior = BottomSheetBehavior.from(parent);
        if (isSearchAllowed) {
            parent.getLayoutParams().height = ViewGroup.LayoutParams.MATCH_PARENT;
            behavior.setPeekHeight(Resources.getSystem().getDisplayMetrics().heightPixels);
        }
        behavior.setState(BottomSheetBehavior.STATE_EXPANDED);
        behavior.setDraggable(false);
        behavior.setSkipCollapsed(true);
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
