package com.dj.user.activity;

import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.res.Configuration;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewConfiguration;
import android.view.inputmethod.InputMethodManager;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.window.OnBackInvokedDispatcher;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.dj.user.R;
import com.dj.user.activity.auth.LoginActivity;
import com.dj.user.databinding.ViewToolbarBinding;
import com.dj.user.databinding.ViewToolbarRedeemBinding;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Token;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.FullscreenLoadingDialog;

import org.json.JSONObject;

import java.util.Arrays;
import java.util.Iterator;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public abstract class BaseActivity extends AppCompatActivity {
    private float downX, downY;
    private static final int INVALID_POINTER_ID = -1;
    private int activePointerId = INVALID_POINTER_ID;
    private int touchSlop;

    private boolean isPasswordVisible = false;
    private boolean isLoadingDialogShown = false;
    private boolean isCustomConfirmationDialogShown = false;

    private View loadingView;
    private String currentLanguage;
    private FullscreenLoadingDialog fullscreenLoadingDialog;
    private CustomConfirmationDialog customConfirmationDialog;

    private final List<String> agentImages = Arrays.asList(
            "member/avatar_2.webp",
            "member/avatar_4.webp",
            "member/avatar_10.webp"
    );
    private final BroadcastReceiver logoutReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            showLogoutDialog();
        }
    };

    @Override
    protected void attachBaseContext(Context newBase) {
        Locale locale = new Locale(getCurrentLanguage(newBase));
        Locale.setDefault(locale);
        Configuration config = new Configuration();
        config.setLocale(locale);
        Context context = newBase.createConfigurationContext(config);
        super.attachBaseContext(context);
    }

    @Override
    public void setContentView(View view) {
        FrameLayout root = new FrameLayout(this);
        root.addView(view);
        loadingView = getLayoutInflater().inflate(R.layout.view_fullscreen_loader, root, false);
        root.addView(loadingView);
        super.setContentView(root);
        applyNavigationBarInsets(root);
    }

    protected void applyNavigationBarInsets(View root) {
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        ViewCompat.setOnApplyWindowInsetsListener(root, (v, insets) -> {
            Insets navigationBars = insets.getInsets(WindowInsetsCompat.Type.navigationBars());
            v.setPadding(v.getPaddingLeft(), v.getPaddingTop(), v.getPaddingRight(), navigationBars.bottom);
            return insets;
        });
    }

    public String getRandomAgentImage() {
        int randomIndex = new java.util.Random().nextInt(agentImages.size());
        return agentImages.get(randomIndex);
    }

    protected void showLoading(boolean show) {
        if (loadingView != null) {
            loadingView.setVisibility(show ? View.VISIBLE : View.GONE);
        }
    }

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setTransparentStatusBar();
        touchSlop = ViewConfiguration.get(this).getScaledTouchSlop();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
                    OnBackInvokedDispatcher.PRIORITY_DEFAULT,
                    () -> {
                        // Leave empty: ignore back gesture completely
                    });
        }
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                onBaseBackPressed();
            }
        });
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (!Locale.getDefault().getLanguage().equals(currentLanguage)) {
            recreate();
        }
        LocalBroadcastManager.getInstance(this).registerReceiver(logoutReceiver, new IntentFilter("FORCE_LOGOUT"));
    }

    @Override
    protected void onPause() {
        super.onPause();
        LocalBroadcastManager.getInstance(this).unregisterReceiver(logoutReceiver);
    }

    protected void onBaseBackPressed() {
        hideKeyboard(this);
        finish();
        overridePendingTransition(R.anim.left_to_right, R.anim.right_to_left);
    }

    protected void setTransparentStatusBar() {
        getWindow().getDecorView().setSystemUiVisibility(
                View.SYSTEM_UI_FLAG_LAYOUT_STABLE | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
        );
        getWindow().setStatusBarColor(Color.TRANSPARENT);
    }

    public String getCurrentLanguage(Context context) {
        currentLanguage = CacheManager.getString(context, CacheManager.KEY_LANGUAGE);
        if (StringUtil.isNullOrEmpty(currentLanguage)) {
            currentLanguage = "zh";
        }
        return currentLanguage;
    }

    public void setLocale(String languageCode) {
        Locale locale = new Locale(languageCode);
        Locale.setDefault(locale);
        Configuration config = new Configuration();
        config.setLocale(locale);
        getResources().updateConfiguration(config, getResources().getDisplayMetrics());
        CacheManager.saveString(this, CacheManager.KEY_LANGUAGE, languageCode);
        recreate();
    }

    protected void setupToolbar(View toolbarView, String title, int rightIconResId, @Nullable View.OnClickListener rightIconClickListener) {
        ViewToolbarBinding toolbarBinding = ViewToolbarBinding.bind(toolbarView);

        toolbarBinding.textViewTitle.setText(title);
        toolbarBinding.imageViewLeftIcon.setOnClickListener(v -> onBaseBackPressed());

        if (rightIconResId != 0) {
            toolbarBinding.imageViewRightIcon.setImageResource(rightIconResId);
            toolbarBinding.imageViewRightIcon.setVisibility(View.VISIBLE);
            toolbarBinding.imageViewRightIcon.setOnClickListener(rightIconClickListener);
        } else {
            toolbarBinding.imageViewRightIcon.setVisibility(View.INVISIBLE);
            toolbarBinding.imageViewRightIcon.setOnClickListener(null);
        }
    }

    protected void setupToolbar(View toolbarView, String title, @Nullable View.OnClickListener rightButtonClickListener) {
        ViewToolbarRedeemBinding toolbarBinding = ViewToolbarRedeemBinding.bind(toolbarView);
        toolbarBinding.textViewTitle.setText(title);
        toolbarBinding.imageViewLeftIcon.setOnClickListener(v -> onBaseBackPressed());
        toolbarBinding.textViewRedeem.setOnClickListener(rightButtonClickListener);
    }

    @Override
    public boolean dispatchTouchEvent(MotionEvent event) {
        switch (event.getActionMasked()) {
            case MotionEvent.ACTION_DOWN:
                downX = event.getRawX();
                downY = event.getRawY();
                activePointerId = event.getPointerId(0);
                break;

            case MotionEvent.ACTION_UP:
                float upX = event.getRawX();
                float upY = event.getRawY();

                float deltaX = Math.abs(upX - downX);
                float deltaY = Math.abs(upY - downY);

                boolean isTap = (deltaX < touchSlop && deltaY < touchSlop);

                if (isTap) {
                    View currentFocus = getCurrentFocus();
                    if ((currentFocus instanceof EditText)) {
                        int[] location = new int[2];
                        currentFocus.getLocationOnScreen(location);

                        float viewLeft = location[0];
                        float viewTop = location[1];
                        float viewRight = viewLeft + currentFocus.getWidth();
                        float viewBottom = viewTop + currentFocus.getHeight();

                        if (upX < viewLeft || upX > viewRight || upY < viewTop || upY > viewBottom) {
                            hideKeyboard(this);
                            currentFocus.clearFocus();
                        }
                    }
                }
                activePointerId = INVALID_POINTER_ID;
                break;

            case MotionEvent.ACTION_CANCEL:
                activePointerId = INVALID_POINTER_ID;
                break;
        }

        return super.dispatchTouchEvent(event);
    }

    public void hideKeyboard(Activity activity) {
        InputMethodManager imm = (InputMethodManager) activity.getSystemService(Context.INPUT_METHOD_SERVICE);
        View view = activity.getCurrentFocus();
        if (view == null) view = new View(activity);
        imm.hideSoftInputFromWindow(view.getWindowToken(), 0);
        view.clearFocus();
    }

    public void startAppActivity(Intent intent, Bundle bundle, boolean isFinish, boolean isStartNew, boolean animate) {
        if (isStartNew) {
            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK | Intent.FLAG_ACTIVITY_NEW_TASK);
        }
        if (bundle != null) {
            intent.putExtras(bundle);
        }
        startActivity(intent);
        if (animate) {
            overridePendingTransition(R.anim.enter, R.anim.exit);
        }
        if (isFinish) {
            finish();
        }
    }

    public void togglePasswordVisibility(EditText editText, ImageView passwordToggleImageView) {
        int selection = editText.getSelectionEnd();
        if (isPasswordVisible) {
            // Hide password
            editText.setTransformationMethod(android.text.method.PasswordTransformationMethod.getInstance());
            passwordToggleImageView.setImageResource(R.drawable.ic_eye_off);
        } else {
            // Show password
            editText.setTransformationMethod(android.text.method.HideReturnsTransformationMethod.getInstance());
            passwordToggleImageView.setImageResource(R.drawable.ic_eye_on);
        }
        // Restore cursor position
        editText.setSelection(Math.min(selection, editText.getText().length()));
        isPasswordVisible = !isPasswordVisible;
    }

    public void showError(Context context, LinearLayout editTextPanel) { // , TextView errorTextView, String errorMsg) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text_error));
//        errorTextView.setText(errorMsg);
//        errorTextView.setVisibility(errorMsg.isEmpty() ? View.GONE : View.VISIBLE);
    }

    public void showErrorTransparent(Context context, LinearLayout editTextPanel) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text_transparent_error));
    }

    public void clearError(Context context, LinearLayout editTextPanel) { // , TextView errorTextView) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text));
//        errorTextView.setVisibility(View.GONE);
    }

    public void clearErrorTransparent(Context context, LinearLayout editTextPanel) { // , TextView errorTextView) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text_transparent));
//        errorTextView.setVisibility(View.GONE);
    }

    public void showLogoutDialog() {
        if (isFinishing() || isDestroyed()) {
            return;
        } // TODO: 27/02/2026  
        showCustomConfirmationDialog(
                this,
                "Session Expired", "Your session has expired. Please log in again", "", "",
                "Okay", new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                        CacheManager.clearAll(BaseActivity.this);
                        startAppActivity(new Intent(BaseActivity.this, LoginActivity.class), null,
                                true, true, true);
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
    }

    public void showFullScreenLoadingDialog(Context context) {
        if (!isLoadingDialogShown) {
            fullscreenLoadingDialog = new FullscreenLoadingDialog(context);
            try {
                if (!isFinishing()) {
                    fullscreenLoadingDialog.show();
                    isLoadingDialogShown = true;
                } else {
                    isLoadingDialogShown = false;
                }
            } catch (RuntimeException e) {
                Log.e("### RuntimeException", e.toString());
                isLoadingDialogShown = false;
            }
        }
    }

    public void dismissLoadingDialog() {
        if (!isFinishing() && fullscreenLoadingDialog != null) {
            isLoadingDialogShown = false;
            fullscreenLoadingDialog.dismiss();
            fullscreenLoadingDialog.cancel();
        }
    }

    public void showCustomConfirmationDialog(Context context, String title, String message, String note, String negativeText, String positiveButtonText,
                                             CustomConfirmationDialog.OnButtonClickListener buttonClickListener) {
        if (!isCustomConfirmationDialogShown) {
            customConfirmationDialog = new CustomConfirmationDialog(context, title, message, note, negativeText, positiveButtonText, true, buttonClickListener);
            try {
                if (!isFinishing()) {
                    customConfirmationDialog.show();
                    isCustomConfirmationDialogShown = true;
                } else {
                    isCustomConfirmationDialogShown = false;
                }
            } catch (RuntimeException e) {
                Log.e("### RuntimeException", e.toString());
                isCustomConfirmationDialogShown = false;
            }
        }
    }

    public void showCustomConfirmationDialog(Context context, String title, String message, String note, String negativeText, String positiveButtonText,
                                             boolean isCenteredMessage, CustomConfirmationDialog.OnButtonClickListener buttonClickListener) {
        if (!isCustomConfirmationDialogShown) {
            customConfirmationDialog = new CustomConfirmationDialog(context, title, message, note, negativeText, positiveButtonText, isCenteredMessage, buttonClickListener);
            try {
                if (!isFinishing()) {
                    customConfirmationDialog.show();
                    isCustomConfirmationDialogShown = true;
                } else {
                    isCustomConfirmationDialogShown = false;
                }
            } catch (RuntimeException e) {
                Log.e("### RuntimeException", e.toString());
                isCustomConfirmationDialogShown = false;
            }
        }
    }

    public void dismissCustomConfirmationDialog() {
        if (!isFinishing() && customConfirmationDialog != null) { // && customConfirmationDialog.isShowing() && isCustomConfirmationDialogShown) {
            isCustomConfirmationDialogShown = false;
            customConfirmationDialog.dismiss();
            customConfirmationDialog.cancel();
        }
    }

    // Centralized API handler
    public <T> void executeApiCall(Context context, Call<T> call, ApiCallback<T> callback, boolean showLoader) {
        showLoading(showLoader);
        call.enqueue(new Callback<T>() {
            @Override
            public void onResponse(@NonNull Call<T> call, @NonNull Response<T> response) {
                showLoading(false);
                if (response.isSuccessful()) {
                    T body = response.body();
                    if (body instanceof BaseResponse) {
                        BaseResponse<?> baseResponse = (BaseResponse<?>) body;

                        Token token = baseResponse.getToken();
                        if (token != null) {
                            CacheManager.saveString(context, CacheManager.KEY_ACCESS_TOKEN, token.getAccess_token());
                            CacheManager.saveString(context, CacheManager.KEY_REFRESH_TOKEN, token.getRefresh_token());
                        }

                        if (baseResponse.isStatus()) {
                            callback.onSuccess(body);
                        } else {
                            String errorMsg = baseResponse.getMessage() != null
                                    ? baseResponse.getMessage()
                                    : "操作失败";
                            // Parse detailed field errors
                            String rawError = baseResponse.getError();
                            if (rawError != null) {
                                try {
                                    Object parsedError = new org.json.JSONTokener(rawError).nextValue();
                                    if (parsedError instanceof JSONObject) {
                                        String details = buildDetailedErrorMessage((JSONObject) parsedError);
                                        if (!details.isEmpty()) {
                                            errorMsg += "\n" + details;
                                        }
                                    } else if (parsedError instanceof String) {
                                        // Simple error string
                                        errorMsg += "\n" + parsedError;
                                    }
                                } catch (Exception e) {
                                    Log.e("### API_PARSE_ERROR", "Failed to parse BaseResponse.error", e);
                                }
                            }

                            boolean handled = callback.onApiError(response.code(), errorMsg);
                            if (!handled) {
                                defaultApiErrorHandling(response.code(), errorMsg);
                            }
                        }
                    }
                } else {
                    String errorMsg = "Unknown error";
                    int statusCode = response.code();

                    try {
                        if (response.errorBody() != null) {
                            String errorBodyStr = response.errorBody().string(); // Already decrypted by interceptor
                            JSONObject json = new JSONObject(errorBodyStr);
                            String finalMessage = "";
                            if (json.has("error")) {
                                Object errorField = json.get("error");
                                if (errorField instanceof JSONObject) {
                                    String details = buildDetailedErrorMessage((JSONObject) errorField);
                                    if (!details.isEmpty()) {
                                        finalMessage = details;
                                    }
                                } else if (errorField instanceof String) {
                                    String errorStr = (String) errorField;
                                    if (!errorStr.isEmpty()) {
                                        finalMessage = errorStr;
                                    }
                                }
                            }
                            if (finalMessage.isEmpty() && json.has("message")) {
                                String messageStr = json.getString("message");
                                if (!messageStr.isEmpty()) {
                                    finalMessage = messageStr;
                                }
                            }
                            if (!finalMessage.isEmpty()) {
                                errorMsg = finalMessage;
                            }
                        }
                    } catch (Exception e) {
                        Log.e("### onResponse: ", e.toString());
                    }

                    boolean handled = callback.onApiError(statusCode, errorMsg);
                    if (!handled) {
                        defaultApiErrorHandling(statusCode, errorMsg);
                    }
                }
            }

            @Override
            public void onFailure(@NonNull Call<T> call, @NonNull Throwable t) {
                showLoading(false);
                boolean handled = callback.onFailure(t);
                if (!handled) {
                    defaultFailureHandling(t);
                }
            }
        });
    }

    private String buildDetailedErrorMessage(JSONObject errorJson) {
        StringBuilder details = new StringBuilder();
        try {
            Iterator<String> keys = errorJson.keys();
            while (keys.hasNext()) {
                String key = keys.next();
                details
//                        .append(key)
//                        .append(": ")
                        .append(errorJson.getJSONArray(key).getString(0))
                        .append("\n");
            }
        } catch (Exception e) {
            Log.e("### ERROR_PARSE", "Failed to parse error details", e);
        }
        return details.toString().trim();
    }

    protected void defaultApiErrorHandling(int code, String message) {
        showCustomConfirmationDialog(this, String.format(Locale.ENGLISH, "%s (%d)", getString(R.string.app_name), code), message, "", "",
                "确定", new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
    }

    protected void defaultFailureHandling(Throwable t) {
        showCustomConfirmationDialog(this, getString(R.string.app_name), t.getLocalizedMessage(), "", "",
                "确定", new CustomConfirmationDialog.OnButtonClickListener() {
                    @Override
                    public void onPositiveButtonClicked() {
                    }

                    @Override
                    public void onNegativeButtonClicked() {

                    }
                }
        );
    }
}
