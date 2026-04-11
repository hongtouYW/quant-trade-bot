package com.dj.shop.activity.account;

import android.content.Intent;
import android.os.Bundle;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.auth.LoginActivity;
import com.dj.shop.activity.feedback.FeedbackFormActivity;
import com.dj.shop.activity.qr.FullScreenImageActivity;
import com.dj.shop.adapter.LanguageListViewAdapter;
import com.dj.shop.databinding.ActivityProfileBinding;
import com.dj.shop.model.ItemLanguage;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.VersionUtil;
import com.dj.shop.widget.CustomBottomSheetDialogFragment;
import com.dj.shop.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;

public class ProfileActivity extends BaseActivity {
    private ActivityProfileBinding binding;
    private Shop shop;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityProfileBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.profile_title), R.drawable.ic_qr_code, v -> startAppActivity(
                new Intent(ProfileActivity.this, FullScreenImageActivity.class),
                null, false, false, true
        ));
        setupProfileData();
        setupListeners();
        binding.textViewVersion.setText(String.format("V %s_%s", VersionUtil.getVersionName(this), VersionUtil.getVersionCode(this)));
    }

    private void setupProfileData() {
        if (shop != null && shop.getShop_name() != null) {
            binding.textViewUsername.setText(shop.getShop_name());
        } else {
            binding.textViewUsername.setText(R.string.profile_unknown_user);
        }
    }

    private void setupListeners() {
        binding.panelSettings.setOnClickListener(view -> startAppActivity(
                new Intent(ProfileActivity.this, SettingsActivity.class),
                null, false, false, true
        ));
        binding.panelFeedback.setOnClickListener(view -> startAppActivity(
                new Intent(ProfileActivity.this, FeedbackFormActivity.class),
                null, false, false, true
        ));
        String currentLanguage = getCurrentLanguage(this);
        binding.textViewLanguage.setText(getString(
                currentLanguage.equalsIgnoreCase("en") ? R.string.language_english :
                        currentLanguage.equalsIgnoreCase("ms") ? R.string.language_malay :
                                R.string.language_chinese)
        );
        ArrayList<ItemLanguage> languageList = new ArrayList<>();
        languageList.add(new ItemLanguage(1, getString(R.string.language_chinese), "zh"));
        languageList.add(new ItemLanguage(2, getString(R.string.language_english), "en"));
        languageList.add(new ItemLanguage(3, getString(R.string.language_malay), "ms"));
        for (ItemLanguage itemLanguage : languageList) {
            itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(currentLanguage));
        }
        LanguageListViewAdapter languageListViewAdapter = new LanguageListViewAdapter(this);
        binding.panelLanguage.setOnClickListener(view ->
                SelectionBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_language_setting),
                        languageList,
                        languageListViewAdapter,
                        (language, pos) -> {
                            setLocale(language.getCode());
                            for (ItemLanguage itemLanguage : languageList) {
                                itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(language.getCode()));
                            }
                        },
                        ItemLanguage.class).show(getSupportFragmentManager(), "LanguageSheet")
        );
        binding.panelLogout.setOnClickListener(view -> showLogoutConfirmation());
    }

    private void showLogoutConfirmation() {
        CustomBottomSheetDialogFragment bottomSheet =
                CustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_logout_title),
                        "",
                        getString(R.string.profile_logout_confirm),
                        getString(R.string.profile_logout_cancel),
                        true,
                        new CustomBottomSheetDialogFragment.OnActionListener() {
                            @Override
                            public void onPositiveClick() {
                                logout();
                            }

                            @Override
                            public void onNegativeClick() {
                            }
                        });
        bottomSheet.show(getSupportFragmentManager(), "LogoutBottomSheet");
    }

    protected void logout() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.logout(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CacheManager.clearAll(ProfileActivity.this);
                startAppActivity(new Intent(ProfileActivity.this, LoginActivity.class), null, true, true, true);
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