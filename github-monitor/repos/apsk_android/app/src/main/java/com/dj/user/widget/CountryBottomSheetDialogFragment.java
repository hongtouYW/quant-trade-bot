package com.dj.user.widget;

import android.content.res.Resources;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.adapter.CountryListViewAdapter;
import com.dj.user.model.response.Country;
import com.google.android.material.bottomsheet.BottomSheetBehavior;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;

import java.util.ArrayList;
import java.util.List;

public class CountryBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_LIST = "arg_list";

    private List<Country> originalList = new ArrayList<>();
    private List<Country> filteredList = new ArrayList<>();
    private CountryListViewAdapter adapter;
    private OnCountrySelectedListener listener;

    public interface OnCountrySelectedListener {
        void onCountrySelected(Country country, int position);
    }

    public static CountryBottomSheetDialogFragment newInstance(ArrayList<Country> list, OnCountrySelectedListener listener) {
        CountryBottomSheetDialogFragment fragment = new CountryBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putSerializable(ARG_LIST, list);
        fragment.setArguments(args);
        fragment.setCountrySelectedListener(listener);
        return fragment;
    }

    public void setCountrySelectedListener(OnCountrySelectedListener listener) {
        this.listener = listener;
    }

    protected void filterList(String keyword) {
        if (originalList == null || adapter == null) return;
        filteredList.clear();
        if (keyword.isEmpty()) {
            filteredList.addAll(originalList);
        } else {
            String lower = keyword.toLowerCase();
            String normalized = lower.startsWith("+") ? lower.substring(1) : lower;
            for (Country country : originalList) {
                String countryName = country.getCountry_name().toLowerCase();
                String phoneCode = country.getPhone_code();
                boolean matchName = countryName.contains(normalized);
                boolean matchPhone = phoneCode.startsWith(normalized);
                if (matchName || matchPhone) {
                    filteredList.add(country);
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

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_country, container, false);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        EditText searchEditText = view.findViewById(R.id.editText_search);
        ListView listView = view.findViewById(R.id.listView_options);

        if (getArguments() != null) {
            originalList = (ArrayList<Country>) getArguments().getSerializable(ARG_LIST);
        }

        dismissImageView.setOnClickListener(v -> dismiss());
        searchEditText.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void onTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void afterTextChanged(Editable editable) {
                String keyword = editable.toString().trim();
                filterList(keyword);
            }
        });

        adapter = new CountryListViewAdapter(requireContext());
        if (originalList != null) {
            filteredList.clear();
            filteredList.addAll(originalList);
        }
        adapter.replaceList(filteredList);
        adapter.setOnItemClickListener((position, country) -> {
            for (Country c : originalList) {
                c.setSelected(false);
            }
            ((Country) country).setSelected(true);
            adapter.notifyDataSetChanged();
            if (listener != null) {
                listener.onCountrySelected((Country) country, position);
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
        parent.getLayoutParams().height = ViewGroup.LayoutParams.MATCH_PARENT;
        behavior.setPeekHeight(Resources.getSystem().getDisplayMetrics().heightPixels);
        behavior.setState(BottomSheetBehavior.STATE_EXPANDED);
        behavior.setDraggable(false);
        behavior.setSkipCollapsed(true);
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
