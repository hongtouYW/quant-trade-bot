package com.dj.user.fragment.promotion;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.adapter.RedeemListViewAdapter;
import com.dj.user.databinding.FragmentPromotionRedeemBinding;
import com.dj.user.enums.VIPType;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestBonusRedemption;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Redemption;
import com.dj.user.model.response.VIP;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.widget.CustomToast;

import java.util.List;

public class PromotionRedeemFragment extends BaseFragment {

    private FragmentPromotionRedeemBinding binding;
    private Context context;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private RedeemListViewAdapter redeemListViewAdapter;

    public PromotionRedeemFragment newInstance(Context context) {
        PromotionRedeemFragment fragment = new PromotionRedeemFragment();
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
        binding = FragmentPromotionRedeemBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getRedemptionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        redeemListViewAdapter = new RedeemListViewAdapter(context);
        binding.listViewRedeem.setAdapter(redeemListViewAdapter);
        binding.listViewRedeem.setExpanded(true);
        redeemListViewAdapter.setOnItemClickListener((position, object) -> {
        });
        redeemListViewAdapter.setOnRedeemClickListener((position, item) -> {
            if (item.getType().equalsIgnoreCase(VIPType.GENERAL.getValue())) {
                redeemGeneralBonus(item);
            } else if (item.getType().equalsIgnoreCase(VIPType.WEEKLY.getValue())) {
                redeemWeeklyBonus();
            } else if (item.getType().equalsIgnoreCase(VIPType.MONTHLY.getValue())) {
                redeemMonthlyBonus();
            }
        });
        binding.buttonRedeemAll.setOnClickListener(view -> redeemAllBonus());
    }

    private void getRedemptionList() {
        if (redeemListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getRedemptionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Redemption>> response) {
                List<Redemption> redemptionList = response.getData();
                loadingPanel.setVisibility(View.GONE);
                if (redemptionList != null && !redemptionList.isEmpty()) {
                    redeemListViewAdapter.replaceList(redemptionList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                redeemListViewAdapter.replaceList(null);
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                redeemListViewAdapter.replaceList(null);
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }
        }, false);
    }

    public void redeemGeneralBonus(Redemption redemption) {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestBonusRedemption request = new RequestBonusRedemption(member.getMember_id(), redemption.getVip_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemGeneralBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
                getRedemptionList();
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

    public void redeemWeeklyBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemWeeklyBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
                getRedemptionList();
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

    public void redeemMonthlyBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemMonthlyBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
                getRedemptionList();
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

    private void redeemAllBonus() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.redeemAllBonus(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CustomToast.showTopToast(context, getString(R.string.vip_redeem_success));
                getRedemptionList();
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

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
