package com.dj.user.model.request;

public class RequestSliderRead {
    private String member_id;
    private String slider_id;

    public RequestSliderRead(String memberId, String sliderId) {
        this.member_id = memberId;
        this.slider_id = sliderId;
    }
}