package com.dj.user.model.request;

public class RequestYxiBookmarkDelete {
    private String member_id;
    private String providerbookmark_id;

    public RequestYxiBookmarkDelete(String memberId, String gameBookmarkId) {
        this.member_id = memberId;
        this.providerbookmark_id = gameBookmarkId;
    }
}
