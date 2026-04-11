package com.dj.user.activity.mine.bank;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.BankOptionListViewAdapter;
import com.dj.user.databinding.ActivityBankOptionBinding;
import com.dj.user.model.response.Bank;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.google.gson.Gson;

import java.util.List;

public class BankOptionActivity extends BaseActivity {

    private ActivityBankOptionBinding binding;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private BankOptionListViewAdapter bankOptionListViewAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBankOptionBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.bank_option_title), 0, null);
        setupUI();
    }

    @Override
    public void onResume() {
        super.onResume();
        getBankList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        binding.textViewSearch.setOnClickListener(view -> bankOptionListViewAdapter.getFilter().filter(binding.editTextSearch.getText()));
        binding.editTextSearch.addTextChangedListener(new TextWatcher() {
            @Override
            public void afterTextChanged(Editable s) {
                filterByKeyword();
            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }
        });

        bankOptionListViewAdapter = new BankOptionListViewAdapter(this);
        binding.listViewBankOption.setAdapter(bankOptionListViewAdapter);
        bankOptionListViewAdapter.setFilterListener(isEmpty -> {
            if (isEmpty) {
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
            } else {
                dataPanel.setVisibility(View.VISIBLE);
                noDataPanel.setVisibility(View.GONE);
                loadingPanel.setVisibility(View.GONE);
            }
        });
        bankOptionListViewAdapter.setOnItemClickListener((position, object) -> {
            Intent resultIntent = new Intent();
            resultIntent.putExtra("data", new Gson().toJson(object));
            setResult(RESULT_OK, resultIntent);
            finish();
        });
    }

    private void filterByKeyword() {
        String keyword = binding.editTextSearch.getText().toString();
        bankOptionListViewAdapter.getFilter().filter(keyword);
    }

    private void getBankList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getBankList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Bank>> response) {
                List<Bank> bankList = response.getData();
                if (bankList != null && !bankList.isEmpty()) {
                    bankOptionListViewAdapter.setData(response.getData());
                    filterByKeyword();
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}