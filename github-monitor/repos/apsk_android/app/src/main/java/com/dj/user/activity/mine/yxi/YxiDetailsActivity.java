package com.dj.user.activity.mine.yxi;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.text.SpannableString;
import android.text.Spanned;
import android.text.TextPaint;
import android.text.style.CharacterStyle;
import android.util.Log;
import android.view.View;
import android.view.animation.LinearInterpolator;
import android.widget.LinearLayout;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.mine.topup.TopUpActivity;
import com.dj.user.adapter.YxiRecyclerAdapter;
import com.dj.user.databinding.ActivityYxiDetailsBinding;
import com.dj.user.enums.ExternalYxiAction;
import com.dj.user.model.request.RequestPlayer;
import com.dj.user.model.request.RequestPlayerDelete;
import com.dj.user.model.request.RequestPlayerDetails;
import com.dj.user.model.request.RequestPlayerLogin;
import com.dj.user.model.request.RequestPlayerPassword;
import com.dj.user.model.request.RequestPlayerTopUpWithdraw;
import com.dj.user.model.request.RequestYxi;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Deeplink;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Yxi;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.util.YxiCacheManager;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.GridSpacingItemDecoration;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.List;

public class YxiDetailsActivity extends BaseActivity {

    private ActivityYxiDetailsBinding binding;
    private Player player;
    private YxiProvider yxiProvider;
    private Yxi selectedYxi;
    private String password;
    private YxiRecyclerAdapter yxiRecyclerAdapter;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private ExternalYxiAction action = ExternalYxiAction.DETAILS;
    private ObjectAnimator refreshAnimator;
    private boolean isTopUpRunning = false;
    private boolean isFirstLoad = true;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);

        parseIntentData();
        setupUI();
        setupProviderUI();
        setupViewData();
        setupExternalViewData();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (isFirstLoad) {
            isFirstLoad = false;
            // Full load only once
            getPlayerDetails();   // full render
            getPlayerPassword();
            getYxiList();
        } else {
            // Lightweight refresh
            refreshBalanceOnly();
        }
    }

    @Override
    protected void onBaseBackPressed() {
        if (isTopUpRunning) {
            Toast.makeText(this, getString(R.string.yxi_details_refreshing), Toast.LENGTH_SHORT).show();
            return;
        }
        playerWithdraw();
        finish();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            player = new Gson().fromJson(json, Player.class);
            yxiProvider = player.getProvider();
        }
    }

    private void setupUI() {
        binding.imageViewLeftIcon.setOnClickListener(view -> onBaseBackPressed());
        binding.imageViewRefresh.setOnClickListener(view -> playerTopUp());
        binding.panelDelete.setOnClickListener(view -> showDeleteConfirmation());

        String fullText = getString(R.string.yxi_details_step_2_desc);
        int start = fullText.indexOf(getString(R.string.yxi_details_step_2_download));
        int end = start + getString((R.string.yxi_details_step_2_download)).length();
        SpannableString spannable = new SpannableString(fullText);
        spannable.setSpan(new CharacterStyle() {
            @Override
            public void updateDrawState(@NonNull TextPaint tp) {
                tp.setColor(ContextCompat.getColor(YxiDetailsActivity.this, R.color.orange_FDB71B));
                tp.setUnderlineText(true);
            }
        }, start, end, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewStep2Desc.setText(spannable);
        binding.textViewStep2Desc.setOnClickListener(v -> {
            action = ExternalYxiAction.DOWNLOAD;
            getYxiProviderDetails();
        });
        binding.textViewStep2Desc.setHighlightColor(Color.TRANSPARENT);

        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        RecyclerView yxiRecyclerView = binding.recyclerViewYxi;
        yxiRecyclerView.setItemViewCacheSize(20);
        yxiRecyclerView.setDrawingCacheEnabled(true);
        yxiRecyclerView.setDrawingCacheQuality(View.DRAWING_CACHE_QUALITY_HIGH);

        int spanCount = 3;
        int horizontalSpacing = getResources().getDimensionPixelSize(R.dimen.provider_horizontal_spacing);
        int verticalSpacing = getResources().getDimensionPixelSize(R.dimen.provider_vertical_spacing);
        boolean includeEdge = true;
        yxiRecyclerView.setLayoutManager(new GridLayoutManager(this, spanCount));
        yxiRecyclerView.addItemDecoration(new GridSpacingItemDecoration(spanCount, horizontalSpacing, verticalSpacing, includeEdge));

        yxiRecyclerAdapter = new YxiRecyclerAdapter(this);
        yxiRecyclerView.setAdapter(yxiRecyclerAdapter);
        yxiRecyclerAdapter.setOnYxiClickListener(yxi -> {
            selectedYxi = yxi;
            binding.textViewStartYxi.setAlpha(1F);
            if (yxiProvider == null) {
                return;
            }
            if (!yxiProvider.isExternalApp() && selectedYxi == null) {
                Toast.makeText(YxiDetailsActivity.this, getString(R.string.yxi_details_choose_yxi), Toast.LENGTH_SHORT).show();
                return;
            }
            if (yxiProvider.isExternalApp()) {
                action = ExternalYxiAction.START;
                getPlayerDetails();
            } else {
                getYxiUrl();
            }
        });
        binding.textViewStartYxi.setOnClickListener(view -> {
            if (yxiProvider == null) {
                return;
            }
            if (!yxiProvider.isExternalApp() && selectedYxi == null) {
                Toast.makeText(YxiDetailsActivity.this, getString(R.string.yxi_details_choose_yxi), Toast.LENGTH_SHORT).show();
                return;
            }
//            CreditTransferDialog creditTransferDialog = new CreditTransferDialog(YxiDetailsActivity.this, false, player, new CreditTransferDialog.OnButtonClickListener() {
//                @Override
//                public void onStartYxiClicked(double creditAmount) {
//                    if (creditAmount > 0) {
//                        playerTopUp(creditAmount);
//                    } else {
            if (yxiProvider.isExternalApp()) {
                action = ExternalYxiAction.START;
                getPlayerDetails();
            } else {
                getYxiUrl();
            }
//                    }
//                }
//
//                @Override
//                public void onConvertClicked(double creditAmount) {
//                }
//            });
//            creditTransferDialog.show();
        });
        binding.textViewTopUp.setOnClickListener(view ->
                startAppActivity(new Intent(YxiDetailsActivity.this, TopUpActivity.class),
                        null, false, false, true
                ));
        binding.textViewTransfer.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(player));
            startAppActivity(new Intent(YxiDetailsActivity.this, PlayerTransferActivity.class),
                    bundle, false, false, true);
        });
    }

    private void setupProviderUI() {
        if (yxiProvider == null) return;
        // Start button & panel visibility
        if (!yxiProvider.isExternalApp()) {
            binding.textViewStartYxi.setAlpha(selectedYxi == null ? 0.5F : 1F);
            binding.panelButtons.setVisibility(View.GONE);
        } else {
            binding.textViewStartYxi.setAlpha(1F);
            binding.panelButtons.setVisibility(View.VISIBLE);
        }
        // YXI list visibility
        if (yxiProvider.isExternalApp()) {
            binding.panelYxiList.setVisibility(View.GONE);
        } else {
            binding.panelYxiList.setVisibility(View.VISIBLE);
        }
    }

    private void setupViewData() {
        if (player == null) {
            return;
        }
        binding.textViewTitle.setText(yxiProvider.getProvider_name());
        binding.textViewTitle.setSelected(true);
        binding.textViewBalance.setText(FormatUtils.formatAmount(player.getBalance()));

        binding.textViewLogin.setText(player.getLoginId());
        binding.panelLogin.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", player.getLoginId()));
        binding.panelPassword.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", password));
        binding.panelLoginDetails.setVisibility(yxiProvider.isExternalApp() && !StringUtil.isNullOrEmpty(player.getPass()) ? View.VISIBLE : View.GONE);

        String icon = yxiProvider.getBanner();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).into(binding.imageViewYxiIcon, new Callback() {
                @Override
                public void onSuccess() {
                    binding.imageViewYxiIcon.setVisibility(View.VISIBLE);
                }

                @Override
                public void onError(Exception e) {
                    binding.imageViewYxiIcon.setVisibility(View.GONE);
                }
            });
        } else {
            binding.imageViewYxiIcon.setVisibility(View.GONE);
        }
        binding.textViewYxiListLabel.setText(String.format(getString(R.string.yxi_details_yxi_list), yxiProvider.getProvider_name()));
    }

    private void setupExternalViewData() {
        if (yxiProvider == null) {
            return;
        }
        if (yxiProvider.isExternalApp()) {
            binding.panelExternalAppDetails.setVisibility(View.VISIBLE);
            String icon = yxiProvider.getBanner();
            if (!StringUtil.isNullOrEmpty(icon)) {
                if (!icon.startsWith("http")) {
                    icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
                }
                Picasso.get().load(icon).into(binding.imageViewYxiLogo, new Callback() {
                    @Override
                    public void onSuccess() {
                        binding.imageViewYxiLogo.setVisibility(View.VISIBLE);
                    }

                    @Override
                    public void onError(Exception e) {
                        binding.imageViewYxiLogo.setVisibility(View.GONE);
                    }
                });
            } else {
                binding.imageViewYxiLogo.setVisibility(View.GONE);
            }
            binding.textViewYxiName.setText(yxiProvider.getProvider_name());
            binding.textViewYxiDesc.setText(String.format(getString(R.string.yxi_details_desc), yxiProvider.getProvider_name()));
            binding.textViewStep1Desc.setText(String.format(getString(R.string.yxi_details_step_1_desc), yxiProvider.getProvider_name()));
        } else {
            binding.panelExternalAppDetails.setVisibility(View.GONE);
        }
    }

    private void setupYxiList(List<Yxi> yxiList) {
        if (yxiList != null && !yxiList.isEmpty()) {
            yxiRecyclerAdapter.setData(yxiList);

            dataPanel.setVisibility(View.VISIBLE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.GONE);
        } else {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.VISIBLE);
            loadingPanel.setVisibility(View.GONE);
        }
    }

    private void showDeleteConfirmation() {
        showCustomConfirmationDialog(
                this,
                getString(R.string.yxi_details_delete_player_title),
                String.format(getString(R.string.yxi_details_delete_player_desc), player.getLoginId()),
                getString(R.string.yxi_details_delete_player_note),
                getString(R.string.yxi_details_delete_player_negative),
                getString(R.string.yxi_details_delete_player_positive),
                new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                        deletePlayer();
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
    }

    private void openExternalApp(Deeplink deeplink, String packageName, String fallbackUrl) {
        PackageManager pm = getPackageManager();
        Intent launchIntent = !StringUtil.isNullOrEmpty(packageName)
                ? pm.getLaunchIntentForPackage(packageName)
                : null;
        String androidDeepLink = deeplink != null ? deeplink.getAndroid() : null;
        try {
            // Try opening deeplink first (if available)
            if (!StringUtil.isNullOrEmpty(androidDeepLink)) {
                Log.d("###", "openExternalApp: " + androidDeepLink);
                Intent deepLinkIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(androidDeepLink));
                deepLinkIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(deepLinkIntent);
                return;
            }
            // If no deeplink, try launching the app via package name
            if (launchIntent != null) {
                launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(launchIntent);
                return;
            }
            // Fallback → open in external browser
            Log.w("###", "App not installed, opening in browser: " + fallbackUrl);
            openExternalBrowser(fallbackUrl);

        } catch (Exception e) {
            // Safety fallback in case any intent fails
            Log.e("###", "openExternalApp failed: ", e);
            openExternalBrowser(fallbackUrl);
        }
    }

    private void openExternalBrowser(String url) {
        if (StringUtil.isNullOrEmpty(url)) {
            return;
        }
        Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
        browserIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        startActivity(browserIntent);
    }

    private void openInAppBrowser(String url) {
        if (StringUtil.isNullOrEmpty(url)) {
            return;
        }
        Bundle bundle = new Bundle();
        bundle.putString("data", url);
        bundle.putString("player", new Gson().toJson(player));
        startAppActivity(new Intent(this, YxiWebViewActivity.class),
                bundle, false, false, true);
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
        if (yxiProvider.isExternalApp()) {
            binding.textViewStartYxi.setAlpha(1F);
        }
    }

    private void getPlayerDetails() {
        if (player == null) {
            return;
        }
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerDetails request = new RequestPlayerDetails(player.getMember_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerDetails(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                player = response.getData();
                yxiProvider = player.getProvider();
                Deeplink deeplink = response.getDeeplink();

//                setupUI();
                setupProviderUI();
                setupViewData();
                setupExternalViewData();
                if (action == ExternalYxiAction.START) {
                    openExternalApp(deeplink, yxiProvider.getAndroid(), yxiProvider.getDownload());
                }
                action = ExternalYxiAction.DETAILS;
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
        }, action == ExternalYxiAction.START);
    }

    private void refreshBalanceOnly() {
        if (player == null) return;
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerDetails request = new RequestPlayerDetails(player.getMember_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerDetails(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player updated = response.getData();
                if (updated != null) {
                    player.setBalance(updated.getBalanceStr());
                    binding.textViewBalance.setText(FormatUtils.formatAmount(player.getBalance()));
                }
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

    private void getPlayerPassword() {
        if (player == null || StringUtil.isNullOrEmpty(player.getPass())) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerPassword request = new RequestPlayerPassword(player.getMember_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerPassword(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                password = response.getPassword();
                binding.textViewPassword.setText(password);
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return false;
            }
        }, false);
    }

    private void getYxiProviderDetails() {
        if (player == null || yxiProvider == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayer request = new RequestPlayer(player.getMember_id(), yxiProvider.getProvider_id());
        executeApiCall(this, apiService.getYxiProvider(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<YxiProvider> response) {
                yxiProvider = response.getData();
                setupExternalViewData();

                if (action == ExternalYxiAction.DOWNLOAD) {
                    openExternalBrowser(yxiProvider.getDownload());
                }
                action = ExternalYxiAction.DETAILS;
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

    private void getYxiList() {
        if (yxiRecyclerAdapter != null && yxiRecyclerAdapter.getItemCount() > 0) return;
//        if (player == null || yxiProvider == null || yxiProvider.isExternalApp()) {
//            binding.panelYxiList.setVisibility(View.GONE);
//            return;
//        }
//        binding.panelYxiList.setVisibility(View.VISIBLE);
        // Clear previous selection whenever reloading the list
//        selectedYxi = null;
//        if (yxiRecyclerAdapter != null) {
//            yxiRecyclerAdapter.clearSelection();
//        }
        List<Yxi> cachedList = YxiCacheManager.getCachedYxiList(yxiProvider.getProvider_id());
        if (cachedList != null && !cachedList.isEmpty()) {
            setupYxiList(cachedList);
        } else {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestYxi request = new RequestYxi(player.getMember_id(), yxiProvider.getGameplatform_id(), yxiProvider.getProvider_id());
        executeApiCall(this, apiService.getYxiList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Yxi>> response) {
                List<Yxi> yxiList = response.getData();
                YxiCacheManager.putCachedYxiList(yxiProvider.getProvider_id(), yxiList);
                setupYxiList(yxiList);
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

    private void getYxiUrl() {
        if (player == null || selectedYxi == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerLogin request = new RequestPlayerLogin(player.getMember_id(), player.getGamemember_id(), selectedYxi.getGame_id());
        executeApiCall(this, apiService.getYxiUrl(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<String> response) {
                String url = response.getUrl();
                openInAppBrowser(url);
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
        RequestPlayerDelete request = new RequestPlayerDelete(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "deletePlayer IP: " + publicIp);
            executeApiCall(this, apiService.deletePlayer(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    showCustomConfirmationDialog(
                            YxiDetailsActivity.this,
                            "", getString(R.string.yxi_details_delete_player_success), "", "",
                            getString(R.string.yxi_details_delete_player_success_positive),
                            new CustomConfirmationDialog.OnButtonClickListener() {
                                @Override
                                public void onPositiveButtonClicked() {
                                    onBaseBackPressed();
                                }

                                @Override
                                public void onNegativeButtonClicked() {

                                }
                            }
                    );
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

    private void playerTopUp() {
        if (player == null) {
            return;
        }
        isTopUpRunning = true;
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerTopUp IP: " + publicIp);
            executeApiCall(this, apiService.playerTopUpAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    isTopUpRunning = false;
                    List<Player> rawList = response.getPlayer();
                    if (rawList != null && !rawList.isEmpty()) {
                        player = rawList.get(0);
                        setupViewData();
                    }
                    stopRefreshAnimation();
                }

                @Override
                public boolean onApiError(int code, String message) {
                    isTopUpRunning = false;
                    stopRefreshAnimation();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    isTopUpRunning = false;
                    stopRefreshAnimation();
                    return true;
                }
            }, false);
        });
    }

    private void playerWithdraw() {
        if (player == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerWithdraw IP: " + publicIp);
            executeApiCall(this, apiService.playerWithdrawAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
//                    finish();
                }

                @Override
                public boolean onApiError(int code, String message) {
//                    finish();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
//                    finish();
                    return true;
                }
            }, false);
        });
    }
}