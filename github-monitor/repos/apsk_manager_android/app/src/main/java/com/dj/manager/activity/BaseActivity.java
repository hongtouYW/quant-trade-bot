package com.dj.manager.activity;

import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.res.Configuration;
import android.graphics.Color;
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

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.dj.manager.R;
import com.dj.manager.databinding.ViewToolbarBinding;
import com.dj.manager.databinding.ViewToolbarButtonsBinding;
import com.dj.manager.databinding.ViewToolbarFilterBinding;
import com.dj.manager.databinding.ViewToolbarTextBinding;
import com.dj.manager.enums.LogFilterType;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Token;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.StringUtil;
import com.dj.manager.widget.CustomConfirmationDialog;

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
        currentLanguage = languageCode;
        CacheManager.saveString(this, CacheManager.KEY_LANGUAGE, languageCode);
        Locale locale = new Locale(languageCode);
        Locale.setDefault(locale);
        Configuration config = new Configuration();
        config.setLocale(locale);
        getResources().updateConfiguration(config, getResources().getDisplayMetrics());
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

    protected void setupToolbar(View toolbarView, String title, int rightIconResId, int selectedRightIconResId, @Nullable View.OnClickListener rightIconClickListener) {
        ViewToolbarFilterBinding toolbarBinding = ViewToolbarFilterBinding.bind(toolbarView);

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
        if (selectedRightIconResId != 0) {
            toolbarBinding.imageViewRightIconSelected.setImageResource(selectedRightIconResId);
            toolbarBinding.imageViewRightIconSelected.setOnClickListener(rightIconClickListener);
        }
        toolbarBinding.imageViewRightIconSelected.setVisibility(View.GONE);
    }

    protected void updateFilterToolbar(View toolbarView, LogFilterType logFilterType, boolean isAll) {
        ViewToolbarFilterBinding toolbarBinding = ViewToolbarFilterBinding.bind(toolbarView);
        toolbarBinding.imageViewRightIcon.setVisibility(isAll ? View.VISIBLE : View.GONE);
        toolbarBinding.imageViewRightIconSelected.setVisibility(isAll ? View.GONE : View.VISIBLE);
    }

    protected void setupToolbar(View toolbarView, String title, String rightActionText, int rightActionColorResId, @Nullable View.OnClickListener rightActionClickListener) {
        ViewToolbarTextBinding toolbarBinding = ViewToolbarTextBinding.bind(toolbarView);

        toolbarBinding.textViewTitle.setText(title);
        toolbarBinding.imageViewLeftIcon.setOnClickListener(v -> onBaseBackPressed());

        if (!StringUtil.isNullOrEmpty(rightActionText)) {
            toolbarBinding.textViewRightAction.setText(rightActionText);
            toolbarBinding.textViewRightAction.setTextColor(ContextCompat.getColor(this, rightActionColorResId));
            toolbarBinding.textViewRightAction.setVisibility(View.VISIBLE);
            toolbarBinding.textViewRightAction.setOnClickListener(rightActionClickListener);
        } else {
            toolbarBinding.textViewRightAction.setVisibility(View.INVISIBLE);
            toolbarBinding.textViewRightAction.setOnClickListener(null);
        }
    }

    protected void setupToolbar(View toolbarView, String title, int rightIcon1ResId, int selectedRightIcon1ResId, @Nullable View.OnClickListener rightIcon1ClickListener, int rightIcon2ResId, @Nullable View.OnClickListener rightIcon2ClickListener) {
        ViewToolbarButtonsBinding toolbarBinding = ViewToolbarButtonsBinding.bind(toolbarView);

        toolbarBinding.textViewTitle.setText(title);
        toolbarBinding.imageViewLeftIcon.setOnClickListener(v -> onBaseBackPressed());

        if (rightIcon1ResId != 0) {
            toolbarBinding.imageViewRightIcon1.setImageResource(rightIcon1ResId);
            toolbarBinding.imageViewRightIcon1.setVisibility(View.VISIBLE);
            toolbarBinding.imageViewRightIcon1.setOnClickListener(rightIcon1ClickListener);
        } else {
            toolbarBinding.imageViewRightIcon1.setVisibility(View.INVISIBLE);
            toolbarBinding.imageViewRightIcon1.setOnClickListener(null);
        }
        if (selectedRightIcon1ResId != 0) {
            toolbarBinding.imageViewRightIconSelected.setImageResource(selectedRightIcon1ResId);
            toolbarBinding.imageViewRightIconSelected.setOnClickListener(rightIcon1ClickListener);
        }
        toolbarBinding.imageViewRightIconSelected.setVisibility(View.INVISIBLE);
        if (rightIcon2ResId != 0) {
            toolbarBinding.imageViewRightIcon2.setImageResource(rightIcon2ResId);
            toolbarBinding.imageViewRightIcon2.setVisibility(View.VISIBLE);
            toolbarBinding.imageViewRightIcon2.setOnClickListener(rightIcon2ClickListener);
        } else {
            toolbarBinding.imageViewRightIcon2.setVisibility(View.INVISIBLE);
            toolbarBinding.imageViewRightIcon2.setOnClickListener(null);
        }
    }

    protected void updateButtonsToolbar(View toolbarView, boolean hasFilter) {
        ViewToolbarButtonsBinding toolbarBinding = ViewToolbarButtonsBinding.bind(toolbarView);
        toolbarBinding.imageViewRightIcon1.setVisibility(hasFilter ? View.INVISIBLE : View.VISIBLE);
        toolbarBinding.imageViewRightIconSelected.setVisibility(hasFilter ? View.VISIBLE : View.INVISIBLE);
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

    public void startAppActivity(Intent intent, Bundle bundle, boolean isFinish, boolean isStartNew, boolean isClearTop, boolean animate) {
        if (isStartNew) {
            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK | Intent.FLAG_ACTIVITY_NEW_TASK);
        }
        if (isClearTop) {
            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
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

    public void clearError(Context context, LinearLayout editTextPanel) { // , TextView errorTextView) {
        editTextPanel.setBackground(ContextCompat.getDrawable(context, R.drawable.bg_edit_text));
//        errorTextView.setVisibility(View.GONE);
    }

    public void showLogoutDialog() {
        if (isFinishing() || isDestroyed()) {
            return;
        }
        showCustomConfirmationDialog(this,
                getString(R.string.session_expired_title), getString(R.string.session_expired_desc),
                getString(R.string.session_expired_okay), () -> {
                    CacheManager.clearAll(BaseActivity.this);
                    startAppActivity(new Intent(BaseActivity.this, LoginActivity.class), null,
                            true, true, false, true);
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
        call.enqueue(new Callback<T>() {
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
                                    : getString(R.string.whoops_something_went_wrong);
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
