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
import com.dj.user.adapter.AffiliateDownlineListViewAdapter;
import com.dj.user.databinding.FragmentAffiliateDownlineBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Downline;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;

import java.util.List;

public class AffiliateDownlineFragment extends BaseFragment {

    private FragmentAffiliateDownlineBinding binding;
    private Context context;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private AffiliateDownlineListViewAdapter affiliateDownlineListViewAdapter;

    public AffiliateDownlineFragment newInstance(Context context) {
        AffiliateDownlineFragment fragment = new AffiliateDownlineFragment();
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
        binding = FragmentAffiliateDownlineBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getDownlineList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        affiliateDownlineListViewAdapter = new AffiliateDownlineListViewAdapter(context);
        binding.listViewDownline.setAdapter(affiliateDownlineListViewAdapter);
        affiliateDownlineListViewAdapter.setOnItemClickListener((position, object) -> {

        });
    }

    private void getDownlineList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getDownlineList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Downline>> response) {
                List<Downline> downlineList = response.getData();
                boolean hasData = downlineList != null && !downlineList.isEmpty();
                loadingPanel.setVisibility(View.GONE);
                if (hasData) {
                    affiliateDownlineListViewAdapter.replaceList(downlineList);
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