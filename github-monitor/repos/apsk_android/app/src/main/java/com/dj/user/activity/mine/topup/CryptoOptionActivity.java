package com.dj.user.activity.mine.topup;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.BankOptionListViewAdapter;
import com.dj.user.databinding.ActivityCryptoOptionBinding;
import com.dj.user.model.response.Bank;

import java.util.ArrayList;
import java.util.List;

public class CryptoOptionActivity extends BaseActivity {

    private ActivityCryptoOptionBinding binding;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private BankOptionListViewAdapter bankOptionListViewAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityCryptoOptionBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "货币钱包充值", 0, null);
        setupUI();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        List<Bank> cryptoList = new ArrayList<>();
        cryptoList.add(new Bank("USDT"));
        cryptoList.add(new Bank("BitCoin"));
        bankOptionListViewAdapter = new BankOptionListViewAdapter(this);
        binding.listViewCryptoOption.setAdapter(bankOptionListViewAdapter);
        bankOptionListViewAdapter.setData(cryptoList);
        bankOptionListViewAdapter.setOnItemClickListener((position, object) -> {
            startAppActivity(new Intent(CryptoOptionActivity.this, TopUpAmountActivity.class),
                    null, false, false, false);
        });

        dataPanel.setVisibility(View.VISIBLE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.GONE);
    }

//    private void getBankList() {
//        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
//        executeApiCall(this, apiService.getBankList(), new ApiCallback<>() {
//            @Override
//            public void onSuccess(BaseResponse<List<Bank>> response) {
//                List<Bank> bankList = response.getData();
//                if (bankList != null && !bankList.isEmpty()) {
//                    bankOptionListViewAdapter.setData(response.getData());
//                    filterByKeyword();
//                } else {
//                    dataPanel.setVisibility(View.GONE);
//                    noDataPanel.setVisibility(View.VISIBLE);
//                    loadingPanel.setVisibility(View.GONE);
//                }
//            }
//
//            @Override
//            public boolean onApiError(int code, String message) {
//                return false;
//            }
//
//            @Override
//            public boolean onFailure(Throwable t) {
//                return false;
//            }
//        }, true);
//    }
}