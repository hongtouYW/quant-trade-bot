package com.dj.user.model.request;

public class RequestYxiBookmark {
    private String member_id;
    private String provider_id;
    private String providerbookmark_name;

    public RequestYxiBookmark(String memberId, String providerId) {
        this.member_id = memberId;
        this.provider_id = providerId;
        this.providerbookmark_name = "Favourite";
    }
}
