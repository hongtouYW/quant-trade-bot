package com.dj.manager.activity.user;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.yxi.YxiListActivity;
import com.dj.manager.databinding.ActivityUserDetailsBinding;
import com.dj.manager.enums.ReasonActionType;
import com.dj.manager.enums.UserStatus;
import com.dj.manager.model.request.RequestMemberProfile;
import com.dj.manager.model.request.RequestUpdateMemberPhone;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;

public class UserDetailsActivity extends BaseActivity {
    private ActivityUserDetailsBinding binding;
    private boolean isPasswordVisible = false;
    private Manager manager;
    private Member member;
    private String password = "";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityUserDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupUI();
        bindMemberData();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getMemberProfile();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        member = new Gson().fromJson(json, Member.class);
    }

    private void setupToolbar() {
        UserStatus status = member.getUserStatus();
//        if (status != UserStatus.DELETED) {
//            setupToolbar(binding.toolbar.getRoot(), getString(R.string.user_details_title), getString(R.string.user_details_delete), R.color.red_D32424, view -> {
//                deleteMember();
//            });
//        } else {
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.user_details_title), null, 0, null);
//        }
    }

    private void setupUI() {
        binding.imageViewPasswordToggle.setOnClickListener(view -> {
            isPasswordVisible = !isPasswordVisible;
            if (!StringUtil.isNullOrEmpty(password)) {
                updatePasswordVisibility();
            } else {
                if (isPasswordVisible) {
                    getMemberPassword();
                }
            }
        });

        binding.buttonChangePassword.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonChangePassword.setOnClickListener(view -> startMemberActivity(UserChangePasswordActivity.class));
        binding.buttonAllYxi.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonAllYxi.setOnClickListener(view -> startMemberActivity(YxiListActivity.class));
    }

    private void bindMemberData() {
        setupToolbar();
        String vipName = "vip_" + member.getVip();
        int resId = getResources().getIdentifier(vipName, "drawable", getPackageName());
        UserStatus status = member.getUserStatus();

        binding.imageViewVip.setImageResource(resId);
        binding.editTextPhone.setText(FormatUtils.formatMsianPhone(member.getPhone()));
        binding.editTextPhone.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getPhone()));
        binding.editTextPhone.addTextChangedListener(new TextWatcher() {
            private boolean isFormatting;

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (isFormatting) return;
                isFormatting = true;
                String raw = s.toString()
                        .replaceAll("\\s+", "")   // remove spaces
                        .replaceAll("[^0-9+]", ""); // allow only + and digits
                if (raw.length() > 15) {
                    raw = raw.substring(0, 15);
                }
                String formatted = FormatUtils.formatMsianPhone(raw);
                binding.editTextPhone.setText(formatted);
                binding.editTextPhone.setSelection(formatted.length());
                isFormatting = false;
            }
        });
        binding.imageViewEdit.setOnClickListener(view -> {
            binding.imageViewEdit.setVisibility(View.GONE);
            binding.imageViewConfirm.setVisibility(View.VISIBLE);
            binding.imageViewCancel.setVisibility(View.VISIBLE);

            binding.editTextPhone.setEnabled(true);
            binding.editTextPhone.requestFocus();
            binding.editTextPhone.setSelection(binding.editTextPhone.length());
        });
        binding.imageViewConfirm.setOnClickListener(view -> {
            binding.imageViewEdit.setVisibility(View.VISIBLE);
            binding.imageViewConfirm.setVisibility(View.GONE);
            binding.imageViewCancel.setVisibility(View.GONE);

            binding.editTextPhone.setEnabled(false);
            updateMemberPhone();
        });
        binding.imageViewCancel.setOnClickListener(view -> {
            binding.imageViewEdit.setVisibility(View.VISIBLE);
            binding.imageViewConfirm.setVisibility(View.GONE);
            binding.imageViewCancel.setVisibility(View.GONE);

            binding.editTextPhone.setText(FormatUtils.formatMsianPhone(member.getPhone()));
            binding.editTextPhone.setEnabled(false);
        });

        binding.textViewLocation.setText(member.getMemberLocation());
        binding.textViewId.setText(member.getPrefix());
        binding.textViewId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getPrefix()));
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewStatus.setTextColor(ContextCompat.getColor(this, status.getColorResId()));
        binding.textViewShopId.setText(member.getShopPrefix());
        binding.textViewShopId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", member.getShop_id()));
        binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(member.getBalance())));
        binding.textViewLastLogon.setText(DateFormatUtils.timeAgo(this, member.getLastlogin_on(), true));

        binding.buttonCta.setText(status.getActionTitle());
        binding.buttonCta.setTextColor(ContextCompat.getColor(this, status.getActionColorResId()));
        binding.buttonCta.setBackgroundResource(status.getActionBgResId());

        binding.buttonCta.setOnClickListener(view -> blockUnblockMember());

        updateButtonsByStatus(status);
    }

    private void updatePasswordVisibility() {
        binding.textViewPassword.setText(isPasswordVisible ? password : getString(R.string.placeholder_password_masked));
        binding.textViewPassword.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", password));
        binding.imageViewPasswordToggle.setImageResource(
                isPasswordVisible ? R.drawable.ic_eye_on : R.drawable.ic_eye_off
        );
    }

    private void startMemberActivity(Class<?> targetClass) {
        Bundle bundle = new Bundle();
        bundle.putString("data", new Gson().toJson(member));
        startAppActivity(new Intent(this, targetClass), bundle, false, false, false, true);
    }

    private void updateButtonsByStatus(UserStatus status) {
        boolean enableAll = (status == UserStatus.ACTIVE);
        boolean enableCta = (status == UserStatus.ACTIVE || status == UserStatus.BLOCKED);

        setViewState(binding.panelPhone, enableAll);
        setViewState(binding.imageViewEdit, enableAll);
        setViewState(binding.buttonChangePassword, enableAll);
        setViewState(binding.buttonAllYxi, enableAll);
        setViewState(binding.buttonCta, enableCta);
    }

    private void setViewState(View view, boolean enabled) {
        view.setEnabled(enabled);
        view.setAlpha(enabled ? 1.0F : 0.4F);
    }

    private void getMemberProfile() {
        if (member == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberProfile request = new RequestMemberProfile(manager.getManager_id(), member.getMember_id());
        executeApiCall(this, apiService.getMemberProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                bindMemberData();
                getMemberPassword();
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

    private void getMemberPassword() {
        if (member == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberProfile request = new RequestMemberProfile(manager.getManager_id(), member.getMember_id());
        executeApiCall(this, apiService.getMemberPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                password = response.getPassword();
                updatePasswordVisibility();
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

    private void blockUnblockMember() {
        if (member == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberProfile request = new RequestMemberProfile(manager.getManager_id(), member.getMember_id());

        if (member.getUserStatus() == UserStatus.ACTIVE) {
            executeApiCall(this, apiService.blockMember(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Member> response) {
                    member = response.getData();
                    bindMemberData();
                    CustomToast.showTopToast(UserDetailsActivity.this, getString(R.string.user_details_block_success));

                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ReasonActionType.BLOCK_USER));
                    bundle.putString("id", member.getMember_id());
                    startAppActivity(new Intent(UserDetailsActivity.this, ActionReasonActivity.class),
                            bundle, false, false, false, false);
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
        } else {
            executeApiCall(this, apiService.unblockMember(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Member> response) {
                    member = response.getData();
                    bindMemberData();
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

    private void deleteMember() {
        if (member == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberProfile request = new RequestMemberProfile(manager.getManager_id(), member.getMember_id());
        executeApiCall(this, apiService.deleteMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                bindMemberData();
                CustomToast.showTopToast(UserDetailsActivity.this, getString(R.string.user_details_delete_success));

                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(ReasonActionType.DELETE_USER));
                bundle.putString("id", member.getMember_id());
                startAppActivity(new Intent(UserDetailsActivity.this, ActionReasonActivity.class),
                        bundle, false, false, false, false);
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

    private void updateMemberPhone() {
        if (member == null) {
            return;
        }
        String phone = binding.editTextPhone.getText().toString()
                .replaceAll("\\s+", "")
                .replaceAll("^\\+", "");
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateMemberPhone request = new RequestUpdateMemberPhone(manager.getManager_id(), member.getMember_id(), phone);
        executeApiCall(this, apiService.editMemberPhone(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                bindMemberData();
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