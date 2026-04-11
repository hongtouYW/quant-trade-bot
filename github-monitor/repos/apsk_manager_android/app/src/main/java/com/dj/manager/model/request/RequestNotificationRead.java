package com.dj.manager.model.request;

public class RequestNotificationRead {
    private String manager_id;
    private String notification_id;

    public RequestNotificationRead(String managerId, String notificationId) {
        this.manager_id = managerId;
        this.notification_id = notificationId;
    }
}
