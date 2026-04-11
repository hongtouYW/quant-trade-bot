package com.dj.user.activity.mine.yxi;

import android.annotation.SuppressLint;
import android.graphics.Bitmap;
import android.os.Bundle;
import android.util.Log;
import android.view.MotionEvent;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.LinearLayout;
import android.widget.Toast;

import androidx.annotation.Nullable;

import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityYxiWebViewBinding;
import com.dj.user.enums.YxiActionType;
import com.dj.user.model.request.RequestPlayerDetails;
import com.dj.user.model.request.RequestPlayerTopUpWithdraw;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Player;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.widget.CreditTransferDialog;
import com.dj.user.widget.CustomToast;
import com.dj.user.widget.YxiActionDialog;
import com.google.gson.Gson;

public class YxiWebViewActivity extends BaseActivity {
    private ActivityYxiWebViewBinding binding;
    private String url;
    private Player player;
    private boolean isLobby = false;
    private WebView webView;
    private LinearLayout loadingPanel;
    private YxiActionDialog yxiActionDialog;
    private boolean isTopUpRunning = false;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiWebViewBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupUI();
        loadWebUrlIntoWebView();
    }

    @Override
    protected void onResume() {
        super.onResume();
        enableImmersiveMode();
//        getPlayerDetails(YxiActionType.NONE);
    }

    @Override
    protected void onPause() {
        super.onPause();
        disableImmersiveMode();
    }

    @Override
    protected void onBaseBackPressed() {
//        getPlayerDetails(YxiActionType.EXIT, 0, true);
        if (isLobby) {
            playerWithdraw();
            return;
        }
        finish();
    }

    private void parseIntentData() {
        url = getIntent().getStringExtra("data");
        isLobby = getIntent().getBooleanExtra("isLobby", false);
        String json = getIntent().getStringExtra("player");
        if (json != null) {
            player = new Gson().fromJson(json, Player.class);
        }
    }

    @SuppressLint("ClickableViewAccessibility")
    private void setupUI() {
        webView = binding.webView;
        loadingPanel = binding.panelLoading.getRoot();
        binding.panelHome.setOnTouchListener(new View.OnTouchListener() {
            private float downX, downY;
            private long downTime;
            private static final int CLICK_THRESHOLD = 200; // ms
            private static final float MOVE_THRESHOLD = 10f; // dp

            @Override
            public boolean onTouch(View v, MotionEvent event) {
                switch (event.getActionMasked()) {
                    case MotionEvent.ACTION_DOWN:
                        downX = event.getRawX();
                        downY = event.getRawY();
                        downTime = System.currentTimeMillis();
                        return true;

                    case MotionEvent.ACTION_MOVE:
                        float moveX = event.getRawX() - downX;
                        float moveY = event.getRawY() - downY;

                        // move the view
                        float newX = v.getX() + moveX;
                        float newY = v.getY() + moveY;

                        // keep within parent bounds
                        View parent = (View) v.getParent();
                        newX = Math.max(0, Math.min(newX, parent.getWidth() - v.getWidth()));
                        newY = Math.max(0, Math.min(newY, parent.getHeight() - v.getHeight()));

                        v.setX(newX);
                        v.setY(newY);

                        downX = event.getRawX();
                        downY = event.getRawY();
                        return true;

                    case MotionEvent.ACTION_UP:
                        long clickDuration = System.currentTimeMillis() - downTime;
                        float deltaX = Math.abs(event.getRawX() - downX);
                        float deltaY = Math.abs(event.getRawY() - downY);

                        if (clickDuration < CLICK_THRESHOLD && deltaX < MOVE_THRESHOLD && deltaY < MOVE_THRESHOLD) {
                            v.performClick();
                        }
                        return true;

                    default:
                        return false;
                }
            }
        });
        binding.panelHome.setOnClickListener(v -> {
            yxiActionDialog = new YxiActionDialog(YxiWebViewActivity.this, new YxiActionDialog.OnButtonClickListener() {
                @Override
                public void onRefreshClicked() {
                    playerTopUp();
                }

                @Override
                public void onTransferClicked() {
//                    getPlayerDetails(YxiActionType.TRANSFER, 0, false);
                }

                @Override
                public void onWithdrawClicked() {
                    getPlayerDetails(YxiActionType.WITHDRAW);
                }

                @Override
                public void onExitClicked() {
//                    getPlayerDetails(YxiActionType.NONE, 0, true);
                    if (isLobby) {
                        playerWithdraw();
                        return;
                    }
                    onBaseBackPressed();
                }
            });
            yxiActionDialog.show();
        });
    }

    private void enableImmersiveMode() {
        getWindow().getDecorView().setSystemUiVisibility(
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                        | View.SYSTEM_UI_FLAG_FULLSCREEN
                        | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                        | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                        | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                        | View.SYSTEM_UI_FLAG_LAYOUT_STABLE
        );
    }

    private void disableImmersiveMode() {
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_VISIBLE);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void loadWebUrlIntoWebView() {
//        webView.requestDisallowInterceptTouchEvent(true);
//        webView.requestFocus(View.FOCUS_DOWN);
        // Optional: Enable DOM storage and caching (helps many modern sites)
        webView.getSettings().setDomStorageEnabled(true);
        webView.getSettings().setLoadsImagesAutomatically(true);

        webView.getSettings().setJavaScriptEnabled(true);
        webView.getSettings().setJavaScriptCanOpenWindowsAutomatically(true);
        webView.getSettings().setMediaPlaybackRequiresUserGesture(false);

        webView.getSettings().setUseWideViewPort(true);
        webView.getSettings().setLoadWithOverviewMode(true);
        webView.getSettings().setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        webView.setLayerType(View.LAYER_TYPE_NONE, null);

        webView.setWebViewClient(new WebViewController());

        // Enable cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        webView.loadUrl(url);

        webView.setFocusable(true);
        webView.setFocusableInTouchMode(true);
        webView.requestFocus(View.FOCUS_DOWN);
        webView.requestFocusFromTouch();
    }

    private class WebViewController extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            return handleUrl(url);
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            String targetUrl = request.getUrl().toString();
            return handleUrl(targetUrl);
        }

        @Override
        public void onPageStarted(WebView view, String url, Bitmap favicon) {
            webView.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            webView.setVisibility(View.VISIBLE);
            loadingPanel.setVisibility(View.GONE);
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            super.onReceivedError(view, request, error);
            webView.setVisibility(View.VISIBLE);
            loadingPanel.setVisibility(View.GONE);
        }

        private boolean handleUrl(String url) {
            Log.d("###", "Yxi WebView URL: " + url);
            if (url.startsWith("https://apsk.cc") || url.startsWith("https://sebanyak.com")) {
                runOnUiThread(() -> {
                    if (isLobby) {
                        playerWithdraw();
                    } else {
                        finish();
                    }
                });
                return true; // prevent WebView from loading it
            }
            return false; // allow normal loading
        }
    }

    private void getPlayerDetails(YxiActionType yxiActionType) {
        if (player == null) {
            return;
        }
        showFullScreenLoadingDialog(this);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerDetails request = new RequestPlayerDetails(player.getMember_id(), player.getGamemember_id());
        executeApiCall(this, apiService.getPlayerDetails(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                player = response.getData();
                if (yxiActionType == YxiActionType.WITHDRAW) {
                    CreditTransferDialog creditTransferDialog = new CreditTransferDialog(
                            YxiWebViewActivity.this, true, player, new CreditTransferDialog.OnButtonClickListener() {
                        @Override
                        public void onStartYxiClicked(double creditAmount) {

                        }

                        @Override
                        public void onConvertClicked(double creditAmount) {
                            playerWithdraw(creditAmount);
                        }
                    });
                    creditTransferDialog.show();
                }
                dismissLoadingDialog();
            }

            @Override
            public boolean onApiError(int code, String message) {
                dismissLoadingDialog();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                dismissLoadingDialog();
                return false;
            }
        }, false);
    }

    private void playerTopUp() {
        if (player == null) {
            return;
        }
        isTopUpRunning = true;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerTopUp IP: " + publicIp);
            executeApiCall(this, apiService.playerTopUpAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    isTopUpRunning = false;
                    if (yxiActionDialog != null) {
                        yxiActionDialog.stopRefreshAnimation();
                    }
                }

                @Override
                public boolean onApiError(int code, String message) {
                    isTopUpRunning = false;
                    if (yxiActionDialog != null) {
                        yxiActionDialog.stopRefreshAnimation();
                    }
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    isTopUpRunning = false;
                    if (yxiActionDialog != null) {
                        yxiActionDialog.stopRefreshAnimation();
                    }
                    return true;
                }
            }, false);
        });
    }

    private void playerWithdraw(double amount) {
        if (player == null) {
            return;
        }
        showFullScreenLoadingDialog(this);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id(), amount);
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerWithdraw IP: " + publicIp);
            executeApiCall(this, apiService.playerWithdraw(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    dismissLoadingDialog();
                    CustomToast.showTopToast(YxiWebViewActivity.this, "成功提现");
                }

                @Override
                public boolean onApiError(int code, String message) {
                    dismissLoadingDialog();
                    return false;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    dismissLoadingDialog();
                    return false;
                }
            }, false);
        });
    }

    private void playerWithdraw() {
        if (isTopUpRunning) {
            Toast.makeText(this, "正在刷新中，请稍候再返回", Toast.LENGTH_SHORT).show();
            return;
        }
        if (player == null) {
            return;
        }
        showFullScreenLoadingDialog(this);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerTopUpWithdraw request = new RequestPlayerTopUpWithdraw(player.getMember_id(), player.getGamemember_id());
        request.fetchPublicIp(publicIp -> {
            Log.d("###", "playerWithdraw IP: " + publicIp);
            executeApiCall(this, apiService.playerWithdrawAll(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
                    dismissLoadingDialog();
                    finish();
                }

                @Override
                public boolean onApiError(int code, String message) {
                    dismissLoadingDialog();
                    finish();
                    return true;
                }

                @Override
                public boolean onFailure(Throwable t) {
                    dismissLoadingDialog();
                    finish();
                    return true;
                }
            }, false);
        });
    }
}
