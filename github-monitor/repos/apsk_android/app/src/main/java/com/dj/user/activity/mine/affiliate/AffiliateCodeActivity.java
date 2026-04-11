package com.dj.user.activity.mine.affiliate;

import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.AffiliateCodeListViewAdapter;
import com.dj.user.databinding.ActivityAffiliateCodeBinding;
import com.dj.user.model.request.RequestInvitationCodeEdit;
import com.dj.user.model.request.RequestInvitationCodeNew;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.InvitationCode;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.SocialMediaUtil;

import java.util.List;

public class AffiliateCodeActivity extends BaseActivity {

    private ActivityAffiliateCodeBinding binding;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private AffiliateCodeListViewAdapter affiliateCodeListViewAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityAffiliateCodeBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.affiliate_code_title), R.drawable.ic_add_gradient, view -> createNewInvitationCode());
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getInvitationCodeList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        affiliateCodeListViewAdapter = new AffiliateCodeListViewAdapter(this);
        binding.listViewAffiliateCode.setAdapter(affiliateCodeListViewAdapter);
        binding.listViewAffiliateCode.setExpanded(true);
        affiliateCodeListViewAdapter.setOnItemClickListener((position, object) -> {
        });
    }

    public void shareInvitation(InvitationCode invitationCode) {
        SocialMediaUtil.shareGeneric(this, null, invitationCode.getQr());
    }

    private void getInvitationCodeList() {
        if (affiliateCodeListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getInvitationCodeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<InvitationCode>> response) {
                List<InvitationCode> invitationCodeList = response.getData();
                loadingPanel.setVisibility(View.GONE);
                if (invitationCodeList != null && !invitationCodeList.isEmpty()) {
                    affiliateCodeListViewAdapter.replaceList(invitationCodeList);
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

    private void createNewInvitationCode() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestInvitationCodeNew request = new RequestInvitationCodeNew(member.getMember_id());
        executeApiCall(this, apiService.createNewInvitationCode(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<InvitationCode> response) {
                getInvitationCodeList();
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

    public void setDefaultInvitationCode(InvitationCode invitationCode) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestInvitationCodeEdit request = new RequestInvitationCodeEdit(member.getMember_id(), invitationCode.getInvitation_id());
        executeApiCall(this, apiService.setDefaultInvitationCode(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<InvitationCode> response) {
                getInvitationCodeList();
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

    public void setInvitationCodeName(InvitationCode invitationCode, String invitationCodeName) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestInvitationCodeEdit request = new RequestInvitationCodeEdit(member.getMember_id(), invitationCode.getInvitation_id(), invitationCodeName);
        executeApiCall(this, apiService.setInvitationCodeLabel(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<InvitationCode> response) {
                affiliateCodeListViewAdapter.finishEditing(invitationCode, invitationCodeName);
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