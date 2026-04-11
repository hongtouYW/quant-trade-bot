package com.dj.user.fragment.dashboard;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Shader;
import android.os.Bundle;
import android.text.TextPaint;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.LinearInterpolator;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.activity.DashboardActivity;
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.activity.mine.ProfileDetailsActivity;
import com.dj.user.activity.mine.VIPActivity;
import com.dj.user.activity.mine.affiliate.AffiliateActivity;
import com.dj.user.activity.mine.bank.BankListActivity;
import com.dj.user.activity.mine.security.SecurityPrivacyActivity;
import com.dj.user.activity.mine.setting.AppDownloadActivity;
import com.dj.user.activity.mine.setting.ContactUsActivity;
import com.dj.user.activity.mine.setting.HelpActivity;
import com.dj.user.activity.mine.setting.LanguageActivity;
import com.dj.user.activity.mine.topup.TopUpActivity;
import com.dj.user.activity.mine.transaction.TransactionActivity;
import com.dj.user.activity.mine.withdraw.WithdrawActivity;
import com.dj.user.activity.mine.yxi.PlayerTransferActivity;
import com.dj.user.adapter.ProfileActionGridItemAdapter;
import com.dj.user.adapter.ProfileSectionListViewAdapter;
import com.dj.user.databinding.FragmentProfileBinding;
import com.dj.user.enums.Language;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.ItemGrid;
import com.dj.user.model.ItemSection;
import com.dj.user.model.request.RequestPlayerTransferAll;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Notification;
import com.dj.user.model.response.Slider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.widget.CustomConfirmationDialog;

import java.util.ArrayList;
import java.util.List;

public class ProfileFragment extends BaseFragment {

    private FragmentProfileBinding binding;
    private Context context;
    private Member member;
    private ObjectAnimator refreshAnimator;
    private int unreadNotification = 0;
    private int unreadSlider = 0;

    public ProfileFragment newInstance(Context context) {
        ProfileFragment fragment = new ProfileFragment();
        fragment.context = context;
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context context) {
        super.onAttach(context);
        if (this.context == null) {
            PackageManager packageManager = context.getPackageManager();
            Intent intent = packageManager.getLaunchIntentForPackage(context.getPackageName());
            ComponentName componentName = intent.getComponent();
            Intent mainIntent = Intent.makeRestartActivityTask(componentName);
            context.startActivity(mainIntent);
            Runtime.getRuntime().exit(0);
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentProfileBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupActionGrid();
        setupSectionLists();
        setupViewData();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (member == null) {
            CacheManager.clearAll(context);
            ((DashboardActivity) context).startAppActivity(new Intent(context, LoginActivity.class),
                    null, true, true, true);
            return;
        }
        getProfile();
        getNotificationList();
    }

    private void setupUI() {
//        binding.imageViewProfile.setOnClickListener(view ->
//                ((DashboardActivity) context).startAppActivity(new Intent(context, ProfileDetailsActivity.class),
//                        null, false, false, true
//                ));
//        binding.textViewProfile.setOnClickListener(view ->
//                ((DashboardActivity) context).startAppActivity(new Intent(context, ProfileDetailsActivity.class),
//                        null, false, false, true
//                ));
        binding.imageViewNotification.setOnClickListener(view ->
                ((DashboardActivity) context).startAppActivity(new Intent(context, NewsCentreActivity.class),
                        null, false, false, true
                ));
        binding.textViewCurrency.setText(getString(R.string.placeholder_currency_myr));
        binding.textViewCurrency.post(() -> {
            TextPaint paint = binding.textViewCurrency.getPaint();
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewCurrency.getTextSize(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewCurrency.invalidate();
        });
        binding.textViewRefresh.post(() -> {
            TextPaint paint = binding.textViewRefresh.getPaint();
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewRefresh.getTextSize(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewRefresh.invalidate();
        });
        binding.panelBalance.setOnClickListener(view -> oneClickTransferYxiCredit());
    }

    private void setupActionGrid() {
        List<ItemGrid> actionItems = List.of(
                new ItemGrid(1, R.drawable.ic_profile_top_up, getString(R.string.action_panel_top_up), ""),
                new ItemGrid(2, R.drawable.ic_profile_transaction, getString(R.string.action_panel_transaction), ""),
                new ItemGrid(3, R.drawable.ic_profile_withdrawal, getString(R.string.action_panel_withdraw), ""),
                new ItemGrid(4, R.drawable.ic_profile_wallet, getString(R.string.action_panel_wallet), ""),
                new ItemGrid(5, R.drawable.ic_profile_vip, getString(R.string.action_panel_vip), ""),
                new ItemGrid(6, R.drawable.ic_profile_promotion, getString(R.string.action_panel_promotion), ""),
                new ItemGrid(7, R.drawable.ic_profile_bank, getString(R.string.action_panel_bank), ""),
                new ItemGrid(8, R.drawable.ic_profile_affiliate, getString(R.string.action_panel_affiliate), "")
        );

        ProfileActionGridItemAdapter adapter = new ProfileActionGridItemAdapter(context, actionItems);
        binding.gridViewProfileAction.setAdapter(adapter);

        binding.gridViewProfileAction.setOnItemClickListener((parent, view, position, id) -> {
            ItemGrid clicked = actionItems.get(position);
            switch (clicked.getId()) {
                case 1:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, TopUpActivity.class),
                            null, false, false, true);
                    break;
                case 2:
//                    Bundle bundle = new Bundle();
//                    bundle.putInt("tab", 1);
                    ((DashboardActivity) context).startAppActivity(new Intent(context, TransactionActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, WithdrawActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, PlayerTransferActivity.class),
                            null, false, false, true);
                    break;
                case 5:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, VIPActivity.class),
                            null, false, false, true);
                    break;
                case 6:
                    ((DashboardActivity) context).switchTab(DashboardActivity.PAGE_PROMOTION, 0);
                    break;
                case 7:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, BankListActivity.class),
                            null, false, false, true);
                    break;
                case 8:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, AffiliateActivity.class),
                            null, false, false, true);
                    break;
            }
        });
    }

    private void setupSectionLists() {
        String languageCode = ((DashboardActivity) context).getCurrentLanguage(context);
        Language language = Language.fromValue(languageCode);

        List<ItemSection> generalSectionList = new ArrayList<>();
        generalSectionList.add(new ItemSection(0, R.drawable.ic_general_profile, getString(R.string.me_profile), ""));
        generalSectionList.add(new ItemSection(1, R.drawable.ic_general_privacy, getString(R.string.me_action_privacy_security), ""));
        generalSectionList.add(new ItemSection(2, R.drawable.ic_contact_us, getString(R.string.me_action_find_us), getString(R.string.me_action_find_us_desc)));
        generalSectionList.add(new ItemSection(3, R.drawable.ic_general_app_download, getString(R.string.me_action_app_download), ""));
//        generalSectionList.add(new ItemSection(4, R.drawable.ic_general_feedback, getString(R.string.me_action_app_feedback), ""));
        generalSectionList.add(new ItemSection(5, R.drawable.ic_general_language, getString(R.string.me_action_language), language.getTitle()));
        generalSectionList.add(new ItemSection(6, R.drawable.ic_general_help, getString(R.string.me_action_help), ""));
        generalSectionList.add(new ItemSection(7, R.drawable.ic_general_logout, getString(R.string.me_action_logout), getString(R.string.me_action_logout_desc)));

        ProfileSectionListViewAdapter generalAdapter = new ProfileSectionListViewAdapter(context);
        generalAdapter.addList(generalSectionList);
        binding.listViewSectionGeneral.setAdapter(generalAdapter);
        generalAdapter.setOnItemClickListener((position, object) -> {
            ItemSection clicked = (ItemSection) object;
            switch (clicked.getId()) {
                case 0:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, ProfileDetailsActivity.class),
                            null, false, false, true);
                    break;
                case 1:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, SecurityPrivacyActivity.class),
                            null, false, false, true);
                    break;
                case 2:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, ContactUsActivity.class),
                            null, false, false, true);
                    break;
                case 3:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, AppDownloadActivity.class),
                            null, false, false, true);
                    break;
                case 4:
                    Bundle bundle = new Bundle();
                    bundle.putInt("tab", 3);
                    ((DashboardActivity) context).startAppActivity(new Intent(context, NewsCentreActivity.class),
                            bundle, false, false, true);
                    break;
                case 5:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, LanguageActivity.class),
                            null, false, false, true);
                    break;
                case 6:
                    ((DashboardActivity) context).startAppActivity(new Intent(context, HelpActivity.class),
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
//        updateProfileImage(member.getAvatar());
        binding.textViewUsername.setText(FormatUtils.formatMsianPhone(member.getMember_login()));
        binding.textViewUsername.setSelected(true);
        String vipName = "vip_" + member.getVip();
        int resId = getResources().getIdentifier(vipName, "drawable", context.getPackageName());
        binding.imageViewVip.setImageResource(resId);

        binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
        binding.textViewBalance.post(() -> {
            TextPaint paint = binding.textViewBalance.getPaint();
//            float width = paint.measureText(binding.textViewBalance.getText().toString());
            Shader shader = new LinearGradient(
                    0, 0, 0, binding.textViewBalance.getMeasuredHeight(),
                    Color.parseColor("#F8AF07"),
                    Color.parseColor("#FFFC86"),
                    Shader.TileMode.CLAMP
            );
            paint.setShader(shader);
            binding.textViewBalance.invalidate();
        });
    }

//    private void updateProfileImage(String url) {
//        if (!StringUtil.isNullOrEmpty(url)) {
//            Picasso.get().load(url).centerCrop().fit().into(binding.imageViewProfile);
//        }
//    }

    private void showLogoutConfirmation() {
        ((DashboardActivity) context).showCustomConfirmationDialog(
                context,
                getString(R.string.logout_title),
                getString(R.string.logout_desc),
                "",
                getString(R.string.logout_negative),
                getString(R.string.logout_positive),
                new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                        logout();
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
    }

    private void startRefreshAnimation() {
        if (refreshAnimator == null) {
            refreshAnimator = ObjectAnimator.ofFloat(binding.imageViewRefresh, "rotation", 0f, 360f);
            refreshAnimator.setDuration(300);
            refreshAnimator.setRepeatCount(ValueAnimator.INFINITE);
            refreshAnimator.setInterpolator(new LinearInterpolator());
        }
        refreshAnimator.start();
    }

    private void stopRefreshAnimation() {
        if (refreshAnimator != null && refreshAnimator.isRunning()) {
            refreshAnimator.cancel();
            binding.imageViewRefresh.setRotation(0f);
        }
    }

    private void getProfile() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(context, CacheManager.KEY_USER_PROFILE, member);
                }
                setupViewData();
                ((DashboardActivity) context).updateProfileImage();
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return true;
            }
        }, false);
    }

    private void oneClickTransferYxiCredit() {
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestPlayerTransferAll request = new RequestPlayerTransferAll(member.getMember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "oneClickTransferYxiCredit IP: " + publicIp);
            ((DashboardActivity) context).executeApiCall(context, apiService.oneClickTransferYxiCredit(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    getProfile();
                }

                @Override
                public boolean onApiError(int code, String message) {
                    stopRefreshAnimation();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    stopRefreshAnimation();
                    return true;
                }
            }, false);
        });
    }

    private void updateNotificationIcon() {
        int totalUnread = unreadNotification + unreadSlider;
        binding.textViewBadge.setText(totalUnread > 99 ? "99+" : String.valueOf(totalUnread));
        binding.textViewBadge.setVisibility(totalUnread > 0 ? View.VISIBLE : View.GONE);
//        binding.imageViewNotification.setImageResource(
//                hasUnread
//                        ? R.drawable.ic_notification_circled_unread
//                        : R.drawable.ic_notification_circled
//        );
    }

    private void getNotificationList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getNotificationList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Notification>> response) {
                List<Notification> notificationList = response.getData();
                unreadNotification = 0;

                if (notificationList != null) {
                    for (Notification n : notificationList) {
                        if (!n.getIs_read()) {
                            unreadNotification += 1;
                        }
                    }
                }
                getSliderList();
            }

            @Override
            public boolean onApiError(int code, String message) {
                unreadNotification = 0;
                getSliderList();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                unreadNotification = 0;
                getSliderList();
                return false;
            }
        }, false);
    }

    private void getSliderList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((DashboardActivity) context).executeApiCall(context, apiService.getSliderList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Slider>> response) {
                List<Slider> sliderList = response.getData();
                unreadSlider = 0;
                if (sliderList != null) {
                    for (Slider s : sliderList) {
                        if (!s.isRead()) {
                            unreadSlider += 1;
                        }
                    }
                }
                updateNotificationIcon();
            }

            @Override
            public boolean onApiError(int code, String message) {
                unreadSlider = 0;
                updateNotificationIcon();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                unreadSlider = 0;
                updateNotificationIcon();
                return false;
            }
        }, false);
    }

    private void logout() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        ((DashboardActivity) context).executeApiCall(context, apiService.logout(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CacheManager.clearAll(context);
                ((DashboardActivity) context).startAppActivity(new Intent(context, LoginActivity.class),
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