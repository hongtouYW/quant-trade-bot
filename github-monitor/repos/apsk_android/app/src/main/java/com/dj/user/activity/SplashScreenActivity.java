package com.dj.user.activity;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;

import com.dj.user.R;
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.model.request.RequestVersion;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Country;
import com.dj.user.model.response.Version;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.InAppUpdater;
import com.dj.user.util.NetworkUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.util.VersionUtil;
import com.dj.user.widget.CustomConfirmationDialog;

import java.util.List;

@SuppressLint("CustomSplashScreen")
public class SplashScreenActivity extends BaseActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_splash_screen);
        hideSystemUI();
        NetworkUtils.fetchPublicIPv4(ipAddress -> Log.d("###", String.format("onCreate: %s", ipAddress)));
        getCountryList();
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

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getCountryList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                List<Country> countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase("mys"));
                }
                CacheManager.saveObject(SplashScreenActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
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
        }, true);
    }

    private boolean isLoggedIn() {
        String token = CacheManager.getString(this, CacheManager.KEY_ACCESS_TOKEN);
        return token != null && !token.isEmpty();
    }

    private boolean isNeedBinding() {
        return CacheManager.getBoolean(this, CacheManager.KEY_NEED_BINDING);
    }

    private void proceedToNextScreen() {
        if (isLoggedIn() && !isNeedBinding()) {
            startAppActivity(
                    new Intent(SplashScreenActivity.this, DashboardActivity.class),
                    null, true, true, true
            );
        } else {
            CacheManager.saveBoolean(this, CacheManager.KEY_NEED_BINDING, false);
            startAppActivity(
                    new Intent(SplashScreenActivity.this, LoginActivity.class),
                    null, true, true, true
            );
        }
    }

    private void showUpdateDialog(boolean forceUpdate, String latestVersion, String changeLog, String url) {
        showCustomConfirmationDialog(
                this,
                getString(R.string.splash_update_title),
                !StringUtil.isNullOrEmpty(changeLog) ?
                        changeLog :
                        String.format(getString(R.string.splash_update_desc), VersionUtil.getVersionName(this), latestVersion),
                "",
                forceUpdate ? null : getString(R.string.splash_update_negative),
                getString(R.string.splash_update_positive),
                StringUtil.isNullOrEmpty(changeLog),
                new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                        if (url != null && !url.isEmpty()) {
                            new InAppUpdater(SplashScreenActivity.this).startUpdate(url);
//                            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
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
                    public void onNegativeButtonClicked() {
                        if (!forceUpdate) {
                            proceedToNextScreen();
                        }
                    }
                }
        );
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