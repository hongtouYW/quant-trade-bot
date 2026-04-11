package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.mine.affiliate.AffiliateActivity;
import com.dj.user.adapter.AffiliateCommissionListViewAdapter;
import com.dj.user.databinding.FragmentAffiliateCommisionBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Commission;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;

import java.util.List;

public class AffiliateCommisionFragment extends BaseFragment {

    private FragmentAffiliateCommisionBinding binding;
    private Context context;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private AffiliateCommissionListViewAdapter commissionListViewAdapter;

    public AffiliateCommisionFragment newInstance(Context context) {
        AffiliateCommisionFragment fragment = new AffiliateCommisionFragment();
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
        binding = FragmentAffiliateCommisionBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getCommissionList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        commissionListViewAdapter = new AffiliateCommissionListViewAdapter(context);
        binding.listViewCommission.setAdapter(commissionListViewAdapter);
        commissionListViewAdapter.setOnItemClickListener((position, object) -> {

        });
    }

    private void getCommissionList() {
        if (commissionListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getCommissionList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Commission>> response) {
                List<Commission> commissionList = response.getData();
                loadingPanel.setVisibility(View.GONE);
                if (commissionList != null && !commissionList.isEmpty()) {
                    commissionListViewAdapter.replaceList(commissionList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
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
        }, false);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}