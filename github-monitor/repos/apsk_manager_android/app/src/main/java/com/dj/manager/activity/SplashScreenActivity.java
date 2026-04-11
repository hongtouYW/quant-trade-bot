package com.dj.manager.activity;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

import com.dj.manager.R;
import com.dj.manager.activity.dashboard.DashboardActivity;
import com.dj.manager.databinding.ActivitySplashScreenBinding;
import com.dj.manager.model.request.RequestVersion;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Version;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.InAppUpdater;
import com.dj.manager.util.StringUtil;
import com.dj.manager.util.VersionUtil;
import com.dj.manager.widget.CustomBottomSheetDialogFragment;

@SuppressLint("CustomSplashScreen")
public class SplashScreenActivity extends BaseActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ActivitySplashScreenBinding binding = ActivitySplashScreenBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        hideSystemUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        InAppUpdater.cleanupIfNeeded(this);
        String pendingUrl = InAppUpdater.consumePendingUrl(this);
        if (pendingUrl != null) {
            new InAppUpdater(this).startUpdate(pendingUrl);
            return;
        }
        checkAppVersion();
    }

    private void checkAppVersion() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.checkVersion(new RequestVersion()), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Version> response) {
                Version version = response.getData();
                if (version != null && version.getLatest_version() != null && version.getMinimun_version() != null) {
                    if (VersionUtil.isVersionLower(SplashScreenActivity.this, version.getMinimun_version()) ||
                            (VersionUtil.isVersionLower(SplashScreenActivity.this, version.getLatest_version()) && version.getForce_update() == 1)) {
                        // Force update required
                        showUpdateDialog(true, version.getLatest_version(), version.getChangelog(), version.getUrl());
                    } else if (VersionUtil.isVersionLower(SplashScreenActivity.this, version.getLatest_version())) {
                        // Suggest update (optional)
                        showUpdateDialog(false, version.getLatest_version(), version.getChangelog(), version.getUrl());
                    } else {
                        // No update needed
                        proceedToNextScreen();
                    }
                } else {
                    // Invalid response, proceed as normal
                    proceedToNextScreen();
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

    private boolean isLoggedIn() {
        String token = CacheManager.getString(this, CacheManager.KEY_ACCESS_TOKEN);
        return token != null && !token.isEmpty();
    }

    private void proceedToNextScreen() {
        if (isLoggedIn()) {
            startAppActivity(
                    new Intent(SplashScreenActivity.this, DashboardActivity.class),
                    null, true, true, false, true
            );
        } else {
            startAppActivity(
                    new Intent(SplashScreenActivity.this, OnboardingActivity.class),
                    null, true, true, false, true
            );
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
                                    new InAppUpdater(SplashScreenActivity.this).startUpdate(url);
//                                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
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
                                if (!forceUpdate) {
                                    proceedToNextScreen();
                                }
                            }
                        });
        bottomSheet.show(getSupportFragmentManager(), "CustomBottomSheet");
    }

    private void hideSystemUI() {
        getWindow().getDecorView().setSystemUiVisibility(
                android.view.View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                        | android.view.View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                        | android.view.View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                        | android.view.View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                        | android.view.View.SYSTEM_UI_FLAG_FULLSCREEN
                        | android.view.View.SYSTEM_UI_FLAG_HIDE_NAVIGATION);
    }
}