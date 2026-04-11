package com.dj.manager.activity.notification;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.NotificationListViewAdapter;
import com.dj.manager.databinding.ActivityNotificationBinding;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Notification;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.google.gson.Gson;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.stream.Collectors;

public class NotificationActivity extends BaseActivity {
    private ActivityNotificationBinding binding;
    private Manager manager;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private NotificationListViewAdapter notificationListViewAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityNotificationBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.notification_title), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getNotificationList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        notificationListViewAdapter = new NotificationListViewAdapter(this);
        binding.listViewNotification.setAdapter(notificationListViewAdapter);
        notificationListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            startAppActivity(new Intent(NotificationActivity.this, NotificationDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    private void getNotificationList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(manager.getManager_id());
        executeApiCall(this, apiService.getNotificationList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Notification>> response) {
                List<Notification> notificationList = response.getData();
                if (notificationList != null && !notificationList.isEmpty()) {
                    List<Notification> processedList = new ArrayList<>();

                    String today = new SimpleDateFormat(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH)
                            .format(new Date());
                    String yesterday = new SimpleDateFormat(DateFormatUtils.FORMAT_YYYY_MM_DD_DASHED, Locale.ENGLISH)
                            .format(new Date(System.currentTimeMillis() - 24L * 60 * 60 * 1000));

                    List<Notification> unreadList = notificationList.stream()
                            .filter(n -> !n.getIs_read())
                            .collect(Collectors.toList());
                    if (!unreadList.isEmpty()) {
                        processedList.add(new Notification(getString(R.string.notification_latest), unreadList.size()));
                        processedList.addAll(unreadList);
                    }

                    boolean todayAdded = false;
                    boolean yesterdayAdded = false;
                    boolean earlierAdded = false;

                    for (Notification n : notificationList) {
                        if (!n.getIs_read()) continue;

                        String createdDate = n.getCreated_on();
                        String dateOnly = createdDate != null && createdDate.length() >= 10
                                ? createdDate.substring(0, 10)
                                : createdDate;

                        if (today.equals(dateOnly) && !todayAdded) {
                            processedList.add(new Notification(getString(R.string.notification_today), 0));
                            todayAdded = true;
                        } else if (yesterday.equals(dateOnly) && !yesterdayAdded) {
                            processedList.add(new Notification(getString(R.string.notification_yesterday), 0));
                            yesterdayAdded = true;
                        } else if (!today.equals(dateOnly) && !yesterday.equals(dateOnly) && !earlierAdded) {
                            processedList.add(new Notification(getString(R.string.notification_earlier), 0)); // earlier section
                            earlierAdded = true;
                        }

                        processedList.add(n);
                    }
                    notificationListViewAdapter.replaceList(processedList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                    loadingPanel.setVisibility(View.GONE);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
                }
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