package com.dj.manager.activity.yxi;

import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.View;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityYxiPlayerDetailsBinding;
import com.dj.manager.enums.PlayerStatus;
import com.dj.manager.model.request.RequestPlayerDelete;
import com.dj.manager.model.request.RequestPlayerPassword;
import com.dj.manager.model.request.RequestPlayerProfile;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Player;
import com.dj.manager.model.response.YxiProvider;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.StringUtil;
import com.dj.manager.widget.CustomBottomSheetDialogFragment;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class YxiPlayerDetailsActivity extends BaseActivity {
    private ActivityYxiPlayerDetailsBinding binding;
    private boolean isPasswordVisible = false;
    private Manager manager;
    private Player player;
    private YxiProvider yxiProvider;
    private String password;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiPlayerDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupUI();
        setupPlayerDetails();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getPlayerProfile();
    }

    private void parseIntentData() {
        Uri data = getIntent().getData();
        if (data != null) {
            String host = data.getHost(); // player
            if (!StringUtil.isNullOrEmpty(host) && host.equalsIgnoreCase("player")) {
                String id = data.getQueryParameter("id"); // gamemember_id
                if (!StringUtil.isNullOrEmpty(id)) {
                    player = new Player(Integer.parseInt(id));
                }
            }
        } else {
            String json = getIntent().getStringExtra("data");
            player = new Gson().fromJson(json, Player.class);
            yxiProvider = player.getProvider() == null ? new YxiProvider() : player.getProvider();
        }
    }

    private void setupToolbar() {
        if (player.getPlayerStatus() != PlayerStatus.DELETED) {
            setupToolbar(binding.toolbar.getRoot(), getString(R.string.yxi_player_details_title),
                    getString(R.string.yxi_player_details_delete), R.color.red_D32424, view -> showDeleteConfirmation());
        } else {
            setupToolbar(binding.toolbar.getRoot(), getString(R.string.yxi_player_details_title), null, 0, null);
        }
    }

    private void setupUI() {
        boolean isNoPassword = player == null || StringUtil.isNullOrEmpty(player.getPass());
        binding.panelPassword.setVisibility(isNoPassword ? View.GONE : View.VISIBLE);
        binding.buttonResetPassword.setVisibility(isNoPassword ? View.GONE : View.VISIBLE);

        binding.imageViewPasswordToggle.setOnClickListener(view -> {
            isPasswordVisible = !isPasswordVisible;
            if (!StringUtil.isNullOrEmpty(password)) {
                updatePasswordVisibility();
            } else {
                if (isPasswordVisible) {
                    getPlayerPassword();
                }
            }
        });
        binding.buttonResetPassword.setTextColorRes(R.color.white_FFFFFF);
    }

    private void setupPlayerDetails() {
        setupToolbar();

        String icon = yxiProvider.getIcon();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).centerInside().fit().into(binding.imageViewYxi, new Callback() {
                @Override
                public void onSuccess() {

                }

                @Override
                public void onError(Exception e) {
                    binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
                }
            });
        } else {
            binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
        }

        binding.textViewYxi.setText(yxiProvider.getProvider_name());
        binding.textViewId.setText(player.getLoginId());
        binding.textViewId.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", player.getLoginId()));
        binding.textViewYxiBalance.setText(FormatUtils.formatAmount(player.getBalance()));

        PlayerStatus status = player.getPlayerStatus();
        binding.textViewStatus.setText(status.getTitle());
        binding.textViewStatus.setTextColor(ContextCompat.getColor(this, status.getColorResId()));

        binding.buttonResetPassword.setEnabled(status == PlayerStatus.ACTIVE);
        binding.buttonResetPassword.setAlpha(status == PlayerStatus.ACTIVE ? 1.0F : 0.4F);
        binding.buttonResetPassword.setOnClickListener(view -> showResetConfirmation());
    }

    private void updatePasswordVisibility() {
        binding.textViewPassword.setText(isPasswordVisible ? password : getString(R.string.placeholder_password_masked));
        binding.textViewPassword.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", password));
        binding.imageViewPasswordToggle.setImageResource(
                isPasswordVisible ? R.drawable.ic_eye_on : R.drawable.ic_eye_off
        );
    }


    private void showResetConfirmation() {
        CustomBottomSheetDialogFragment bottomSheet =
                CustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.yxi_player_reset_title),
                        "",
                        getString(R.string.yxi_player_reset_confirm),
                        getString(R.string.yxi_player_reset_cancel),
                        true,
                        new CustomBottomSheetDialogFragment.OnActionListener() {
                            @Override
                            public void onPositiveClick() {
                                resetPassword();
                            }

                            @Override
                            public void onNegativeClick() {
                            }
                        });
        bottomSheet.show(getSupportFragmentManager(), "ResetBottomSheet");
    }

    private void showDeleteConfirmation() {
        CustomBottomSheetDialogFragment bottomSheet =
                CustomBottomSheetDialogFragment.newInstance(
                        getString(R.string.yxi_player_delete_title),
                        "",
                        getString(R.string.yxi_player_delete_confirm),
                        getString(R.string.yxi_player_delete_cancel),
                        true,
                        new CustomBottomSheetDialogFragment.OnActionListener() {
                            @Override
                            public void onPositiveClick() {
                                deletePlayer();
                            }

                            @Override
                            public void onNegativeClick() {
                            }
                        });
        bottomSheet.show(getSupportFragmentManager(), "DeleteBottomSheet");
    }

    private void getPlayerProfile() {
        if (player == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerProfile request = new RequestPlayerProfile(manager.getManager_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                player = response.getData();
                yxiProvider = player.getProvider();
                setupPlayerDetails();
                getPlayerPassword();
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

    private void getPlayerPassword() {
        if (player == null || StringUtil.isNullOrEmpty(player.getPass())) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerPassword request = new RequestPlayerPassword(manager.getManager_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                password = response.getPassword();
                updatePasswordVisibility();
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

    private void resetPassword() {
        if (player == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerProfile request = new RequestPlayerProfile(manager.getManager_id(), player.getGamemember_id());
        executeApiCall(this, apiService.resetPlayerPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                getPlayerProfile();
                CustomToast.showTopToast(YxiPlayerDetailsActivity.this, getString(R.string.yxi_player_reset_success));
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

    private void deletePlayer() {
        if (player == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerDelete request = new RequestPlayerDelete(manager.getManager_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "deletePlayer IP: " + publicIp);
            executeApiCall(this, apiService.deletePlayer(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    getPlayerProfile();
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
        });
    }
}