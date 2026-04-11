package com.dj.user.activity.mine.bank;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.BankAccountListViewAdapter;
import com.dj.user.databinding.ActivityBankListBinding;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestQuickPay;
import com.dj.user.model.response.BankAccount;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomToast;
import com.google.gson.Gson;

import java.util.List;

public class BankListActivity extends BaseActivity {

    private ActivityBankListBinding binding;
    private Member member;
    private String bankAccountName;
    private BankAccountListViewAdapter bankAccountListViewAdapter;
    private LinearLayout bindedPanel;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBankListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.bank_details_title), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getBankAccountList();
    }

    private void setupUI() {
        bindedPanel = binding.panelBinded;
        bankAccountListViewAdapter = new BankAccountListViewAdapter(this);
        binding.listViewBank.setAdapter(bankAccountListViewAdapter);
        bankAccountListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            bundle.putBoolean("isViewMode", true);
            startAppActivity(new Intent(this, BankFormActivity.class),
                    bundle, false, false, true);
        });
        binding.panelAdd.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", bankAccountName);
            startAppActivity(new Intent(this, BankFormActivity.class),
                    !StringUtil.isNullOrEmpty(bankAccountName) ? bundle : null,
                    false, false, true
            );
        });
    }

    private void getBankAccountList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getBankAccountList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<BankAccount>> response) {
                List<BankAccount> bankAccountList = response.getData();
                if (bankAccountList != null && !bankAccountList.isEmpty()) {
                    bankAccountName = bankAccountList.get(0).getBank_full_name();
                    bankAccountListViewAdapter.replaceList(bankAccountList);
                    binding.panelBind.setVisibility(bankAccountList.size() < 10 ? View.VISIBLE : View.GONE);
                    bindedPanel.setVisibility(View.VISIBLE);
                } else {
                    bankAccountName = null;
                    bindedPanel.setVisibility(View.GONE);
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

    public void updateQuickPay(BankAccount bankAccount, int isQuickPay) {
        if (bankAccount == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestQuickPay request = new RequestQuickPay(member.getMember_id(), bankAccount.getBankaccount_id(), isQuickPay);
        executeApiCall(this, apiService.updateQuickPay(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<BankAccount> response) {
                getBankAccountList();
                CustomToast.showTopToast(BankListActivity.this, getString(R.string.bank_details_update_success));
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