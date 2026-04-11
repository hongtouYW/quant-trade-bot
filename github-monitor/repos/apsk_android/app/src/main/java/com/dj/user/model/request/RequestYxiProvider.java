package com.dj.user.model.request;

public class RequestYxiProvider {
    private String member_id;
    private String provider_category;

    public RequestYxiProvider(String memberId, String providerCategory) {
        this.member_id = memberId;
        this.provider_category = providerCategory;
    }
}
