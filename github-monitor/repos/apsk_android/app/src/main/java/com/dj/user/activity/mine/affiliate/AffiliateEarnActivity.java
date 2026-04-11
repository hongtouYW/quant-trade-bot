package com.dj.user.activity.mine.affiliate;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.view.inputmethod.InputMethodManager;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityAffiliateEarnBinding;
import com.dj.user.model.request.RequestInvitationCodeEdit;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.InvitationCode;
import com.dj.user.model.response.InvitationSummary;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.SocialMediaUtil;
import com.dj.user.util.StringUtil;

import java.util.List;

public class AffiliateEarnActivity extends BaseActivity {

    private ActivityAffiliateEarnBinding binding;
    private Member member;
    private InvitationCode defaultInvitationCode;
    private InvitationSummary invitationSummary;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityAffiliateEarnBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.affiliate_title), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getInvitationCodeList();
    }

    private void setupUI() {
        binding.panelMore.setOnClickListener(view ->
                startAppActivity(new Intent(AffiliateEarnActivity.this, AffiliateCodeActivity.class),
                        null, false, false, true
                ));
        binding.panelSummary.setOnClickListener(view ->
                startAppActivity(new Intent(AffiliateEarnActivity.this, AffiliateCodeDetailsActivity.class),
                        null, false, false, true
                ));
    }

    private void setupDefaultInvitationCodeViewData() {
        if (defaultInvitationCode == null) {
            return;
        }
        if (!defaultInvitationCode.isEditing()) {
            binding.panelLabelEdit.setVisibility(View.GONE);
            binding.panelLabel.setVisibility(View.VISIBLE);
            binding.textViewLabel.setText(!StringUtil.isNullOrEmpty(defaultInvitationCode.getInvitecode_name()) ? defaultInvitationCode.getInvitecode_name() : getString(R.string.affiliate_code_default_label));
            binding.imageViewEdit.setOnClickListener(view -> {
                defaultInvitationCode.setEditing(true);
                setupDefaultInvitationCodeViewData();
            });
        } else {
            binding.panelLabelEdit.setVisibility(View.VISIBLE);
            binding.panelLabel.setVisibility(View.GONE);
            binding.editTextLabel.setText(!StringUtil.isNullOrEmpty(defaultInvitationCode.getInvitecode_name()) ? defaultInvitationCode.getInvitecode_name() : getString(R.string.affiliate_code_default_label));
            binding.editTextLabel.post(() -> {
                binding.editTextLabel.requestFocus();
                binding.editTextLabel.setSelection(binding.editTextLabel.length());
                InputMethodManager imm = (InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
                imm.showSoftInput(binding.editTextLabel, InputMethodManager.SHOW_FORCED);
            });
            binding.imageViewTick.setOnClickListener(view -> {
                String invitationCodeName = binding.editTextLabel.getText().toString();
                if (!invitationCodeName.isEmpty()) {
                    setInvitationCodeName(defaultInvitationCode, invitationCodeName);
                }
            });
        }
        binding.imageViewDefault.setImageResource(defaultInvitationCode.isDefault() ? R.drawable.ic_check_selected : R.drawable.ic_check);
        binding.textViewInvitationCode.setText(!StringUtil.isNullOrEmpty(defaultInvitationCode.getReferralCode()) ? defaultInvitationCode.getReferralCode() : "-");
        binding.imageViewCopyCode.setOnClickListener(view -> StringUtil.copyToClipboard(AffiliateEarnActivity.this, "", defaultInvitationCode.getReferralCode()));
        binding.textViewInvitationLink.setText(!StringUtil.isNullOrEmpty(defaultInvitationCode.getQr()) ? defaultInvitationCode.getQr() : "-");
        binding.textViewInvitationLink.setSelected(true);
        binding.imageViewCopyLink.setOnClickListener(view -> StringUtil.copyToClipboard(AffiliateEarnActivity.this, "", defaultInvitationCode.getQr()));
        binding.buttonInviteNow.setOnClickListener(view -> SocialMediaUtil.shareGeneric(AffiliateEarnActivity.this, null, defaultInvitationCode.getQr()));
    }

    private void setupInvitationSummaryViewData() {
        if (invitationSummary == null) {
            return;
        }
        binding.textViewTeamCount.setText(String.valueOf(invitationSummary.getTotaldownline()));
        binding.textViewTradedCount.setText(String.valueOf(invitationSummary.getTotalcredit()));
        binding.textViewAmount.setText(FormatUtils.formatAmount(invitationSummary.getTotalcommission()));
    }

    private void getInvitationCodeList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getInvitationCodeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<InvitationCode>> response) {
                List<InvitationCode> invitationCodeList = response.getData();
                for (InvitationCode invitationCode : invitationCodeList) {
                    if (invitationCode.isDefault()) {
                        defaultInvitationCode = invitationCode;
                        break;
                    }
                }
                getInvitationSummary();
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, defaultInvitationCode == null);
    }

    private void getInvitationSummary() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getInvitationSummary(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<InvitationSummary> response) {
                invitationSummary = response.getData();
                setupDefaultInvitationCodeViewData();
                setupInvitationSummaryViewData();
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, invitationSummary == null);
    }

    public void setInvitationCodeName(InvitationCode invitationCode, String invitationCodeName) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestInvitationCodeEdit request = new RequestInvitationCodeEdit(member.getMember_id(), invitationCode.getInvitation_id(), invitationCodeName);
        executeApiCall(this, apiService.setInvitationCodeLabel(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<InvitationCode> response) {
                defaultInvitationCode.setInvitecode_name(invitationCodeName);
                defaultInvitationCode.setEditing(false);
                setupDefaultInvitationCodeViewData();
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