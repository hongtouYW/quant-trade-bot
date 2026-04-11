package com.dj.manager.activity.profile;

import static com.dj.manager.R.string.language_chinese;
import static com.dj.manager.R.string.language_malay;

import android.content.Intent;
import android.os.Bundle;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.OnboardingActivity;
import com.dj.manager.activity.shop.ShopManagementActivity;
import com.dj.manager.adapter.LanguageListViewAdapter;
import com.dj.manager.databinding.ActivityProfileBinding;
import com.dj.manager.model.ItemLanguage;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.CustomBottomSheetDialogFragment;
import com.dj.manager.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;

public class ProfileActivity extends BaseActivity {
    private ActivityProfileBinding binding;
    private Manager manager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityProfileBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.profile_settings_title), 0, null);
        if (manager != null) {
            setupProfileData();
        }
        setupUI();
    }

    private void setupProfileData() {
        if (manager == null) {
            return;
        }
        binding.textViewUsername.setText(manager.getFull_name());
        binding.textViewId.setText(manager.getPrefix());
    }

    private void setupUI() {
        binding.panelShops.setOnClickListener(view ->
                startAppActivity(new Intent(this, ShopManagementActivity.class),
                        null, false, false, false, true
                ));
        binding.panelPassword.setOnClickListener(view ->
                startAppActivity(new Intent(this, ChangePasswordActivity.class),
                        null, false, false, false, true
                ));

        String currentLanguage = getCurrentLanguage(this);
        binding.textViewLanguage.setText(getString(
                currentLanguage.equalsIgnoreCase("en") ? R.string.language_english :
                        currentLanguage.equalsIgnoreCase("ms") ? language_malay :
                                language_chinese)
        );
        ArrayList<ItemLanguage> languageList = new ArrayList<>();
        languageList.add(new ItemLanguage(1, getString(language_chinese), "zh"));
        languageList.add(new ItemLanguage(2, getString(R.string.language_english), "en"));
        languageList.add(new ItemLanguage(3, getString(R.string.language_malay), "ms"));
        for (ItemLanguage itemLanguage : languageList) {
            itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(currentLanguage));
        }
        LanguageListViewAdapter languageListViewAdapter = new LanguageListViewAdapter(this);
        binding.panelLanguage.setOnClickListener(view ->
                SelectionBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_language_settings),
                        false,
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
        binding.buttonLogout.setTextColorRes(R.color.white_FFFFFF);
        binding.buttonLogout.setOnClickListener(view -> showLogoutConfirmation());
    }

    private void showLogoutConfirmation() {
        CustomBottomSheetDialogFragment bottomSheet =
                CustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_settings_logout_title),
                        "",
                        getString(R.string.profile_settings_logout_confirm),
                        getString(R.string.profile_settings_logout_cancel),
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
        bottomSheet.show(getSupportFragmentManager(), "CustomBottomSheet");
    }

    private void logout() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.logout(), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                CacheManager.clearAll(ProfileActivity.this);
                startAppActivity(new Intent(ProfileActivity.this, OnboardingActivity.class),
                        null, true, true, false, true);
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