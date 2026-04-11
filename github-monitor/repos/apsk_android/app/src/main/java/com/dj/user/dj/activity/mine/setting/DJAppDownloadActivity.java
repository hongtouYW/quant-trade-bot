package com.dj.user.dj.activity.mine.setting;

import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.FullScreenImageActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.databinding.DjActivityAppDownloadBinding;
import com.dj.user.dj.widget.DJCustomBottomSheetDialogFragment;
import com.dj.user.model.request.RequestVersion;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Version;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.util.VersionUtil;
import com.dj.user.widget.CustomToast;

import java.util.List;

public class DJAppDownloadActivity extends BaseActivity {

    private DjActivityAppDownloadBinding binding;
    private String iosUrl;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityAppDownloadBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.app_download_title), 0, null);
        setupUI();
        getVersionList();
    }

    private void setupUI() {
        binding.textViewVersion.setText(String.format(getString(R.string.app_download_current_version), VersionUtil.getVersionName(this), VersionUtil.getVersionCode(this)));
        binding.imageViewApple.setOnClickListener(view -> {
            if (!StringUtil.isNullOrEmpty(iosUrl)) {
                startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(iosUrl)));
            } else {
                Toast.makeText(DJAppDownloadActivity.this, "URL not available", Toast.LENGTH_SHORT).show();
            }
        });
        binding.buttonCheck.setOnClickListener(view -> checkAppVersion());
        binding.textViewFeedback.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putInt("tab", 3);
            startAppActivity(new Intent(this, NewsCentreActivity.class),
                    bundle, true, false, true);
        });
    }

    private void generateQRCode(String url) {
        Bitmap bitmap = ImageUtils.generateQRCode(this, url);
        if (bitmap != null) {
            binding.imageViewQr.setImageBitmap(bitmap);
            binding.imageViewQr.setOnClickListener(v -> {
                Bundle bundle = new Bundle();
                bundle.putString("data", url);
                startAppActivity(new Intent(DJAppDownloadActivity.this, FullScreenImageActivity.class),
                        bundle, false, false, true);
            });
        }
        binding.imageViewGoogle.setOnClickListener(v -> startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url))));
    }

    private void showUpdateConfirmation(boolean forceUpdate, String latestVersion, String changeLog, String url) {
        DJCustomBottomSheetDialogFragment bottomSheet =
                DJCustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.app_download_upgrade_title),
                        !StringUtil.isNullOrEmpty(changeLog) ?
                                changeLog :
                                String.format(getString(R.string.app_download_upgrade_desc), VersionUtil.getVersionName(this), latestVersion),
                        getString(R.string.app_download_upgrade_install),
                        forceUpdate ? null : getString(R.string.app_download_upgrade_skip),
                        StringUtil.isNullOrEmpty(changeLog),
                        new DJCustomBottomSheetDialogFragment.OnActionListener() {
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

    private void getVersionList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getVersionList(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Version>> response) {
                Version android = null;
                List<Version> versionList = response.getData();
                for (Version version : versionList) {
                    if (version.getPlatform().equalsIgnoreCase("android")) {
                        android = version;
                    } else if (version.getPlatform().equalsIgnoreCase("ios")) {
                        iosUrl = version.getUrl();
                    }
                }
                if (android != null) {
                    String url = android.getUrl();
                    String appPackageName = getPackageName();
                    String playStoreUrl = "https://play.google.com/store/apps/details?id=" + appPackageName;
                    String targetUrl = (url != null && !url.isEmpty()) ? url : playStoreUrl;
                    generateQRCode(targetUrl);
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

    private void checkAppVersion() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.checkVersion(new RequestVersion()), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Version> response) {
                Version android = response.getData();
                if (android != null && android.getLatest_version() != null && android.getMinimun_version() != null) {
                    if (VersionUtil.isVersionLower(DJAppDownloadActivity.this, android.getMinimun_version()) ||
                            (VersionUtil.isVersionLower(DJAppDownloadActivity.this, android.getLatest_version()) && android.getForce_update() == 1)) {
                        // Force update required
                        showUpdateConfirmation(true, android.getLatest_version(), android.getChangelog(), android.getUrl());
                    } else if (VersionUtil.isVersionLower(DJAppDownloadActivity.this, android.getLatest_version())) {
                        // Suggest update (optional)
                        showUpdateConfirmation(false, android.getLatest_version(), android.getChangelog(), android.getUrl());
                    } else {
                        // No update needed
                        CustomToast.showTopToast(DJAppDownloadActivity.this, getString(R.string.app_download_already_latest));
                    }
                } else {
                    // Invalid response, proceed as normal
                    CustomToast.showTopToast(DJAppDownloadActivity.this, getString(R.string.app_download_already_latest));
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