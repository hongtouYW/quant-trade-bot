package com.dj.user.model.request;

public class RequestPromotion {
    private String member_id;
    private String promotion_type;

    public RequestPromotion(String memberId, String promotionType) {
        this.member_id = memberId;
        this.promotion_type = promotionType;
    }
}
