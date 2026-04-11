package com.dj.user.dj.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.databinding.DjFragmentProfileBinding;
import com.dj.user.dj.activity.DJDashboardActivity;
import com.dj.user.dj.activity.mine.DJProfileDetailsActivity;
import com.dj.user.dj.activity.mine.security.DJSecurityPrivacyActivity;
import com.dj.user.dj.activity.mine.setting.DJAppDownloadActivity;
import com.dj.user.dj.activity.mine.setting.DJContactUsActivity;
import com.dj.user.dj.activity.mine.setting.DJHelpActivity;
import com.dj.user.dj.activity.mine.setting.DJLanguageActivity;
import com.dj.user.dj.adapter.DJProfileSectionListViewAdapter;
import com.dj.user.dj.widget.DJCustomBottomSheetDialogFragment;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemSection;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.squareup.picasso.Picasso;

import java.util.ArrayList;
import java.util.List;

public class DJProfileFragment extends BaseFragment {

    private DjFragmentProfileBinding binding;
    private Context context;
    private Member member;

    public DJProfileFragment newInstance(Context context) {
        DJProfileFragment fragment = new DJProfileFragment();
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
        binding = DjFragmentProfileBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupSectionLists();
        setupViewData();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getProfile();
    }

    private void setupUI() {
        binding.imageViewProfile.setOnClickListener(view ->
                ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJProfileDetailsActivity.class),
                        null, false, false, true
                ));
        binding.textViewProfile.setOnClickListener(view ->
                ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJProfileDetailsActivity.class),
                        null, false, false, true
                ));
    }

    private void setupSectionLists() {
        List<ItemSection> generalSectionList = new ArrayList<>();
        generalSectionList.add(new ItemSection(1, R.drawable.ic_general_privacy, "安全隐私", ""));
        generalSectionList.add(new ItemSection(2, R.drawable.ic_contact_us, "找到我们", "防止打不开"));
        generalSectionList.add(new ItemSection(3, R.drawable.ic_general_app_download, "APP 下载", ""));
        generalSectionList.add(new ItemSection(4, R.drawable.ic_general_feedback, "APP 反馈", ""));
        generalSectionList.add(new ItemSection(5, R.drawable.ic_general_language, "语言", "简体中文"));
        generalSectionList.add(new ItemSection(6, R.drawable.ic_general_help, "帮助", ""));
        generalSectionList.add(new ItemSection(7, R.drawable.ic_general_logout, "登出", "切换账号"));

        DJProfileSectionListViewAdapter generalAdapter = new DJProfileSectionListViewAdapter(context);
        generalAdapter.addList(generalSectionList);
        binding.listViewSectionGeneral.setAdapter(generalAdapter);
        generalAdapter.setOnItemClickListener((position, object) -> {
            ItemSection clicked = (ItemSection) object;
            switch (clicked.getId()) {
                case 1:
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJSecurityPrivacyActivity.class),
                            null, false, false, true);
                    break;
                case 2:
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJContactUsActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJAppDownloadActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    Bundle bundle = new Bundle();
                    bundle.putInt("tab", 3);
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, NewsCentreActivity.class),
                            bundle, false, false, true);
                    break;
                case 5:
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJLanguageActivity.class),
                            null, false, false, true);
                    break;
                case 6:
                    ((DJDashboardActivity) context).startAppActivity(new Intent(context, DJHelpActivity.class),
                            null, false, false, true);
                    break;
                case 7:
                    showLogoutConfirmation();
                    break;
            }
        });
    }

    private void setupViewData() {
        if (member == null) {
            return;
        }
        updateProfileImage(member.getAvatar());
    }

    private void updateProfileImage(String url) {
        if (!StringUtil.isNullOrEmpty(url)) {
            Picasso.get().load(url).centerCrop().fit().into(binding.imageViewProfile);
        }
    }

    private void showLogoutConfirmation() {
        DJCustomBottomSheetDialogFragment bottomSheet =
                DJCustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.logout_title),
                        getString(R.string.logout_desc),
                        getString(R.string.logout_positive),
                        getString(R.string.logout_negative),
                        true,
                        new DJCustomBottomSheetDialogFragment.OnActionListener() {
                            @Override
                            public void onPositiveClick() {
                                logout();
                            }

                            @Override
                            public void onNegativeClick() {
                            }
                        });
        bottomSheet.show(((DJDashboardActivity) context).getSupportFragmentManager(), "CustomBottomSheet");
    }

    private void getProfile() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DJDashboardActivity) context).executeApiCall(context, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(context, CacheManager.KEY_USER_PROFILE, member);
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
        }, false);
    }

    private void logout() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        ((DJDashboardActivity) context).executeApiCall(context, apiService.logout(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CacheManager.clearAll(context);
                ((DJDashboardActivity) context).startAppActivity(new Intent(context, LoginActivity.class),
                        null, true, true, true);
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