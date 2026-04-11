package com.dj.user.model.request;

public class RequestFAQ {
    private String member_id;
    private String question_type;

    public RequestFAQ(String memberId, String questionType) {
        this.member_id = memberId;
        this.question_type = questionType;
    }
}
