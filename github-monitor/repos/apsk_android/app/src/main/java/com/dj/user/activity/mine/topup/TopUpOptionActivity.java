package com.dj.user.activity.mine.topup;

import android.content.Intent;
import android.os.Bundle;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.TopUpListViewAdapter;
import com.dj.user.databinding.ActivityTopUpOptionBinding;
import com.dj.user.model.request.RequestPaymentType;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.PaymentType;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;

import java.util.ArrayList;
import java.util.List;

public class TopUpOptionActivity extends BaseActivity {

    private ActivityTopUpOptionBinding binding;
    private Member member;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTopUpOptionBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), "充值", 0, null);
        setupActionList();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getPaymentTypeList();
    }

    private void setupActionList() {
        List<PaymentType> paymentTypeList = new ArrayList<>();
        paymentTypeList.add(new PaymentType(1, R.drawable.ic_top_up_online, "线上银行充值"));
        paymentTypeList.add(new PaymentType(2, R.drawable.ic_top_up_coins, "货币钱包充值"));
        paymentTypeList.add(new PaymentType(3, R.drawable.ic_top_up_wallet, "eWallet 充值"));
        paymentTypeList.add(new PaymentType(4, R.drawable.ic_top_up_qr, "QR充值"));
        TopUpListViewAdapter topUpListViewAdapter = new TopUpListViewAdapter(this);
        topUpListViewAdapter.addList(paymentTypeList);
        binding.listViewTopUp.setAdapter(topUpListViewAdapter);
        topUpListViewAdapter.setOnItemClickListener((position, object) -> {
            PaymentType paymentType = (PaymentType) object;
            switch (paymentType.getId()) {
                case 1:
                    startAppActivity(new Intent(TopUpOptionActivity.this, TopUpAmountActivity.class),
                            null, false, false, true);
                    break;
                case 2:
                    startAppActivity(new Intent(TopUpOptionActivity.this, CryptoOptionActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    startAppActivity(new Intent(TopUpOptionActivity.this, WalletOptionActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    startAppActivity(new Intent(TopUpOptionActivity.this, TopUpQRActivity.class),
                            null, false, false, true);
                    break;
            }
        });
    }

    private void getPaymentTypeList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPaymentType request = new RequestPaymentType(member.getMember_id(), "");
        executeApiCall(this, apiService.getPaymentTypeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<PaymentType>> response) {
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