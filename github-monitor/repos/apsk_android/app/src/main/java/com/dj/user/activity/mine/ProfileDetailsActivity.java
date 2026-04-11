package com.dj.user.activity.mine;

import android.os.Bundle;
import android.util.Log;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityProfileDetailsBinding;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestProfileEdit;
import com.dj.user.model.response.Avatar;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.DateFormatUtils;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.AvatarBottomSheetDialogFragment;
import com.dj.user.widget.CalendarBottomSheetDialogFragment;
import com.dj.user.widget.CustomToast;
import com.dj.user.widget.RoundedEditTextView;
import com.squareup.picasso.Picasso;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class ProfileDetailsActivity extends BaseActivity implements CalendarBottomSheetDialogFragment.DateSelectionListener {

    private ActivityProfileDetailsBinding binding;
    private Member member;
    private String dob;
    private Avatar selectedAvatar;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityProfileDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
        setupUI();
        setupViewData();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getProfile();
    }

    private void setupUI() {
        binding.panelProfile.setOnClickListener(view -> getAvatarList());
        binding.editTextUsername.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        binding.editTextUsername.setBackgroundTransparent(true);
        binding.editTextUsername.setHint(getString(R.string.profile_username));

        binding.panelDob.setOnClickListener(view -> {
            clearErrorTransparent(this, binding.panelDob);
            LocalDate dobDate = null;
            if (!StringUtil.isNullOrEmpty(dob)) {
                try {
                    String dateOnly = dob.split(" ")[0];
                    DateTimeFormatter storageFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
                    dobDate = LocalDate.parse(dateOnly, storageFormatter);
                } catch (Exception e) {
                    Log.e("###", "setupUI: ", e);
                }
            }
            CalendarBottomSheetDialogFragment calendarSheet = CalendarBottomSheetDialogFragment.newInstance(
                    CalendarBottomSheetDialogFragment.MODE_SINGLE, dobDate, null, true
            );
            calendarSheet.setDateSelectionListener(this);
            calendarSheet.show(getSupportFragmentManager(), "CustomBottomSheet");
        });
        binding.buttonSave.setOnClickListener(view -> editProfile());
    }

    @Override
    public void onDateSelected(LocalDate startDate, LocalDate endDate) {
        if (startDate != null) {
            DateTimeFormatter formatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_DD_MM_YYYY, Locale.ENGLISH);
            binding.textViewDob.setText(startDate.format(formatter));

            DateTimeFormatter storageFormatter = DateTimeFormatter.ofPattern(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH);
            dob = startDate.format(storageFormatter);
        }
    }

    @Override
    public void onSelectionCancelled() {

    }

    private void setupViewData() {
        if (member == null) {
            return;
        }
        dob = member.getDob();
        updateProfileImage(member.getAvatar());
        binding.editTextUsername.setText(member.getMember_name());
        binding.textViewId.setText(member.getPrefix());
        binding.textViewWa.setText(FormatUtils.formatMsianPhone(member.getWhatsapp()));
        binding.textViewDob.setText(!StringUtil.isNullOrEmpty(dob)
                ? DateFormatUtils.formatIsoDate(dob, DateFormatUtils.FORMAT_DD_MM_YYYY)
                : getString(R.string.profile_choose_dob));
    }

    private void updateProfileImage(String url) {
        if (!StringUtil.isNullOrEmpty(url)) {
            Picasso.get().load(url).centerCrop().fit().into(binding.imageViewProfile);
        }
    }

    private void getProfile() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(ProfileDetailsActivity.this, CacheManager.KEY_USER_PROFILE, member);
                }
                setupViewData();
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

    private void getAvatarList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getAvatarList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                List<Avatar> avatarList = response.getImages();
                AvatarBottomSheetDialogFragment.newInstance(getString(R.string.profile_change_profile_pic), new ArrayList<>(avatarList), (avatar) -> {
                    selectedAvatar = avatar;
                    if (selectedAvatar != null) {
                        updateProfileImage(selectedAvatar.getUrl());
                    }
                }).show(getSupportFragmentManager(), "AvatarSheet");
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

    private void editProfile() {
        String memberName = binding.editTextUsername.getText();
        String url = selectedAvatar != null ? selectedAvatar.getUrl() : !StringUtil.isNullOrEmpty(member.getAvatar()) ? member.getAvatar() : "";

        boolean hasError = false;
        if (memberName.isEmpty()) {
            binding.editTextUsername.showError("");
            hasError = true;
        }
        if (dob == null || dob.isEmpty()) {
            showErrorTransparent(this, binding.panelDob);
            hasError = true;
        }
        if (hasError) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfileEdit request = new RequestProfileEdit(member.getMember_id(), memberName, member.getUid(), dob, url);
        executeApiCall(this, apiService.editProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                CustomToast.showTopToast(ProfileDetailsActivity.this, getString(R.string.profile_saved_successfully));
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(ProfileDetailsActivity.this, CacheManager.KEY_USER_PROFILE, member);
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