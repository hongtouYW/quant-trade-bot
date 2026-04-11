package com.dj.user.fragment.topup;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.mine.topup.TopUpActivity;
import com.dj.user.activity.mine.topup.TopUpAmountActivity;
import com.dj.user.adapter.BankOptionListViewAdapter;
import com.dj.user.databinding.FragmentTopUpWalletBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.response.Bank;

import java.util.ArrayList;
import java.util.List;

public class TopUpWalletFragment extends BaseFragment {

    private FragmentTopUpWalletBinding binding;
    private Context context;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private BankOptionListViewAdapter bankOptionListViewAdapter;

    public TopUpWalletFragment newInstance(Context context) {
        TopUpWalletFragment fragment = new TopUpWalletFragment();
        fragment.context = context;
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context ctx) {
        super.onAttach(ctx);
        if (context == null) {
            context = ctx;
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentTopUpWalletBinding.inflate(inflater, container, false);
        context = getContext();
        setupUI();
        return binding.getRoot();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        List<Bank> cryptoList = new ArrayList<>();
        cryptoList.add(new Bank("TNG eWallet 充值"));
        cryptoList.add(new Bank("Boost"));
        cryptoList.add(new Bank("Grab Pay"));
        cryptoList.add(new Bank("支付宝支付"));
        bankOptionListViewAdapter = new BankOptionListViewAdapter(context);
        binding.listViewWalletOption.setAdapter(bankOptionListViewAdapter);
        bankOptionListViewAdapter.setData(cryptoList);
        bankOptionListViewAdapter.setOnItemClickListener((position, object) -> {
            ((TopUpActivity) context).startAppActivity(new Intent(context, TopUpAmountActivity.class),
                    null, false, false, false);
        });

        dataPanel.setVisibility(View.VISIBLE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.GONE);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
