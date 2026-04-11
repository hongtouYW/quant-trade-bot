package com.dj.shop.activity;

import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.PorterDuff;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewConfiguration;
import android.view.inputmethod.InputMethodManager;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.dj.shop.R;
import com.dj.shop.activity.auth.LoginActivity;
import com.dj.shop.databinding.ViewToolbarBinding;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Token;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.StringUtil;
import com.dj.shop.widget.CustomConfirmationDialog;

import org.json.JSONObject;

import java.util.Iterator;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public abstract class BaseActivity extends AppCompatActivity {
    private float downX, downY;
    private static final int INVALID_POINTER_ID = -1;
    private int activePointerId = INVALID_POINTER_ID;
    private int touchSlop;
    private boolean didRecreate = false;
    private boolean isPasswordVisible = false;
    private boolean isCustomConfirmationDialogShown = false;

    private View loadingView;
    private String currentLanguage;
    private CustomConfirmationDialog customConfirmationDialog;
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
        if (!didRecreate && !Locale.getDefault().getLanguage().equals(currentLanguage)) {
            didRecreate = true;
            new Handler(Looper.getMainLooper()).post(() -> {
                if (!isFinishing()) recreate();
            });
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

    @NonNull
    public String getCurrentLanguage(@NonNull Context context) {
        currentLanguage = CacheManager.getString(context, CacheManager.KEY_LANGUAGE);
        if (StringUtil.isNullOrEmpty(currentLanguage)) {
            currentLanguage = "zh";
        }
        return currentLanguage;
    }

    public void setLocale(@NonNull String languageCode) {
        Locale locale = new Locale(languageCode);
        Locale.setDefault(locale);
        Configuration config = new Configuration();
        config.setLocale(locale);
        getResources().updateConfiguration(config, getResources().getDisplayMetrics());
        CacheManager.saveString(this, CacheManager.KEY_LANGUAGE, languageCode);

        Intent intent = getIntent();
        startActivity(intent);
        overridePendingTransition(0, 0);
        finish();
    }

    protected void setupToolbar(@NonNull View toolbarView, @Nullable String title, int rightIconResId, @Nullable View.OnClickListener rightIconClickListener) {
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
            passwordToggleImageView.setColorFilter(ContextCompat.getColor(this, R.color.gray_C2C3CB), PorterDuff.Mode.SRC_IN);
        } else {
            // Show password
            editText.setTransformationMethod(android.text.method.HideReturnsTransformationMethod.getInstance());
            passwordToggleImageView.setColorFilter(ContextCompat.getColor(this, R.color.gold_D4AF37), PorterDuff.Mode.SRC_IN);
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

    public void clearError(Context context, LinearLayout editTextPanel) { // , TextView errorTextView) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text));
//        errorTextView.setVisibility(View.GONE);
    }

    public void showLogoutDialog() {
        if (isFinishing() || isDestroyed()) {
            return;
        }
        showCustomConfirmationDialog(this,
                getString(R.string.session_expired_title),
                getString(R.string.session_expired_desc),
                getString(R.string.session_expired_button), () -> {
                    CacheManager.clearAll(BaseActivity.this);
                    startAppActivity(new Intent(BaseActivity.this, LoginActivity.class), null,
                            true, true, true);
                });
    }

    public void showCustomConfirmationDialog(Context context, String title, String message,
                                             String positiveButtonText, CustomConfirmationDialog.OnButtonClickListener positiveButtonClickListener) {
        if (!isCustomConfirmationDialogShown) {
            customConfirmationDialog = new CustomConfirmationDialog(context, title, message, positiveButtonText, positiveButtonClickListener);
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
        if (showLoader) showLoading(true);
        call.enqueue(new Callback<>() {
            @Override
            public void onResponse(@NonNull Call<T> call, @NonNull Response<T> response) {
                if (showLoader) showLoading(false);
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
                if (showLoader) showLoading(false);
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
        showCustomConfirmationDialog(this, String.format(Locale.ENGLISH, "%s (%d)", getString(R.string.app_name), code), message,
                getString(R.string.common_okay), this::dismissCustomConfirmationDialog);
    }

    protected void defaultFailureHandling(Throwable t) {
        showCustomConfirmationDialog(this, getString(R.string.app_name), t.getLocalizedMessage(),
                getString(R.string.common_okay), this::dismissCustomConfirmationDialog);
    }
}
