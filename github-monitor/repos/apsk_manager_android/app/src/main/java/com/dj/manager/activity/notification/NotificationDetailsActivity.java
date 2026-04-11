package com.dj.manager.activity.notification;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.shop.ShopDetailsActivity;
import com.dj.manager.databinding.ActivityNotificationDetailsBinding;
import com.dj.manager.enums.NotificationType;
import com.dj.manager.model.request.RequestNotificationRead;
import com.dj.manager.model.request.RequestVersion;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Notification;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.Version;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.StringUtil;
import com.dj.manager.util.VersionUtil;
import com.dj.manager.widget.CustomToast;
import com.dj.manager.widget.CustomBottomSheetDialogFragment;
import com.google.gson.Gson;

public class NotificationDetailsActivity extends BaseActivity {
    private ActivityNotificationDetailsBinding binding;
    private Manager manager;
    private Notification notification;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityNotificationDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), "", 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (notification != null && !notification.getIs_read()) {
            markNotificationRead();
        }
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        notification = new Gson().fromJson(json, Notification.class);
    }

    private void setupUI() {
        if (notification == null) {
            return;
        }
        binding.textViewSubject.setText(notification.getTitle());
        binding.textViewDate.setText(DateFormatUtils.formatIsoDate(notification.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_A));
        binding.textViewMessage.setText(notification.getNotification_desc());

        NotificationType notificationType = notification.getNotificationType();
        if (notificationType == NotificationType.VERSION) {
            binding.textViewVersion.setText(String.format(getString(R.string.notification_details_current_version), VersionUtil.getVersionName(this), VersionUtil.getVersionCode(this)));
            binding.textViewVersion.setVisibility(View.VISIBLE);
            binding.buttonCta.setText(notificationType.getTitle());
            binding.buttonCta.setVisibility(View.VISIBLE);
            binding.buttonCta.setOnClickListener(view -> checkAppVersion());

        } else if (notificationType == NotificationType.ALERT) {
            binding.textViewVersion.setVisibility(View.GONE);
            binding.buttonCta.setText(notificationType.getTitle());
            binding.buttonCta.setVisibility(View.VISIBLE);
            binding.buttonCta.setOnClickListener(view -> {
                // TODO: 02/09/2025  
            });

        } else if (notificationType == NotificationType.SHOP) {
            binding.textViewVersion.setVisibility(View.GONE);
            binding.buttonCta.setText(notificationType.getTitle());
            binding.buttonCta.setVisibility(View.VISIBLE);
            binding.buttonCta.setOnClickListener(view -> {
                Bundle bundle = new Bundle();
                Shop shop = new Shop(notification.getSender_id());
                bundle.putString("data", new Gson().toJson(shop));
                startAppActivity(new Intent(NotificationDetailsActivity.this, ShopDetailsActivity.class),
                        bundle, false, false, false, true);
            });
        } else {
            binding.textViewVersion.setVisibility(View.GONE);
            binding.buttonCta.setVisibility(View.GONE);
        }
    }

    private void showUpdateDialog(boolean forceUpdate, String latestVersion, String changeLog, String url) {
        CustomBottomSheetDialogFragment bottomSheet =
                CustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.splash_version_title),
                        !StringUtil.isNullOrEmpty(changeLog) ?
                                changeLog :
                                String.format(getString(R.string.splash_version_desc), VersionUtil.getVersionName(this), latestVersion),
                        getString(R.string.splash_version_confirm),
                        forceUpdate ? null : getString(R.string.splash_version_cancel),
                        StringUtil.isNullOrEmpty(changeLog),
                        new CustomBottomSheetDialogFragment.OnActionListener() {
                            @Override
                            public void onPositiveClick() {
                                if (url != null && !url.isEmpty()) {
                                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                                } else {
                                    // Fallback to Play Store
                                    final String appPackageName = getPackageName();
                                    try {
                                        startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse("market://details?id=" + appPackageName)));
                                    } catch (android.content.ActivityNotFoundException e) {
                                        startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse("https://play.google.com/store/apps/details?id=" + appPackageName)));
                                    }
                                }
                            }

                            @Override
                            public void onNegativeClick() {
                            }
                        });
        bottomSheet.show(getSupportFragmentManager(), "CustomBottomSheet");
    }

    private void markNotificationRead() {
        if (notification == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestNotificationRead request = new RequestNotificationRead(manager.getManager_id(), notification.getNotification_id());
        executeApiCall(this, apiService.markNotificationRead(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Notification> response) {
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

    private void checkAppVersion() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.checkVersion(new RequestVersion()), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Version> response) {
                Version version = response.getData();
                if (version != null && version.getLatest_version() != null && version.getMinimun_version() != null) {
                    if (VersionUtil.isVersionLower(NotificationDetailsActivity.this, version.getMinimun_version()) ||
                            (VersionUtil.isVersionLower(NotificationDetailsActivity.this, version.getLatest_version()) && version.getForce_update() == 1)) {
                        // Force update required
                        showUpdateDialog(true, version.getLatest_version(), version.getChangelog(), version.getUrl());
                    } else if (VersionUtil.isVersionLower(NotificationDetailsActivity.this, version.getLatest_version())) {
                        // Suggest update (optional)
                        showUpdateDialog(false, version.getLatest_version(), version.getChangelog(), version.getUrl());
                    } else {
                        CustomToast.showTopToast(NotificationDetailsActivity.this, getString(R.string.notification_details_latest_version));
                    }
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