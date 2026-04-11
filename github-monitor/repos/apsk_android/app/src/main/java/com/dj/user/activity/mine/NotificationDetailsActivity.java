package com.dj.user.activity.mine;

import android.os.Bundle;

import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityNotificationDetailsBinding;
import com.dj.user.model.request.RequestNotificationRead;
import com.dj.user.model.request.RequestSliderRead;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Notification;
import com.dj.user.model.response.Slider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.google.gson.Gson;

public class NotificationDetailsActivity extends BaseActivity {

    private ActivityNotificationDetailsBinding binding;
    private Member member;
    private boolean isNotification = false;
    private Notification notification;
    private Slider slider;
    private String title;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityNotificationDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), title, 0, null);
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (isNotification && notification != null && !notification.getIs_read()) {
            markNotificationRead();
        }
        if (!isNotification && slider != null && !slider.isRead()) {
            markSliderRead();
        }
    }

    private void parseIntentData() {
        isNotification = getIntent().getBooleanExtra("isNotification", false);
        String json = getIntent().getStringExtra("data");
        if (isNotification) {
            notification = new Gson().fromJson(json, Notification.class);
            if (notification != null) {
                title = !StringUtil.isNullOrEmpty(notification.getTitle()) ? notification.getTitle() : "";
                setupUI(notification);
            }
        } else {
            slider = new Gson().fromJson(json, Slider.class);
            if (slider != null) {
                title = !StringUtil.isNullOrEmpty(slider.getTitle()) ? slider.getTitle() : "";
                setupUI(slider);
            }
        }
    }

    private void setupUI(Notification notification) {
        if (notification == null) {
            return;
        }
        binding.textViewSubject.setText(notification.getNotification_desc());
    }

    private void setupUI(Slider slider) {
        if (slider == null) {
            return;
        }
        binding.textViewSubject.setText(slider.getSlider_desc());
    }

    private void markNotificationRead() {
        if (notification == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestNotificationRead request = new RequestNotificationRead(member.getMember_id(), notification.getNotification_id(), notification.getMessagetype());
        executeApiCall(this, apiService.markNotificationRead(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Notification> response) {
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

    private void markSliderRead() {
        if (slider == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestSliderRead request = new RequestSliderRead(member.getMember_id(), slider.getSlider_id());
        executeApiCall(this, apiService.markSliderRead(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Slider> response) {
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
}