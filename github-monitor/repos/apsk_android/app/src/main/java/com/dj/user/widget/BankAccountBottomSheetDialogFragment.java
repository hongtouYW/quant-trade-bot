package com.dj.user.widget;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.adapter.BankAccountSelectionListViewAdapter;
import com.dj.user.model.response.BankAccount;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class BankAccountBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_LIST = "arg_list";

    private ArrayList<BankAccount> bankAccountArrayList;
    private BankAccountSelectionListViewAdapter bankAccountSelectionListViewAdapter;
    private OnBankAccountSelectedListener bankAccountListener;
    private OnAddBankAccountListener addBankAccountListener;

    public interface OnBankAccountSelectedListener {
        void onBankAccountSelected(BankAccount bankAccount);
    }

    public interface OnAddBankAccountListener {
        void onAddBankAccount();
    }

    public static BankAccountBottomSheetDialogFragment newInstance(ArrayList<BankAccount> list, OnBankAccountSelectedListener bankAccountSelectedListener, OnAddBankAccountListener addBankAccountListener) {
        BankAccountBottomSheetDialogFragment fragment = new BankAccountBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putString(ARG_LIST, new Gson().toJson(list));
        fragment.setArguments(args);
        fragment.setBankAccountSelectedListener(bankAccountSelectedListener);
        fragment.setAddBankAccountListener(addBankAccountListener);
        return fragment;
    }

    public void setBankAccountSelectedListener(OnBankAccountSelectedListener listener) {
        this.bankAccountListener = listener;
    }

    public void setAddBankAccountListener(OnAddBankAccountListener listener) {
        this.addBankAccountListener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_bank_account, container, false);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        ImageView confirmImageView = view.findViewById(R.id.imageView_confirm);
        ExpandableHeightListView listView = view.findViewById(R.id.listView_bank_account);
        LinearLayout addPanel = view.findViewById(R.id.panel_add);

        if (getArguments() != null) {
            String json = getArguments().getString(ARG_LIST);
            if (json != null) {
                bankAccountArrayList = new ArrayList<>(Arrays.asList(new Gson().fromJson(json, BankAccount[].class)));
            }
        }
        bankAccountSelectionListViewAdapter = new BankAccountSelectionListViewAdapter(requireContext());
        bankAccountSelectionListViewAdapter.replaceList(bankAccountArrayList);
        bankAccountSelectionListViewAdapter.setOnItemClickListener((position, bankAccount) -> {
            bankAccountSelectionListViewAdapter.setSelected(position);
        });
        listView.setAdapter(bankAccountSelectionListViewAdapter);
        dismissImageView.setOnClickListener(v -> dismiss());
        confirmImageView.setOnClickListener(v -> {
            if (bankAccountListener != null) {
                bankAccountListener.onBankAccountSelected(bankAccountSelectionListViewAdapter.getSelected());
            }
            dismiss();
        });
        addPanel.setVisibility(bankAccountArrayList.size() < 10 ? View.VISIBLE : View.GONE);
        addPanel.setOnClickListener(v -> {
            if (addBankAccountListener != null) {
                addBankAccountListener.onAddBankAccount();
            }
        });
        return view;
    }

    public void updateBankAccounts(List<BankAccount> newBankAccounts) {
        if (bankAccountSelectionListViewAdapter != null) {
            bankAccountSelectionListViewAdapter.replaceList(newBankAccounts);
        }
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
