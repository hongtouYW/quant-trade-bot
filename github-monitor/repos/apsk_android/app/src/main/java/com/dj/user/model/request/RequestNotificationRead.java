package com.dj.user.model.request;

public class RequestNotificationRead {
    private String member_id;
    private String notification_id;
    private String messagetype;

    public RequestNotificationRead(String memberId, String notificationId, String messageType) {
        this.member_id = memberId;
        this.notification_id = notificationId;
        this.messagetype = messageType;
    }
}
