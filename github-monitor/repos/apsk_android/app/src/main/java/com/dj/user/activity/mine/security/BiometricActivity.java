package com.dj.user.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;
import android.provider.Settings;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.biometric.BiometricManager;
import androidx.biometric.BiometricPrompt;
import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.BiometricListViewAdapter;
import com.dj.user.databinding.ActivityBiometricBinding;
import com.dj.user.enums.BiometricStatus;
import com.dj.user.model.ItemBiometric;
import com.dj.user.util.CacheManager;
import com.dj.user.widget.CustomToast;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.Executor;

public class BiometricActivity extends BaseActivity {

    private ActivityBiometricBinding binding;
    private BiometricListViewAdapter adapter;
    private List<ItemBiometric> biometricList = new ArrayList<>();
    private boolean isAuthenticating = false; // Flag to prevent multiple authentication attempts
    private static final int BIOMETRIC_ID = 1; // Single biometric toggle

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBiometricBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.biometric_title), 0, null);
        initAdapter();
        setupActionList();
    }

    @Override
    protected void onResume() {
        super.onResume();
        // Refresh the list when returning from settings
        setupActionList();
    }

    private void initAdapter() {
        adapter = new BiometricListViewAdapter(this);
        binding.listViewBiometric.setAdapter(adapter);
        adapter.setOnItemClickListener((position, object) -> {
        });
    }

    private void setupActionList() {
        boolean biometricAvailable = isBiometricAvailable();
        if (!biometricAvailable) {
            CacheManager.saveBoolean(this, CacheManager.KEY_BIOMETRIC_ENABLED, false);
        }
        boolean biometricEnabled = CacheManager.getBoolean(this, CacheManager.KEY_BIOMETRIC_ENABLED);

        biometricList.clear();
        biometricList.add(new ItemBiometric(1, R.drawable.ic_security_biometric, getString(R.string.biometric_identification), biometricAvailable, biometricEnabled));
        adapter.replaceList(biometricList);
    }

    public BiometricStatus getBiometricStatus() {
        BiometricManager biometricManager = BiometricManager.from(this);
        int canAuth = biometricManager.canAuthenticate(
                BiometricManager.Authenticators.BIOMETRIC_STRONG |
                        BiometricManager.Authenticators.BIOMETRIC_WEAK
        );
        switch (canAuth) {
            case BiometricManager.BIOMETRIC_SUCCESS:
                return BiometricStatus.SUCCESS;
            case BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE:
                return BiometricStatus.NO_HARDWARE;
            case BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE:
                return BiometricStatus.HW_UNAVAILABLE;
            case BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED:
                return BiometricStatus.NONE_ENROLLED;
            default:
                return BiometricStatus.HW_UNAVAILABLE; // fallback
        }
    }

    private boolean isBiometricAvailable() {
        BiometricManager biometricManager = BiometricManager.from(this);
        int canAuth = biometricManager.canAuthenticate(
                BiometricManager.Authenticators.BIOMETRIC_STRONG |
                        BiometricManager.Authenticators.BIOMETRIC_WEAK
        );
        switch (canAuth) {
            case BiometricManager.BIOMETRIC_SUCCESS:
                return true;
            case BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE:
                Toast.makeText(this, R.string.biometric_not_supported, Toast.LENGTH_SHORT).show();
                break;
            case BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED:
                Toast.makeText(this, R.string.biometric_not_setup, Toast.LENGTH_SHORT).show();
                break;
            case BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE:
                Toast.makeText(this, R.string.biometric_unavailable, Toast.LENGTH_SHORT).show();
                break;
            case BiometricManager.BIOMETRIC_ERROR_SECURITY_UPDATE_REQUIRED:
                break;
            case BiometricManager.BIOMETRIC_ERROR_UNSUPPORTED:
                break;
            case BiometricManager.BIOMETRIC_STATUS_UNKNOWN:
                break;
        }
        return false;
    }

    public void startBiometricPrompt(ItemBiometric biometric) {
        if (isAuthenticating) {
            return;
        }
        isAuthenticating = true;
        Executor executor = ContextCompat.getMainExecutor(this);
        BiometricPrompt biometricPrompt = new BiometricPrompt(this, executor,
                new BiometricPrompt.AuthenticationCallback() {
                    @Override
                    public void onAuthenticationSucceeded(@NonNull BiometricPrompt.AuthenticationResult result) {
                        super.onAuthenticationSucceeded(result);
                        runOnUiThread(() -> {
                            isAuthenticating = false;
                            CustomToast.showTopToast(BiometricActivity.this, getString(R.string.biometric_success_enabled));
                            setBiometricEnabled(biometric.getId(), true);
                        });
                    }

                    @Override
                    public void onAuthenticationFailed() {
                        super.onAuthenticationFailed();
                        runOnUiThread(() -> {
                            isAuthenticating = false;
                            Toast.makeText(BiometricActivity.this, R.string.biometric_failed, Toast.LENGTH_SHORT).show();
                        });
                    }

                    @Override
                    public void onAuthenticationError(int errorCode, @NonNull CharSequence errString) {
                        super.onAuthenticationError(errorCode, errString);
                        runOnUiThread(() -> {
                            isAuthenticating = false;
                            if (errorCode == BiometricPrompt.ERROR_NO_BIOMETRICS) {
                                Toast.makeText(BiometricActivity.this, getString(R.string.biometric_not_setup), Toast.LENGTH_LONG).show();
                                startActivity(new Intent(Settings.ACTION_SECURITY_SETTINGS));
                            } else if (errorCode != BiometricPrompt.ERROR_USER_CANCELED) {
                                Toast.makeText(BiometricActivity.this, String.format(getString(R.string.template_s_space_s), getString(R.string.biometric_failed), errString), Toast.LENGTH_SHORT).show();
                            }
                        });
                    }
                });

        BiometricPrompt.PromptInfo promptInfo = new BiometricPrompt.PromptInfo.Builder()
                .setTitle(getString(R.string.biometric_prompt_title))
                .setSubtitle(getString(R.string.biometric_prompt_desc))
                .setNegativeButtonText(getString(R.string.biometric_prompt_cancel))
                .setConfirmationRequired(true)
                .setAllowedAuthenticators(
                        BiometricManager.Authenticators.BIOMETRIC_STRONG |
                                BiometricManager.Authenticators.BIOMETRIC_WEAK
                )
                .build();

        try {
            biometricPrompt.authenticate(promptInfo);
        } catch (Exception e) {
            isAuthenticating = false;
            Toast.makeText(this, String.format(getString(R.string.template_s_space_s), getString(R.string.biometric_prompt_failed), e.getMessage()), Toast.LENGTH_SHORT).show();
        }
    }

    public void setBiometricEnabled(int biometricId, boolean enabled) {
        if (biometricId == BIOMETRIC_ID) {
            CacheManager.saveBoolean(this, CacheManager.KEY_BIOMETRIC_ENABLED, enabled);
        }
        // Refresh the list to update the UI
        setupActionList();
    }
}